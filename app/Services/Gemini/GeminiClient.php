<?php

namespace App\Services\Gemini;

use App\Services\Audio\Mp3Splitter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente da API do Gemini (Google AI Studio).
 *
 * gemini-2.5-flash entende áudio nativamente (manda o mp3 direto e pede a
 * transcrição, sem modelo Whisper separado) e o mesmo modelo gera o
 * resumo/flashcards.
 *
 * As mensagens de erro fazem parte do contrato com o LessonPipeline:
 *   - "Gemini falhou: ..."        -> erro transitório (429/5xx/conexão), o cron tenta de novo
 *   - "GEMINI_OUTPUT_TRUNCATED"   -> resposta cortada, o pipeline divide o texto em partes
 */
class GeminiClient
{
    public const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';
    public const TRANSCRIBE_MODEL = 'gemini-2.5-flash';
    public const SUMMARY_MODEL = 'gemini-2.5-flash';

    // Áudio vai inline (base64) no corpo da requisição, e o payload total tem
    // teto de ~20MB. Base64 infla ~33%, então o pedaço precisa ficar bem abaixo.
    public const MAX_CHUNK_BYTES = 15 * 1024 * 1024;

    // 60000 caracteres cobre a maioria das aulas inteiras numa única chamada de
    // resumo, evitando o caminho em partes na prática.
    public const SUMMARY_CHUNK_CHARS = 60000;

    public function __construct(private readonly string $apiKey)
    {
    }

    /**
     * POST com no máximo 2 tentativas rápidas (absorve um blip de conexão ou um
     * 429/5xx que resolve em segundos). Erros definitivos sobem na hora.
     *
     * Não é o retry "de verdade": a hospedagem mata requisições com mais de
     * ~120s, então não dá pra dormir muito aqui. O retry real é o cron de 1 em 1
     * minuto, que tenta o mesmo passo do pipeline de novo.
     */
    private function postWithRetry(string $model, array $payload, int $timeout = 90, int $maxAttempts = 2): array
    {
        $url = self::API_BASE . '/' . $model . ':generateContent?key=' . urlencode($this->apiKey);
        $jsonBody = json_encode($payload);
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $retryAfter = null;
            try {
                $response = Http::timeout($timeout)
                    ->withBody($jsonBody, 'application/json')
                    ->post($url);

                if ($response->status() === 200) {
                    return ['raw' => $response->body(), 'parsed' => $response->json()];
                }

                $httpCode = $response->status();
                $errorMessage = "Gemini retornou {$httpCode}: " . substr($response->body(), 0, 500);
                if (!in_array($httpCode, [429, 500, 502, 503, 504], true)) {
                    throw new RuntimeException($errorMessage);
                }
                $header = $response->header('Retry-After');
                $retryAfter = $header !== '' ? (float) $header : null;
            } catch (ConnectionException $e) {
                $errorMessage = 'Falha de conexão com o Gemini: ' . $e->getMessage();
            }

            $lastError = $errorMessage;
            if ($attempt >= $maxAttempts) {
                break;
            }

            $backoff = min(8.0, $retryAfter ?? 3.0);
            Log::warning("[gemini] tentativa {$attempt}/{$maxAttempts} falhou ({$errorMessage}) -- tentando de novo em " . round($backoff, 1) . 's');
            sleep(max(1, (int) ceil($backoff)));
        }

        throw new RuntimeException("Gemini falhou: {$lastError}");
    }

    /**
     * Junta o texto de TODAS as partes da resposta, em ordem. Uma saída longa
     * costuma chegar quebrada em vários content.parts; ler só parts[0] devolvia
     * a resposta cortada. Partes thought=true (raciocínio) são descartadas.
     */
    private static function responseText(?array $parsed): string
    {
        $parts = $parsed['candidates'][0]['content']['parts'] ?? null;
        if (!is_array($parts)) {
            return '';
        }
        $text = '';
        foreach ($parts as $part) {
            if (!is_array($part) || !isset($part['text']) || !is_string($part['text'])) {
                continue;
            }
            if (!empty($part['thought'])) {
                continue;
            }
            $text .= $part['text'];
        }
        return $text;
    }

    private static function mimeForPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            default => 'audio/mpeg',
        };
    }

    public function transcribeChunk(string $path): string
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException("Não foi possível ler o arquivo de áudio: {$path}");
        }

        $response = $this->postWithRetry(self::TRANSCRIBE_MODEL, [
            'contents' => [[
                'parts' => [
                    ['text' => 'Transcreva o áudio a seguir literalmente, palavra por palavra, em português. Não resuma, não traduza, não adicione comentários ou marcações -- devolva só o texto transcrito puro.'],
                    ['inline_data' => ['mime_type' => self::mimeForPath($path), 'data' => base64_encode($data)]],
                ],
            ]],
            // thinkingBudget=0 desliga o "raciocínio" interno -- pra transcrição
            // literal só consome tempo/tokens à toa.
            'generationConfig' => ['temperature' => 0, 'thinkingConfig' => ['thinkingBudget' => 0]],
        ], 110);

        $parsed = $response['parsed'];
        $text = self::responseText($parsed);
        if (trim($text) === '') {
            $blockReason = $parsed['promptFeedback']['blockReason'] ?? null;
            $extra = $blockReason ? " (bloqueado: {$blockReason})" : '';
            throw new RuntimeException('Resposta do Gemini sem texto de transcrição' . $extra . ': ' . substr($response['raw'], 0, 500));
        }
        // Transcrição cortada não pode passar batido: o resumo sairia só da
        // primeira metade. Melhor falhar e deixar o cron tentar de novo.
        if (($parsed['candidates'][0]['finishReason'] ?? '') === 'MAX_TOKENS') {
            throw new RuntimeException('Transcrição cortada por atingir o limite de tokens do Gemini neste pedaço de áudio.');
        }
        return $text;
    }

    /**
     * Pedaços do mp3, em ordem -- um único elemento (o próprio arquivo) se ele
     * já couber. Recalculado a cada chamada (é barato, só parsing de frames)
     * porque os arquivos de um split anterior podem não sobreviver entre ticks.
     */
    public function splitForJob(string $mp3Path, string $chunkDir): array
    {
        if (filesize($mp3Path) <= self::MAX_CHUNK_BYTES) {
            return [$mp3Path];
        }
        return Mp3Splitter::split($mp3Path, $chunkDir, self::MAX_CHUNK_BYTES);
    }

    /**
     * Uma chamada de resumo+flashcards. Resposta cortada sobe como
     * GEMINI_OUTPUT_TRUNCATED; se o que veio ainda for aproveitável, volta em
     * $salvage (último recurso do pipeline).
     */
    public function summarize(string $prompt, int $maxOutputTokens, ?array &$salvage = null): array
    {
        $salvage = null;
        $response = $this->postWithRetry(self::SUMMARY_MODEL, [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => $maxOutputTokens,
                // Texto puro, não JSON: markdown dentro de string JSON obriga o
                // modelo a escapar \n e aspas -- é onde ele erra (ver PromptBuilder).
                'responseMimeType' => 'text/plain',
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ], 110);

        $parsed = $response['parsed'];
        $candidate = $parsed['candidates'][0] ?? null;
        $content = self::responseText($parsed);
        $finishReason = (string) ($candidate['finishReason'] ?? '');

        if (trim($content) === '') {
            if ($finishReason === 'MAX_TOKENS') {
                throw new RuntimeException('GEMINI_OUTPUT_TRUNCATED: resposta cortada por atingir o limite de tokens');
            }
            $blockReason = $parsed['promptFeedback']['blockReason'] ?? ($candidate['finishReason'] ?? null);
            $extra = $blockReason ? " (motivo: {$blockReason})" : '';
            throw new RuntimeException('Resposta do Gemini sem conteúdo' . $extra . ': ' . substr($response['raw'], 0, 500));
        }

        $result = ResponseParser::summary($content);
        $gotSomething = $result['summary_markdown'] !== '' || $result['flashcards'] !== [];

        if ($finishReason === 'MAX_TOKENS') {
            if ($gotSomething) {
                $salvage = $result;
            }
            throw new RuntimeException(
                'GEMINI_OUTPUT_TRUNCATED: resposta cortada no limite de tokens (' . strlen($content) . ' caracteres)'
            );
        }

        if (!$gotSomething) {
            $head = substr($content, 0, 300);
            $tail = strlen($content) > 600 ? ' [...] ' . substr($content, -300) : '';
            throw new RuntimeException(
                'Resposta do Gemini fora do formato esperado (finishReason=' . ($finishReason ?: 'desconhecido') .
                ', ' . strlen($content) . ' caracteres): ' . $head . $tail
            );
        }

        return $result;
    }

    /** Flashcards avulsos (sem resumo) -- "Gerar mais com IA" na edição da aula. */
    public function moreFlashcards(string $prompt, int $maxOutputTokens = 4000): array
    {
        $response = $this->postWithRetry(self::SUMMARY_MODEL, [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => $maxOutputTokens,
                'responseMimeType' => 'text/plain',
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ], 90);

        $parsed = $response['parsed'];
        $content = trim(self::responseText($parsed));

        if ($content === '') {
            $blockReason = $parsed['promptFeedback']['blockReason'] ?? ($parsed['candidates'][0]['finishReason'] ?? null);
            $extra = $blockReason ? " (motivo: {$blockReason})" : '';
            throw new RuntimeException('Resposta do Gemini sem conteúdo' . $extra . ': ' . substr($response['raw'], 0, 500));
        }

        return ResponseParser::flashcards(ResponseParser::stripCodeFence($content));
    }

    /**
     * Vale cair pro caminho em partes em vez de repetir a mesma chamada? Só se
     * o problema for de TAMANHO (contexto/saída grande demais).
     */
    public static function shouldFallbackToParts(string $message): bool
    {
        return (bool) preg_match('/GEMINI_OUTPUT_TRUNCATED|retornou 400|retornou 413|context.?length|too large|token count exceeds/i', $message);
    }

    /** Divide a transcrição em pedaços de até $targetChars, cortando em fim de frase/parágrafo. */
    public static function splitTranscript(string $transcript, int $targetChars): array
    {
        $length = mb_strlen($transcript);
        if ($length <= $targetChars) {
            return [$transcript];
        }

        $parts = [];
        $offset = 0;
        while ($offset < $length) {
            $end = min($offset + $targetChars, $length);
            if ($end < $length) {
                $window = mb_substr($transcript, $offset, $end - $offset);
                $lastBreak = null;
                foreach (["\n\n", '. ', '! ', '? ', "\n"] as $sep) {
                    $pos = mb_strrpos($window, $sep);
                    if ($pos !== false) {
                        $candidate = $pos + mb_strlen($sep);
                        if ($lastBreak === null || $candidate > $lastBreak) {
                            $lastBreak = $candidate;
                        }
                    }
                }
                // só usa o corte "bonito" se ele não deixar o pedaço pequeno demais
                if ($lastBreak !== null && $lastBreak > $targetChars * 0.5) {
                    $end = $offset + $lastBreak;
                }
            }
            $parts[] = mb_substr($transcript, $offset, $end - $offset);
            $offset = $end;
        }
        return $parts;
    }
}

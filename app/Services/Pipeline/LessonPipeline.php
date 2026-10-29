<?php

namespace App\Services\Pipeline;

use App\Models\Flashcard;
use App\Services\Gemini\GeminiClient;
use App\Services\Gemini\PromptBuilder;
use App\Services\Notifications\ClassReadyEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Pipeline de processamento de aulas: áudio -> transcrição -> resumo + flashcards.
 *
 * Cada chamada de tick() avança UM passo (um pedaço de áudio transcrito, ou
 * uma parte do resumo) e salva o progresso em classes.pipeline_state (JSON).
 *
 * Por que um passo por vez: a hospedagem tem um proxy com timeout de ~120s por
 * requisição que set_time_limit() não estende. Uma aula longa passa disso, e
 * quando o proxy mata a conexão o PHP não cai no catch/finally -- a aula
 * ficaria presa em "processando" pra sempre. Em passos pequenos, cada chamada
 * termina rápido e o progresso nunca se perde.
 *
 * Quem chama tick(): o cron (a cada 1 min), o "empurrão" logo após um envio, e
 * o próprio endpoint encadeando o próximo passo quando ainda sobra trabalho.
 */
class LessonPipeline
{
    // Margem de tokens de saída por chamada de resumo (gemini-2.5-flash aceita 65536).
    private const PART_MAX_TOKENS = 32000;

    // Abaixo disso não vale mais dividir uma parte que falhou por tamanho.
    private const MIN_PART_CHARS = 1000;

    public function __construct(private readonly GeminiClient $gemini)
    {
    }

    /**
     * TETO de flashcards pra um trecho de $chars caracteres -- ~1 card a cada
     * 2500 caracteres (2-3min de fala), entre 4 e 18. É limite, não meta.
     */
    public static function targetFlashcardCount(int $chars): int
    {
        return max(4, min(18, (int) round($chars / 2500)));
    }

    /**
     * Executa um passo. Devolve a resposta JSON do endpoint e se vale encadear
     * outro passo imediatamente.
     *
     * @return array{response: array, chain: bool}
     */
    public function tick(): array
    {
        // Evita duas execuções simultâneas (cron + empurrões podem se sobrepor).
        $lock = Cache::lock('lectta:process', 115);
        if (!$lock->get()) {
            return ['response' => ['ok' => true, 'skipped' => 'já tem um processamento em andamento'], 'chain' => false];
        }

        try {
            return $this->runStep();
        } finally {
            $lock->release();
        }
    }

    private function runStep(): array
    {
        // Uma aula "processing" há mais de 3h SEM NENHUM PROGRESSO tem algo
        // persistentemente errado -- desiste em vez de gastar cota à toa. Mede a
        // partir do último passo real (pipeline_updated_at), não do envio.
        DB::update(
            "UPDATE classes SET status = 'error', error_message = 'Processamento não terminou depois de várias tentativas (mais de 3h sem avançar) -- pode ser um problema persistente no Gemini. Tente reenviar.', pipeline_state = NULL, pipeline_updated_at = NULL, processed_at = NOW() " .
            "WHERE status = 'processing' AND COALESCE(pipeline_updated_at, created_at) < NOW() - INTERVAL 3 HOUR"
        );

        // Prioriza retomar uma aula já em andamento antes de começar outra.
        $job = DB::selectOne(
            'SELECT c.*, u.email AS owner_email, u.name AS owner_name FROM classes c ' .
            'JOIN users u ON u.id = c.user_id ' .
            "WHERE c.status IN ('pending', 'processing') " .
            "ORDER BY (c.status = 'processing') DESC, c.created_at ASC LIMIT 1"
        );

        if (!$job) {
            return ['response' => ['ok' => true, 'job' => null], 'chain' => false];
        }
        $job = (array) $job;

        if ($job['status'] !== 'processing') {
            DB::update("UPDATE classes SET status = 'processing', pipeline_updated_at = NOW() WHERE id = ?", [$job['id']]);
        }

        try {
            $result = $this->advance($job);
            return [
                'response' => ['ok' => true, 'job_id' => (int) $job['id'], 'status' => $result['status'], 'detail' => $result['detail']],
                'chain' => $result['status'] === 'processing',
            ];
        } catch (Throwable $e) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $message = $e->getMessage();
            // "Gemini falhou: ..." = erro transitório (429, 5xx, conexão) depois
            // de esgotar as tentativas rápidas. Deixa a aula em processing com o
            // pipeline_state intacto pro cron do próximo minuto tentar de novo o
            // mesmo passo. Não encadeia (só bateria no mesmo rate limit).
            if (str_starts_with($message, 'Gemini falhou:')) {
                Log::warning("[process] job {$job['id']} esbarrou num erro transitório do Gemini, cron tenta de novo: {$message}");
                return [
                    'response' => ['ok' => true, 'job_id' => (int) $job['id'], 'status' => 'processing', 'detail' => 'erro transitório no Gemini, tentando de novo em breve'],
                    'chain' => false,
                ];
            }

            DB::update(
                "UPDATE classes SET status = 'error', error_message = ?, pipeline_state = NULL, pipeline_updated_at = NULL, processed_at = NOW() WHERE id = ?",
                [$message, $job['id']]
            );
            return [
                'response' => ['ok' => false, 'job_id' => (int) $job['id'], 'status' => 'error', 'error' => $message],
                'chain' => false,
            ];
        }
    }

    /** Avança UM passo e grava o estado (ou fecha a aula). */
    private function advance(array $job): array
    {
        $state = json_decode((string) ($job['pipeline_state'] ?? ''), true);
        $isText = ($job['source_type'] ?? 'upload') === 'text';
        if (!is_array($state)) {
            $state = [
                'stage' => $isText ? 'summarizing' : 'transcribing',
                'chunk_index' => 0,
                'chunk_texts' => [],
                // null = ainda não decidiu se cabe numa chamada só.
                'pending_parts' => null,
                'parts_total' => 0,
                'parts_done' => 0,
                'card_target_total' => 0,
                'summary_pieces' => [],
                'flashcards' => [],
            ];
        }

        $audioPath = null;
        if (!$isText) {
            $audioPath = config('lectta.upload_dir_pending') . '/' . $job['audio_filename'];
            if (!is_file($audioPath)) {
                throw new RuntimeException("Arquivo de áudio não encontrado em uploads/pending: {$job['audio_filename']}");
            }
        }

        if ($state['stage'] === 'transcribing') {
            return $this->transcribeStep($job, $state, $audioPath);
        }

        return $this->summarizeStep($job, $state, $audioPath);
    }

    private function transcribeStep(array $job, array $state, string $audioPath): array
    {
        $chunkDir = config('lectta.work_dir') . '/split_job' . $job['id'];
        $chunks = $this->gemini->splitForJob($audioPath, $chunkDir);
        $chunksTotal = count($chunks);

        if ($state['chunk_index'] < $chunksTotal) {
            $state['chunk_texts'][] = $this->gemini->transcribeChunk($chunks[$state['chunk_index']]);
            $state['chunk_index']++;
        }

        if ($chunksTotal > 1) {
            foreach ($chunks as $chunkPath) {
                @unlink($chunkPath);
            }
            @rmdir($chunkDir);
        }

        if ($state['chunk_index'] < $chunksTotal) {
            $this->saveState($job, $state);
            return ['status' => 'processing', 'detail' => "transcrevendo pedaço {$state['chunk_index']}/{$chunksTotal}"];
        }

        // transcrição completa -- consolida e passa pro resumo no próximo passo
        $state['transcript'] = implode("\n", $state['chunk_texts']);
        unset($state['chunk_texts']);
        $state['stage'] = 'summarizing';
        $this->saveState($job, $state);
        return ['status' => 'processing', 'detail' => 'transcrição completa, resumo a seguir'];
    }

    private function summarizeStep(array $job, array $state, ?string $audioPath): array
    {
        $transcript = $state['transcript'] ?? (string) $job['transcript'];
        if (trim($transcript) === '') {
            throw new RuntimeException('Transcrição colada está vazia.');
        }

        if ($state['pending_parts'] === null) {
            $targetCards = self::targetFlashcardCount(mb_strlen($transcript));
            if (mb_strlen($transcript) <= GeminiClient::SUMMARY_CHUNK_CHARS) {
                try {
                    $prompt = PromptBuilder::summary($transcript, $job['title'], $job['subject'], $targetCards);
                    $result = $this->gemini->summarize($prompt, self::PART_MAX_TOKENS);
                    $cardCount = $this->finalize($job, $transcript, (string) ($result['summary_markdown'] ?? ''), $result['flashcards'] ?? [], $audioPath);
                    return ['status' => 'done', 'detail' => "resumo pronto, {$cardCount} flashcards"];
                } catch (RuntimeException $e) {
                    if (!GeminiClient::shouldFallbackToParts($e->getMessage())) {
                        throw $e;
                    }
                    Log::info('[process] resumo numa chamada só falhou (' . $e->getMessage() . ') -- gerando em partes menores');
                    // Divide ao meio DE VERDADE (dividir pelo limite devolveria
                    // uma parte só, idêntica, e repetiria a mesma falha).
                    $state['pending_parts'] = GeminiClient::splitTranscript($transcript, (int) ceil(mb_strlen($transcript) / 2));
                }
            } else {
                $state['pending_parts'] = GeminiClient::splitTranscript($transcript, GeminiClient::SUMMARY_CHUNK_CHARS);
            }
            $state['parts_total'] = count($state['pending_parts']);
            $state['card_target_total'] = $targetCards;
        }

        $partText = array_shift($state['pending_parts']);
        $partNumber = $state['parts_done'] + 1;
        $cardsForPart = max(2, (int) ceil($state['card_target_total'] / max(1, $state['parts_total'])));

        $salvage = null;
        try {
            $prompt = PromptBuilder::partialSummary($partText, $job['title'], $job['subject'], $partNumber, $state['parts_total'], $cardsForPart);
            $result = $this->gemini->summarize($prompt, self::PART_MAX_TOKENS, $salvage);
            $state = $this->appendSummaryPart($state, $result, $partNumber);
        } catch (RuntimeException $e) {
            if (!GeminiClient::shouldFallbackToParts($e->getMessage())) {
                throw $e;
            }
            if (mb_strlen($partText) <= self::MIN_PART_CHARS) {
                // Chegou no piso: aproveita o que veio da resposta cortada --
                // perder um pedaço do resumo é melhor que perder a aula inteira.
                if ($salvage === null) {
                    throw $e;
                }
                Log::info('[process] parte mínima ainda falhou (' . $e->getMessage() . ') -- aproveitando o trecho recuperado');
                $state = $this->appendSummaryPart($state, $salvage, $partNumber);
            } else {
                // Divide essa parte ao meio e devolve as metades pro início da fila.
                Log::info('[process] parte de resumo falhou (' . $e->getMessage() . ') -- dividindo ao meio e tentando de novo');
                $halves = GeminiClient::splitTranscript($partText, (int) ceil(mb_strlen($partText) / 2));
                array_unshift($state['pending_parts'], ...$halves);
                $state['parts_total'] += count($halves) - 1;
            }
        }

        if (!empty($state['pending_parts'])) {
            $this->saveState($job, $state);
            $remaining = count($state['pending_parts']);
            return ['status' => 'processing', 'detail' => "resumo: {$state['parts_done']}/{$state['parts_total']} partes prontas, {$remaining} restante(s)"];
        }

        $summaryMarkdown = implode("\n\n", $state['summary_pieces']);
        $cardCount = $this->finalize($job, $transcript, $summaryMarkdown, $state['flashcards'], $audioPath);
        return ['status' => 'done', 'detail' => "resumo pronto ({$state['parts_done']} partes), {$cardCount} flashcards"];
    }

    private function appendSummaryPart(array $state, array $result, int $partNumber): array
    {
        $piece = trim((string) ($result['summary_markdown'] ?? ''));
        if ($piece !== '') {
            // "#" (não "##") porque o resumo do modelo já usa "##" nos tópicos.
            $state['summary_pieces'][] = $state['parts_total'] > 1
                ? "# Parte {$partNumber} de {$state['parts_total']}\n\n{$piece}"
                : $piece;
        }
        foreach ($result['flashcards'] ?? [] as $card) {
            if (is_array($card) && !empty($card['question']) && !empty($card['answer'])) {
                $state['flashcards'][] = $card;
            }
        }
        $state['parts_done']++;
        return $state;
    }

    private function saveState(array $job, array $state): void
    {
        DB::update('UPDATE classes SET pipeline_state = ?, pipeline_updated_at = NOW() WHERE id = ?', [json_encode($state), $job['id']]);
    }

    /** Grava transcrição+resumo+flashcards, apaga o áudio e avisa o dono por e-mail. */
    private function finalize(array $job, string $transcript, string $summaryMarkdown, array $flashcards, ?string $audioPath): int
    {
        $cardCount = DB::transaction(function () use ($job, $transcript, $summaryMarkdown, $flashcards) {
            DB::update(
                "UPDATE classes SET status = 'done', transcript = ?, summary_markdown = ?, pipeline_state = NULL, pipeline_updated_at = NULL, processed_at = NOW() WHERE id = ?",
                [$transcript, $summaryMarkdown, $job['id']]
            );

            $count = 0;
            foreach ($flashcards as $card) {
                if (empty($card['question']) || empty($card['answer'])) {
                    continue;
                }
                Flashcard::createFresh((int) $job['id'], (int) $job['user_id'], $card['question'], $card['answer']);
                $count++;
            }
            return $count;
        });

        // Tudo já está no banco -- o áudio não serve mais pra nada.
        if ($audioPath !== null) {
            @unlink($audioPath);
        }

        ClassReadyEmail::send(
            $job['owner_email'],
            config('lectta.notify_from'),
            $job['title'],
            $job['subject'],
            $job['topic'] ?? null,
            $summaryMarkdown,
            $cardCount,
            route('home'),
            asset('img/logo-icon-1024.png')
        );

        return $cardCount;
    }
}

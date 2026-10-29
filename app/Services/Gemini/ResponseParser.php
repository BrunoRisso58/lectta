<?php

namespace App\Services\Gemini;

/**
 * Lê a resposta em texto puro com marcadores (<<<RESUMO>>> / <<<FLASHCARDS>>>,
 * ver PromptBuilder). Tolerante de propósito: uma resposta cortada no meio
 * ainda devolve tudo que já tinha vindo -- não existe "estrutura fechando" pra
 * quebrar, ao contrário de JSON.
 */
final class ResponseParser
{
    public static function stripCodeFence(string $text): string
    {
        // O modelo às vezes embrulha tudo numa cerca de código.
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```[a-zA-Z]*[ \t]*\n?/', '', $text);
            $text = preg_replace('/\n?```\s*$/', '', $text);
        }
        return $text;
    }

    /** @return array{summary_markdown: string, flashcards: array<int, array{question: string, answer: string}>} */
    public static function summary(string $text): array
    {
        $text = self::stripCodeFence(str_replace("\r\n", "\n", trim($text)));

        $pieces = preg_split('/^[ \t]*<{2,3}\s*FLASHCARDS\s*>{2,3}[ \t]*$/miu', $text, 2);
        $summary = $pieces[0] ?? '';
        $cardsBlock = $pieces[1] ?? '';

        $summary = preg_replace('/^[ \t]*<{2,3}\s*RESUMO\s*>{2,3}[ \t]*$/miu', '', $summary);

        return [
            'summary_markdown' => trim($summary),
            'flashcards' => self::flashcards($cardsBlock),
        ];
    }

    /**
     * Pares "P: ... / R: ..." em linhas. Aceita as variações que o modelo teima
     * em usar (bullet na frente, negrito, "P -" em vez de "P:"), e deixa uma
     * resposta continuar nas linhas seguintes até o próximo P:/R:.
     */
    public static function flashcards(string $block): array
    {
        $cards = [];
        $current = null;
        $field = null;

        $flush = function () use (&$cards, &$current): void {
            if ($current !== null && $current['question'] !== '' && $current['answer'] !== '') {
                $cards[] = $current;
            }
            $current = null;
        };

        foreach (explode("\n", $block) as $line) {
            $line = trim($line);

            if (preg_match('/^(?:[-*]\s*)?\*{0,2}\s*P\s*\*{0,2}\s*[:.\-–]\s*\*{0,2}\s*(.*)$/u', $line, $m)) {
                $flush();
                $current = ['question' => trim($m[1]), 'answer' => ''];
                $field = 'question';
                continue;
            }
            if ($current !== null && preg_match('/^(?:[-*]\s*)?\*{0,2}\s*R\s*\*{0,2}\s*[:.\-–]\s*\*{0,2}\s*(.*)$/u', $line, $m)) {
                $current['answer'] = trim($m[1]);
                $field = 'answer';
                continue;
            }
            // continuação da pergunta/resposta anterior
            if ($current !== null && $field !== null && $line !== '') {
                $current[$field] = trim($current[$field] . ' ' . $line);
            }
        }
        $flush();

        return $cards;
    }
}

<?php

namespace App\Services\Study;

/**
 * Algoritmo SM-2 (SuperMemo-2), o mesmo princípio usado pelo Anki para
 * repetição espaçada. Qualidade da resposta em escala 0-5:
 *   0 = Novamente (esqueceu)   3 = Difícil   4 = Bom   5 = Fácil
 */
final class Sm2
{
    /** @return array{ease_factor: float, interval_days: int, repetitions: int, due_date: string} */
    public static function next(float $easeFactor, int $intervalDays, int $repetitions, int $quality): array
    {
        if ($quality < 3) {
            $repetitions = 0;
            $intervalDays = 0;
        } else {
            if ($repetitions === 0) {
                $intervalDays = 1;
            } elseif ($repetitions === 1) {
                $intervalDays = 6;
            } else {
                $intervalDays = (int) round($intervalDays * $easeFactor);
            }
            $repetitions++;
            $easeFactor += 0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02);
            if ($easeFactor < 1.3) {
                $easeFactor = 1.3;
            }
        }

        return [
            'ease_factor' => $easeFactor,
            'interval_days' => $intervalDays,
            'repetitions' => $repetitions,
            'due_date' => date('Y-m-d', strtotime("+{$intervalDays} days")),
        ];
    }
}

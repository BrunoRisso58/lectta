<?php

namespace App\Services\Study;

use DateTime;
use Illuminate\Support\Facades\DB;

/**
 * Streak de revisões diárias (estilo Duolingo/Anki): dias consecutivos,
 * terminando hoje ou ontem, com pelo menos 1 revisão. Se o usuário ainda não
 * revisou hoje mas revisou ontem, o streak continua "vivo" até o fim do dia.
 * Tudo é derivado da tabela review_log (uma linha por avaliação de card).
 */
final class StreakService
{
    public function current(int $userId): int
    {
        $dates = DB::table('review_log')
            ->where('user_id', $userId)
            ->selectRaw('DISTINCT DATE(reviewed_at) AS d')
            ->orderByDesc('d')
            ->pluck('d')
            ->all();

        if (!$dates) {
            return 0;
        }

        $today = new DateTime('today');
        $mostRecent = new DateTime($dates[0]);
        $diffFromToday = (int) $today->diff($mostRecent)->format('%r%a');

        if ($diffFromToday < -1) {
            return 0;
        }

        $streak = 1;
        $cursor = $mostRecent;
        for ($i = 1; $i < count($dates); $i++) {
            $prevDay = (clone $cursor)->modify('-1 day');
            $d = new DateTime($dates[$i]);
            if ($d->format('Y-m-d') === $prevDay->format('Y-m-d')) {
                $streak++;
                $cursor = $prevDay;
            } else {
                break;
            }
        }

        return $streak;
    }

    /** Maior sequência já alcançada (histórico completo), não só a atual. */
    public function longest(int $userId): int
    {
        $dates = DB::table('review_log')
            ->where('user_id', $userId)
            ->selectRaw('DISTINCT DATE(reviewed_at) AS d')
            ->orderBy('d')
            ->pluck('d')
            ->all();

        if (!$dates) {
            return 0;
        }

        $longest = 1;
        $current = 1;
        for ($i = 1; $i < count($dates); $i++) {
            $prevDay = new DateTime($dates[$i - 1]);
            $day = new DateTime($dates[$i]);
            $diff = (int) $prevDay->diff($day)->format('%a');
            if ($diff === 1) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 1;
            }
        }

        return $longest;
    }

    /** Cards distintos revisados hoje (um card "Novamente" revisado 2x conta 1). */
    public function cardsReviewedToday(int $userId): int
    {
        return (int) DB::table('review_log')
            ->where('user_id', $userId)
            ->whereRaw('DATE(reviewed_at) = CURDATE()')
            ->count(DB::raw('DISTINCT flashcard_id'));
    }

    /** Total de dias distintos com pelo menos 1 revisão (histórico completo). */
    public function activeDays(int $userId): int
    {
        return (int) DB::table('review_log')
            ->where('user_id', $userId)
            ->count(DB::raw('DISTINCT DATE(reviewed_at)'));
    }

    /** Mapa data => nº de cards distintos revisados naquele dia, últimos $days dias. */
    public function activity(int $userId, int $days): array
    {
        $rows = DB::select(
            'SELECT DATE(reviewed_at) AS d, COUNT(DISTINCT flashcard_id) AS c FROM review_log ' .
            'WHERE user_id = ? AND reviewed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) ' .
            'GROUP BY DATE(reviewed_at)',
            [$userId, $days]
        );
        $activity = [];
        foreach ($rows as $row) {
            $activity[$row->d] = (int) $row->c;
        }
        return $activity;
    }
}

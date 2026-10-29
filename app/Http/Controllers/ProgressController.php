<?php

namespace App\Http\Controllers;

use App\Services\Study\StreakService;
use DateTime;
use Illuminate\Support\Facades\Auth;

/** Streak, estatísticas e heatmap de atividade (18 semanas, estilo GitHub). */
class ProgressController extends Controller
{
    private const MONTHS = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    public function index(StreakService $streaks)
    {
        $userId = Auth::id();

        // 18 semanas terminando hoje, com a grade alinhada a domingo.
        $today = new DateTime('today');
        $gridStart = (clone $today)->modify('-125 days');
        $gridStart->modify('-' . (int) $gridStart->format('w') . ' days');
        $spanDays = (int) $gridStart->diff($today)->format('%a');

        $activity = $streaks->activity($userId, $spanDays + 1);

        $weeks = [];
        $monthLabels = [];
        $lastMonth = null;
        $cursor = clone $gridStart;
        $weekIndex = 0;
        while ($cursor <= $today) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $dateStr = $cursor->format('Y-m-d');
                $count = $activity[$dateStr] ?? 0;
                $level = match (true) {
                    $count >= 11 => 4,
                    $count >= 6 => 3,
                    $count >= 3 => 2,
                    $count >= 1 => 1,
                    default => 0,
                };
                $week[] = [
                    'date' => $dateStr,
                    'count' => $count,
                    'level' => $level,
                    'is_future' => $cursor > $today,
                    'is_today' => $dateStr === $today->format('Y-m-d'),
                ];
                if ($d === 0) {
                    $m = (int) $cursor->format('n');
                    if ($m !== $lastMonth) {
                        $monthLabels[$weekIndex] = self::MONTHS[$m - 1];
                        $lastMonth = $m;
                    }
                }
                $cursor->modify('+1 day');
            }
            $weeks[] = $week;
            $weekIndex++;
        }

        return view('progress', [
            'streak' => $streaks->current($userId),
            'longestStreak' => $streaks->longest($userId),
            'activeDays' => $streaks->activeDays($userId),
            'reviewedToday' => $streaks->cardsReviewedToday($userId),
            'weeks' => $weeks,
            'monthLabels' => $monthLabels,
            'totalReviews' => array_sum($activity),
        ]);
    }
}

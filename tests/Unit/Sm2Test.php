<?php

namespace Tests\Unit;

use App\Services\Study\Sm2;
use PHPUnit\Framework\TestCase;

class Sm2Test extends TestCase
{
    public function test_first_correct_answer_schedules_for_tomorrow(): void
    {
        $next = Sm2::next(2.5, 0, 0, 4);

        $this->assertSame(1, $next['interval_days']);
        $this->assertSame(1, $next['repetitions']);
        $this->assertSame(date('Y-m-d', strtotime('+1 day')), $next['due_date']);
    }

    public function test_second_correct_answer_jumps_to_six_days(): void
    {
        $this->assertSame(6, Sm2::next(2.5, 1, 1, 4)['interval_days']);
    }

    public function test_later_intervals_grow_by_ease_factor(): void
    {
        $this->assertSame(15, Sm2::next(2.5, 6, 2, 4)['interval_days']);
    }

    public function test_forgetting_resets_the_card_to_today(): void
    {
        $next = Sm2::next(2.5, 15, 3, 0);

        $this->assertSame(0, $next['interval_days']);
        $this->assertSame(0, $next['repetitions']);
        $this->assertSame(date('Y-m-d'), $next['due_date']);
    }

    public function test_ease_factor_never_drops_below_1_3(): void
    {
        $this->assertSame(1.3, Sm2::next(1.3, 6, 2, 3)['ease_factor']);
    }
}

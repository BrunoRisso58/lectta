<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Flashcard com o estado do SM-2 (ease_factor, interval_days, repetitions,
 * due_date). Ver App\Services\Study\Sm2.
 */
class Flashcard extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'class_id');
    }

    /** Vencidos até hoje (a revisão espaçada "de sempre"). */
    public function scopeDue(Builder $query): Builder
    {
        return $query->whereRaw('due_date <= CURDATE()');
    }

    /** Cria um card novo, já vencendo hoje, com o estado inicial do SM-2. */
    public static function createFresh(int $lessonId, int $userId, string $question, string $answer): self
    {
        return self::create([
            'class_id' => $lessonId,
            'user_id' => $userId,
            'question' => $question,
            'answer' => $answer,
            'ease_factor' => 2.5,
            'interval_days' => 0,
            'repetitions' => 0,
            'due_date' => DB::raw('CURDATE()'),
        ]);
    }
}

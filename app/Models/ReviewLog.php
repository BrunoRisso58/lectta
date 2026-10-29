<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Uma avaliação de flashcard -- base do streak e do heatmap de atividade. */
class ReviewLog extends Model
{
    protected $table = 'review_log';

    public $timestamps = false;

    protected $guarded = ['id'];
}

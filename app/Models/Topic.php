<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Assunto dentro de uma matéria (opcional numa aula). */
class Topic extends Model
{
    // created_at é preenchido pelo DEFAULT do banco; não há updated_at.
    public $timestamps = false;

    protected $guarded = ['id'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}

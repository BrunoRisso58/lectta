<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Matéria (ex.: "Teologia Sistemática"). Única por nome dentro da conta. */
class Subject extends Model
{
    // created_at é preenchido pelo DEFAULT do banco; não há updated_at.
    public $timestamps = false;

    protected $guarded = ['id'];

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }
}

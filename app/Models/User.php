<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Conta de usuário. A senha fica em `password_hash` (não `password`) e não há
 * `updated_at` nem `remember_token` -- por isso os overrides abaixo.
 */
class User extends Authenticatable
{
    // created_at é preenchido pelo DEFAULT do banco; não há updated_at.
    public $timestamps = false;

    protected $fillable = ['name', 'email', 'password_hash'];

    protected $hidden = ['password_hash'];

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    // Sem coluna remember_token: "lembrar de mim" não existe no app.
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class);
    }
}

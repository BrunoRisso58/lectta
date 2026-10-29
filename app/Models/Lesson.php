<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma aula. A tabela se chama `classes`, mas "Class" é palavra reservada no
 * PHP, daí o model Lesson.
 *
 * Ciclo de vida (coluna status):
 *   queued_youtube -> downloading -> pending -> processing -> done | error
 * Upload de áudio e texto colado entram direto em `pending`.
 */
class Lesson extends Model
{
    public const STATUS_QUEUED_YOUTUBE = 'queued_youtube';
    public const STATUS_DOWNLOADING = 'downloading';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_ERROR = 'error';

    public const STATUS_LABELS = [
        'queued_youtube' => 'na fila do YouTube',
        'downloading' => 'baixando do YouTube…',
        'pending' => 'aguardando processamento',
        'processing' => 'processando…',
        'done' => 'pronta',
        'error' => 'erro no processamento',
    ];

    public const STATUS_ICONS = [
        'queued_youtube' => 'clock',
        'downloading' => 'download',
        'pending' => 'clock',
        'processing' => 'refresh',
        'done' => 'check',
        'error' => 'alert-circle',
    ];

    protected $table = 'classes';

    // created_at tem DEFAULT CURRENT_TIMESTAMP no banco; não há updated_at.
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['pipeline_state'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class, 'class_id');
    }

    public function pendingAudioPath(): ?string
    {
        return $this->audio_filename
            ? config('lectta.upload_dir_pending') . '/' . $this->audio_filename
            : null;
    }
}

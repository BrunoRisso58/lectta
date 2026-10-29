<?php

namespace App\Services\Pipeline;

use Illuminate\Support\Facades\Http;
use Throwable;

final class YoutubeLinks
{
    /** youtube.com (com/sem www/m) e youtu.be: watch, shorts, live, embed, link curto. */
    public static function isValid(string $url): bool
    {
        return (bool) preg_match('#^https?://(www\.|m\.)?(youtube\.com/(watch|shorts/|live/|embed/)|youtu\.be/)#i', trim($url));
    }

    /**
     * Título do vídeo via oEmbed público (sem API key). Se falhar (vídeo
     * privado/removido, timeout) volta null e quem chamou usa um título de
     * reserva -- o erro real só aparece quando o worker tentar baixar.
     */
    public static function fetchTitle(string $url): ?string
    {
        try {
            $response = Http::connectTimeout(3)->timeout(5)
                ->get('https://www.youtube.com/oembed', ['format' => 'json', 'url' => $url]);
        } catch (Throwable $e) {
            return null;
        }
        if ($response->status() !== 200) {
            return null;
        }
        $title = trim((string) ($response->json('title') ?? ''));
        return $title !== '' ? $title : null;
    }
}

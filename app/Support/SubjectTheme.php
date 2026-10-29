<?php

namespace App\Support;

/**
 * Cada matéria recebe uma cor estável (derivada do nome) pra ficar fácil de
 * reconhecer visualmente em qualquer lugar do app -- na lista de aulas, no
 * formulário de envio, nos flashcards da revisão e no e-mail.
 */
final class SubjectTheme
{
    public const PALETTES = 8;

    public static function paletteIndex(string $subject): int
    {
        return (crc32(mb_strtolower(trim($subject))) % self::PALETTES) + 1;
    }

    public static function badgeClass(string $subject): string
    {
        return 'subject-' . self::paletteIndex($subject);
    }

    public static function initials(string $subject): string
    {
        $words = preg_split('/\s+/', trim($subject));
        $words = array_filter($words, fn($w) => $w !== '');
        if (!$words) {
            return '?';
        }
        if (count($words) === 1) {
            return mb_strtoupper(mb_substr(reset($words), 0, 2));
        }
        $first = mb_substr(reset($words), 0, 1);
        $last = mb_substr(end($words), 0, 1);
        return mb_strtoupper($first . $last);
    }
}

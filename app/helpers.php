<?php

// Atalhos globais usados nas views Blade. A lógica mora nas classes de
// App\Support -- aqui ficam só nomes curtos pra não poluir os templates.

use App\Models\Flashcard;
use App\Support\Icons;
use App\Support\Markdown;
use App\Support\SubjectTheme;
use Illuminate\Support\Facades\Auth;

if (!function_exists('icon')) {
    function icon(string $name, int $size = 20, string $class = '', float $stroke = 2): string
    {
        return Icons::svg($name, $size, $class, $stroke);
    }
}

if (!function_exists('subject_badge_class')) {
    function subject_badge_class(?string $subject): string
    {
        return SubjectTheme::badgeClass((string) $subject);
    }
}

if (!function_exists('subject_initials')) {
    function subject_initials(?string $subject): string
    {
        return SubjectTheme::initials((string) $subject);
    }
}

if (!function_exists('markdown')) {
    function markdown(?string $md): string
    {
        return Markdown::render((string) $md);
    }
}

if (!function_exists('asset_v')) {
    /** URL de um estático com ?v=<filemtime>, pra invalidar cache a cada deploy. */
    function asset_v(string $path): string
    {
        $v = @filemtime(public_path($path)) ?: 1;
        return asset($path) . '?v=' . $v;
    }
}

if (!function_exists('nav_due_count')) {
    /**
     * Quantos flashcards estão vencidos hoje -- alimenta o badge da aba Revisar.
     * Cache estático porque sidebar e tab bar pedem o mesmo número na mesma
     * requisição.
     */
    function nav_due_count(): int
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $cached = Flashcard::where('user_id', Auth::id())->due()->count();
        } catch (Throwable $e) {
            $cached = 0;
        }
        return $cached;
    }
}

if (!function_exists('nav_items')) {
    /** Uma definição, dois formatos: tab bar no celular, sidebar a partir de 900px. */
    function nav_items(): array
    {
        return [
            ['key' => 'home',     'href' => route('home'),           'icon' => 'home',  'label' => 'Início'],
            ['key' => 'review',   'href' => route('review'),         'icon' => 'cards', 'label' => 'Revisar', 'badge' => true],
            ['key' => 'progress', 'href' => route('progress'),       'icon' => 'chart', 'label' => 'Progresso'],
            ['key' => 'subjects', 'href' => route('subjects.index'), 'icon' => 'book',  'label' => 'Matérias'],
        ];
    }
}

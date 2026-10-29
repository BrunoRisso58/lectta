<?php

namespace App\Support;

/**
 * Conjunto único de ícones do app (traçado, estilo Lucide, viewBox 24x24).
 *
 * A grade (24x24), o traçado e as pontas são iguais em todo lugar -- o que
 * muda é só o tamanho pedido na chamada. Nas views use o helper icon():
 *
 *   {!! icon('flame', 22) !!}
 */
final class Icons
{
    private const PATHS = [
    // Navegação
    'home'          => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
    'cards'         => '<rect x="3" y="5" width="14" height="10" rx="2"/><path d="M7 19h14v-9"/><path d="M11 9h6"/><path d="M11 12h4"/>',
    'chart'         => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 15l3.5-4 3 2.5L20 7"/>',
    'book'          => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
    'book-open'     => '<path d="M2 5.5C2 4.67 2.67 4 3.5 4H9a3 3 0 0 1 3 3v13a2.5 2.5 0 0 0-2.5-2.5H2V5.5Z"/><path d="M22 5.5c0-.83-.67-1.5-1.5-1.5H15a3 3 0 0 0-3 3v13a2.5 2.5 0 0 1 2.5-2.5H22V5.5Z"/>',
    'plus'          => '<path d="M12 5v14"/><path d="M5 12h14"/>',
    'sparkles'      => '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.937A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/>',
    'logout'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',

    // Setas e chevrons
    'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
    'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
    'arrow-up'      => '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>',
    'arrow-down'    => '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>',
    'x'             => '<path d="M18 6 6 18"/><path d="M6 6l12 12"/>',

    // Estado / status
    'check'         => '<path d="M20 6 9 17l-5-5"/>',
    'clock'         => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    'refresh'       => '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/>',
    'alert-circle'  => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/>',
    'alert-tri'     => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'download'      => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',

    // Ações
    'pencil'        => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
    'trash'         => '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
    'copy'          => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    'filter'        => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3Z"/>',

    // Fontes de aula
    'music'         => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
    'youtube'       => '<path d="M2.5 17a24 24 0 0 1 0-10 4 4 0 0 1 2.8-2.8 156 156 0 0 1 13.4 0A4 4 0 0 1 21.5 7a24 24 0 0 1 0 10 4 4 0 0 1-2.8 2.8 156 156 0 0 1-13.4 0A4 4 0 0 1 2.5 17Z"/><path d="m10 9 5 3-5 3Z"/>',
    'clipboard'     => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><rect x="9" y="1" width="6" height="4" rx="1"/><path d="M9 12h6"/><path d="M9 16h6"/>',

    // Estudo
    'flame'         => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
    'calendar'      => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
    'repeat'        => '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
    'target'        => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4"/>',
    'layers'        => '<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/>',

    // Notas do flashcard
    'again'         => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
    'hard'          => '<circle cx="12" cy="12" r="9"/><path d="M8 15s1.5-2 4-2 4 2 4 2"/><path d="M9 9h.01"/><path d="M15 9h.01"/>',
    'good'          => '<circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/>',
    'easy'          => '<circle cx="12" cy="12" r="9"/><path d="M8 13.5s1.5 3 4 3 4-3 4-3"/><path d="M8.5 8.5s1-1 2-1"/><path d="M15.5 8.5s-1-1-2-1"/>',

    // Tema
    'sun'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/><path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m4.9 19.1 1.4-1.4"/><path d="m17.7 6.3 1.4-1.4"/>',
    'moon'          => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
    'eye'           => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
    ];

    public static function path(string $name): string
    {
        return self::PATHS[$name] ?? '';
    }

    public static function svg(string $name, int $size = 20, string $class = '', float $stroke = 2): string
    {
        $path = self::path($name);
        if ($path === '') {
            return '';
        }
        $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"' : '';
        return '<svg' . $classAttr . ' width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24"'
            . ' fill="none" stroke="currentColor" stroke-width="' . $stroke . '"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
    }
}

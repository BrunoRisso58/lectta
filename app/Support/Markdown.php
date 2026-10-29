<?php

namespace App\Support;

/**
 * Renderizador de markdown bem simples -- só cobre o que o prompt de resumo
 * realmente gera (títulos, negrito, listas com aninhamento, parágrafos). Não é
 * CommonMark completo de propósito: não precisamos disso aqui.
 *
 * $styles mapeia tag => style inline ('h4', 'ul', 'ol', 'li', 'p', 'strong',
 * 'em'). A página usa style.css e passa vazio; o e-mail passa os estilos porque
 * cliente de e-mail ignora folha de estilo.
 */
final class Markdown
{
    public static function render(string $md, array $styles = []): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $md));
        $html = '';
        $depth = 0;     // quantas listas estão abertas
        $indents = [];  // indentação que abriu cada nível
        $tags = [];     // 'ul' ou 'ol' de cada nível

        // Fecha níveis até sobrar $to. Uma sublista foi aberta DENTRO do <li> do
        // item pai (é o aninhamento correto em HTML: <ul> irmão de <li> é
        // inválido), então fechar um nível aninhado também fecha esse <li>.
        $closeLists = function (int $to) use (&$html, &$depth, &$indents, &$tags): void {
            while ($depth > $to) {
                $tag = array_pop($tags);
                array_pop($indents);
                $depth--;
                $html .= "</{$tag}>\n" . ($depth > 0 ? "</li>\n" : '');
            }
        };

        // Abre um nível novo. A partir do segundo, entra dentro do <li> anterior.
        $openList = function (string $tag, int $indent) use (&$html, &$depth, &$indents, &$tags, $styles): void {
            if ($depth > 0 && str_ends_with($html, "</li>\n")) {
                $html = substr($html, 0, -strlen("</li>\n")) . "\n";
            }
            $html .= '<' . $tag . self::styleAttr($styles, $tag) . ">\n";
            $depth++;
            $indents[] = $indent;
            $tags[] = $tag;
        };

        foreach ($lines as $line) {
            // Tabs contam como indentação; o resto é medido em espaços à esquerda,
            // que é o que separa um sub-bullet de um bullet de primeiro nível.
            $line = str_replace("\t", '    ', rtrim($line));
            $indent = strlen($line) - strlen(ltrim($line, ' '));
            $trimmed = trim($line);

            if (preg_match('/^(#{1,4})\s+(.*)$/', $trimmed, $m)) {
                $closeLists(0);
                $level = strlen($m[1]) + 2; // # -> h3, ## -> h4, etc (mantém hierarquia visual modesta)
                $html .= "<h{$level}" . self::styleAttr($styles, "h{$level}") . '>'
                    . self::inline(htmlspecialchars($m[2]), $styles) . "</h{$level}>\n";
                continue;
            }

            // "- item" / "* item" viram <ul>; "1. item" / "1) item" viram <ol>.
            $isBullet = (bool) preg_match('/^[\-\*]\s+(.*)$/', $trimmed, $m);
            $isNumbered = !$isBullet && preg_match('/^\d{1,3}[.)]\s+(.*)$/', $trimmed, $m);

            if ($isBullet || $isNumbered) {
                $tag = $isBullet ? 'ul' : 'ol';
                // O nível vem da COMPARAÇÃO com as indentações já abertas, não de
                // uma conta fixa tipo "2 espaços = 1 nível": assim funciona com
                // recuo de 2 ou de 4.
                while ($depth > 0 && $indent < $indents[$depth - 1]) {
                    $closeLists($depth - 1);
                }
                if ($depth === 0 || $indent > $indents[$depth - 1]) {
                    $openList($tag, $indent);
                } elseif ($tags[$depth - 1] !== $tag) {
                    // Mesma indentação, mas trocou de bullet pra numerada (ou
                    // vice-versa): é outra lista, não a continuação desta.
                    $closeLists($depth - 1);
                    $openList($tag, $indent);
                }
                $html .= '<li' . self::styleAttr($styles, 'li') . '>'
                    . self::inline(htmlspecialchars($m[1]), $styles) . "</li>\n";
                continue;
            }

            // Linha indentada logo abaixo de um bullet é continuação dele.
            if ($depth > 0 && $indent > 0 && $trimmed !== '' && str_ends_with($html, "</li>\n")) {
                $html = substr($html, 0, -strlen("</li>\n"))
                    . ' ' . self::inline(htmlspecialchars($trimmed), $styles) . "</li>\n";
                continue;
            }

            $closeLists(0);

            if ($trimmed === '') {
                continue;
            }

            $html .= '<p' . self::styleAttr($styles, 'p') . '>'
                . self::inline(htmlspecialchars($trimmed), $styles) . "</p>\n";
        }

        $closeLists(0);
        return $html;
    }

    private static function inline(string $text, array $styles = []): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong' . self::styleAttr($styles, 'strong') . '>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em' . self::styleAttr($styles, 'em') . '>$1</em>', $text);
        return $text;
    }

    private static function styleAttr(array $styles, string $tag): string
    {
        return isset($styles[$tag]) ? ' style="' . $styles[$tag] . '"' : '';
    }
}

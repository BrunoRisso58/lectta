<?php

namespace Tests\Unit;

use App\Support\Markdown;
use PHPUnit\Framework\TestCase;

class MarkdownTest extends TestCase
{
    public function test_renders_headings_bold_and_nested_lists(): void
    {
        $html = Markdown::render("## Título\n- **forte** item\n    - sub\n1. passo");

        $this->assertStringContainsString('<h4>Título</h4>', $html);
        $this->assertStringContainsString('<strong>forte</strong>', $html);
        $this->assertMatchesRegularExpression('#<li><strong>forte</strong> item\s*<ul>\s*<li>sub</li>\s*</ul>\s*</li>#', $html);
        $this->assertStringContainsString('<ol>', $html);
    }

    public function test_escapes_html(): void
    {
        $this->assertStringContainsString('&lt;script&gt;', Markdown::render('<script>alert(1)</script>'));
    }

    public function test_inline_styles_for_email(): void
    {
        $this->assertSame("<p style=\"color:red\">x</p>\n", Markdown::render('x', ['p' => 'color:red']));
    }
}

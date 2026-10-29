<?php

namespace Tests\Unit;

use App\Services\Gemini\ResponseParser;
use PHPUnit\Framework\TestCase;

class ResponseParserTest extends TestCase
{
    public function test_splits_summary_and_flashcards(): void
    {
        $result = ResponseParser::summary("<<<RESUMO>>>\n## Tópico\n- item\n\n<<<FLASHCARDS>>>\nP: Pergunta 1\nR: Resposta 1\nP: Pergunta 2\nR: Resposta 2");

        $this->assertSame("## Tópico\n- item", $result['summary_markdown']);
        $this->assertCount(2, $result['flashcards']);
        $this->assertSame(['question' => 'Pergunta 2', 'answer' => 'Resposta 2'], $result['flashcards'][1]);
    }

    public function test_tolerates_code_fences_and_bold_markers(): void
    {
        $result = ResponseParser::summary("```\nResumo\n<<FLASHCARDS>>\n- **P:** O que é?\n**R:** Isto\n```");

        $this->assertSame('Resumo', $result['summary_markdown']);
        $this->assertSame([['question' => 'O que é?', 'answer' => 'Isto']], $result['flashcards']);
    }

    public function test_truncated_response_keeps_complete_cards_only(): void
    {
        $cards = ResponseParser::flashcards("P: completa\nR: sim\nP: cortada no meio");

        $this->assertSame([['question' => 'completa', 'answer' => 'sim']], $cards);
    }

    public function test_multiline_answers_are_joined(): void
    {
        $cards = ResponseParser::flashcards("P: pergunta\nR: começo\ncontinuação");

        $this->assertSame('começo continuação', $cards[0]['answer']);
    }
}

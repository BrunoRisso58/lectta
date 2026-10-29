<?php

namespace Tests\Unit;

use App\Services\Gemini\GeminiClient;
use App\Services\Pipeline\LessonPipeline;
use PHPUnit\Framework\TestCase;

class LessonPipelineTest extends TestCase
{
    public function test_flashcard_target_is_bounded(): void
    {
        $this->assertSame(4, LessonPipeline::targetFlashcardCount(100));
        $this->assertSame(8, LessonPipeline::targetFlashcardCount(20000));
        $this->assertSame(18, LessonPipeline::targetFlashcardCount(500000));
    }

    public function test_transcript_split_preserves_text_and_cuts_on_sentences(): void
    {
        $text = str_repeat('Uma frase completa. ', 500);
        $parts = GeminiClient::splitTranscript($text, 3000);

        $this->assertGreaterThan(1, count($parts));
        $this->assertSame($text, implode('', $parts));
        foreach (array_slice($parts, 0, -1) as $part) {
            $this->assertStringEndsWith('. ', $part);
            $this->assertLessThanOrEqual(3000, mb_strlen($part));
        }
    }

    public function test_only_size_errors_fall_back_to_parts(): void
    {
        $this->assertTrue(GeminiClient::shouldFallbackToParts('GEMINI_OUTPUT_TRUNCATED: cortada'));
        $this->assertTrue(GeminiClient::shouldFallbackToParts('Gemini retornou 400: token count exceeds'));
        $this->assertFalse(GeminiClient::shouldFallbackToParts('Gemini retornou 403: chave inválida'));
    }
}

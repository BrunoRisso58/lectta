<?php

namespace App\Http\Controllers;

use App\Models\Flashcard;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\Gemini\GeminiClient;
use App\Services\Gemini\PromptBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/** Edição de uma aula pronta: matéria/assunto, resumo, transcrição e flashcards. */
class LessonEditController extends Controller
{
    private function findDone(int $id): ?Lesson
    {
        return Lesson::where('id', $id)->where('user_id', Auth::id())->where('status', Lesson::STATUS_DONE)->first();
    }

    public function edit(int $lesson)
    {
        $class = $this->findDone($lesson);
        if (!$class) {
            return redirect()->route('home');
        }
        $userId = Auth::id();

        return view('lessons.edit', [
            'class' => $class,
            'flashcards' => Flashcard::where('class_id', $class->id)->where('user_id', $userId)->orderBy('id')->get(),
            'subjects' => Subject::where('user_id', $userId)->orderBy('name')->get(),
            'topicsBySubject' => LessonUploadController::topicsBySubject($userId),
        ]);
    }

    private function back(Lesson $class, string $key, string $message)
    {
        return redirect()->route('lessons.edit', $class->id)->with($key, $message);
    }

    public function updateContent(Request $request, int $lesson)
    {
        $class = $this->findDone($lesson) ?? abort(404);
        $summary = trim((string) $request->input('summary_markdown'));
        $transcript = trim((string) $request->input('transcript'));

        if ($summary === '') {
            return $this->back($class, 'error', 'O resumo não pode ficar vazio.');
        }
        if ($transcript === '') {
            return $this->back($class, 'error', 'A transcrição não pode ficar vazia.');
        }
        $class->update(['summary_markdown' => $summary, 'transcript' => $transcript]);
        return $this->back($class, 'success', 'Alterações salvas.');
    }

    public function updateSubject(Request $request, int $lesson)
    {
        $class = $this->findDone($lesson) ?? abort(404);
        $userId = Auth::id();

        $subject = Subject::where('id', $request->integer('subject_id'))->where('user_id', $userId)->first();
        if (!$subject) {
            return $this->back($class, 'error', 'Escolha uma matéria válida.');
        }

        // Assunto de OUTRA matéria não vale -- o <select> já impede na tela,
        // mas quem posta direto não pode furar a regra.
        $topic = $request->integer('topic_id')
            ? Topic::where('id', $request->integer('topic_id'))->where('subject_id', $subject->id)->where('user_id', $userId)->first()
            : null;

        // Nome e id convivem em classes (a home agrupa pelo NOME).
        $class->update([
            'subject' => $subject->name,
            'subject_id' => $subject->id,
            'topic' => $topic?->name,
            'topic_id' => $topic?->id,
        ]);

        return $this->back($class, 'success', $topic
            ? "Aula movida para {$subject->name} · {$topic->name}."
            : "Aula movida para {$subject->name}.");
    }

    public function storeFlashcard(Request $request, int $lesson)
    {
        $class = $this->findDone($lesson) ?? abort(404);
        $question = trim((string) $request->input('question'));
        $answer = trim((string) $request->input('answer'));
        if ($question === '' || $answer === '') {
            return $this->back($class, 'error', 'Preencha a pergunta e a resposta do flashcard.');
        }
        Flashcard::createFresh($class->id, Auth::id(), $question, $answer);
        return $this->back($class, 'success', 'Flashcard adicionado.');
    }

    public function updateFlashcard(Request $request, int $lesson, int $flashcard)
    {
        $class = $this->findDone($lesson) ?? abort(404);
        $question = trim((string) $request->input('question'));
        $answer = trim((string) $request->input('answer'));
        if ($question === '' || $answer === '') {
            return $this->back($class, 'error', 'Preencha a pergunta e a resposta do flashcard.');
        }
        Flashcard::where('id', $flashcard)->where('class_id', $class->id)->where('user_id', Auth::id())
            ->update(['question' => $question, 'answer' => $answer]);
        return $this->back($class, 'success', 'Flashcard atualizado.');
    }

    public function destroyFlashcard(int $lesson, int $flashcard)
    {
        $class = $this->findDone($lesson) ?? abort(404);
        Flashcard::where('id', $flashcard)->where('class_id', $class->id)->where('user_id', Auth::id())->delete();
        return $this->back($class, 'success', 'Flashcard removido.');
    }

    /** "Gerar mais com IA": novos cards a partir da transcrição salva, sem repetir os existentes. */
    public function generateFlashcards(Request $request, int $lesson, GeminiClient $gemini)
    {
        $class = $this->findDone($lesson) ?? abort(404);
        $userId = Auth::id();
        $count = max(1, min(10, (int) $request->input('count', 5)));

        if (trim((string) $class->transcript) === '') {
            return $this->back($class, 'error', 'Esta aula não tem transcrição salva -- não dá pra gerar novos flashcards a partir dela.');
        }

        $existingQuestions = Flashcard::where('class_id', $class->id)->where('user_id', $userId)
            ->orderBy('id')->pluck('question')->all();

        try {
            $prompt = PromptBuilder::moreFlashcards($class->transcript, $class->title, $class->subject, $existingQuestions, $count);
            $newCards = $gemini->moreFlashcards($prompt);
        } catch (RuntimeException $e) {
            return $this->back($class, 'error', 'Não foi possível gerar novos flashcards agora: ' . $e->getMessage());
        }

        // Segunda camada contra repetição: descarta na mão qualquer pergunta que
        // bata (ignorando maiúsculas/espaços) com uma existente ou desta leva.
        $seen = array_map(static fn(string $q): string => mb_strtolower(trim($q)), $existingQuestions);
        $added = 0;
        foreach (array_slice($newCards, 0, $count) as $card) {
            $question = trim($card['question'] ?? '');
            $answer = trim($card['answer'] ?? '');
            if ($question === '' || $answer === '') {
                continue;
            }
            $key = mb_strtolower($question);
            if (in_array($key, $seen, true)) {
                continue;
            }
            Flashcard::createFresh($class->id, $userId, $question, $answer);
            $seen[] = $key;
            $added++;
        }

        return $this->back($class, 'success', $added > 0
            ? "{$added} novo(s) flashcard(s) gerado(s) com IA."
            : 'A IA não encontrou nenhum ponto novo pra virar flashcard nesta aula.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Flashcard;
use App\Models\Lesson;
use App\Services\Pipeline\ProcessingTrigger;
use App\Services\Study\StreakService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Tela inicial: saudação, revisão do dia, streak e aulas agrupadas por matéria/assunto. */
class DashboardController extends Controller
{
    public function index(StreakService $streaks)
    {
        $userId = Auth::id();

        $classes = Lesson::where('user_id', $userId)->orderByDesc('created_at')->get();
        $dueCount = Flashcard::where('user_id', $userId)->due()->count();

        $doneCount = $classes->where('status', Lesson::STATUS_DONE)->count();
        $busyCount = $classes->whereNotIn('status', [Lesson::STATUS_DONE, Lesson::STATUS_ERROR])->count();

        $hour = (int) now()->format('G');
        $salutation = $hour < 5 ? 'Boa madrugada' : ($hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite'));

        return view('dashboard', [
            'classes' => $classes,
            'grouped' => $this->groupBySubjectAndTopic($classes->all()),
            'dueCount' => $dueCount,
            'streak' => $streaks->current($userId),
            'reviewedToday' => $streaks->cardsReviewedToday($userId),
            'doneCount' => $doneCount,
            'busyCount' => $busyCount,
            'salutation' => $salutation,
        ]);
    }

    /**
     * Agrupa por matéria e, dentro dela, por assunto. "Sem assunto" (chave '')
     * vai sempre pro fim; o resto em ordem alfabética.
     */
    private function groupBySubjectAndTopic(array $classes): array
    {
        $grouped = [];
        foreach ($classes as $c) {
            $subjectName = ($c->subject ?? '') !== '' ? $c->subject : 'Geral';
            $topicName = $c->topic ?: null;
            $topicKey = $topicName ?: '';

            $grouped[$subjectName] ??= ['count' => 0, 'topics' => [], 'subject_id' => (int) $c->subject_id];
            $grouped[$subjectName]['topics'][$topicKey] ??= ['name' => $topicName, 'topic_id' => (int) $c->topic_id, 'classes' => []];
            $grouped[$subjectName]['topics'][$topicKey]['classes'][] = $c;
            $grouped[$subjectName]['count']++;
        }

        ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($grouped as &$data) {
            uksort($data['topics'], function ($a, $b) {
                if ($a === $b) return 0;
                if ($a === '') return 1;
                if ($b === '') return -1;
                return strcasecmp($a, $b);
            });
        }
        unset($data);

        return $grouped;
    }

    public function destroy(int $lesson)
    {
        $row = Lesson::where('id', $lesson)->where('user_id', Auth::id())->first();
        if ($row) {
            $audioPath = $row->pendingAudioPath();
            $row->delete(); // flashcards e review_log vão junto (ON DELETE CASCADE)
            if ($audioPath && is_file($audioPath)) {
                @unlink($audioPath);
            }
        }
        return redirect()->route('home');
    }

    /**
     * Manda uma aula com erro de volta pra fila. Se o áudio ainda existe (ou é
     * texto colado), só reprocessa; se não existe (download do YouTube que
     * falhou antes de gerar o mp3), volta pra fila do worker de YouTube.
     */
    public function retry(int $lesson)
    {
        $row = Lesson::where('id', $lesson)->where('user_id', Auth::id())
            ->where('status', Lesson::STATUS_ERROR)->first();

        if ($row) {
            $audioPath = $row->pendingAudioPath();
            $isPastedText = ($row->source_type ?? 'upload') === 'text' && trim((string) $row->transcript) !== '';

            if (($audioPath && is_file($audioPath)) || $isPastedText) {
                $row->update(['status' => Lesson::STATUS_PENDING, 'error_message' => null]);
                ProcessingTrigger::fire();
            } elseif (($row->source_type ?? 'upload') === 'youtube' && $row->youtube_url) {
                $row->update(['status' => Lesson::STATUS_QUEUED_YOUTUBE, 'error_message' => null]);
            }
        }

        return redirect()->route('home');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Matérias e assuntos + "radar de domínio".
 *
 * Domínio de um card: repetitions >= 3 e ease_factor > 2.2 (dominado);
 * ease_factor < 2.0 (difícil); o resto está em progresso.
 */
class SubjectController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $subjects = DB::select(
            'SELECT s.*, ' .
            '(SELECT COUNT(*) FROM classes c WHERE c.subject_id = s.id AND c.user_id = s.user_id) AS class_count, ' .
            '(SELECT COUNT(*) FROM flashcards f JOIN classes c ON c.id = f.class_id WHERE c.subject_id = s.id AND f.user_id = s.user_id) AS card_total, ' .
            '(SELECT COUNT(*) FROM flashcards f JOIN classes c ON c.id = f.class_id WHERE c.subject_id = s.id AND f.user_id = s.user_id AND f.repetitions >= 3 AND f.ease_factor > 2.2) AS card_mastered, ' .
            '(SELECT COUNT(*) FROM flashcards f JOIN classes c ON c.id = f.class_id WHERE c.subject_id = s.id AND f.user_id = s.user_id AND f.ease_factor < 2.0) AS card_hard ' .
            'FROM subjects s WHERE s.user_id = ? ORDER BY s.name',
            [$userId]
        );

        $totalCards = 0;
        $totalMastered = 0;
        $totalHard = 0;
        $weakestName = null;
        $weakestPct = null;
        foreach ($subjects as $s) {
            $t = (int) $s->card_total;
            $totalCards += $t;
            $totalMastered += (int) $s->card_mastered;
            $totalHard += (int) $s->card_hard;
            if ($t > 0) {
                $pct = round((int) $s->card_mastered / $t * 100);
                if ($weakestPct === null || $pct < $weakestPct) {
                    $weakestPct = $pct;
                    $weakestName = $s->name;
                }
            }
        }

        $hardCards = DB::select(
            'SELECT f.question, f.ease_factor, f.repetitions, s.name AS subject_name ' .
            'FROM flashcards f ' .
            'JOIN classes c ON c.id = f.class_id ' .
            'LEFT JOIN subjects s ON s.id = c.subject_id ' .
            'WHERE f.user_id = ? AND f.ease_factor < 2.0 ' .
            'ORDER BY f.ease_factor ASC, f.repetitions ASC LIMIT 10',
            [$userId]
        );

        $topicsBySubject = [];
        $topics = DB::select(
            'SELECT t.*, (SELECT COUNT(*) FROM classes c WHERE c.topic_id = t.id AND c.user_id = t.user_id) AS class_count ' .
            'FROM topics t WHERE t.user_id = ? ORDER BY t.name',
            [$userId]
        );
        foreach ($topics as $t) {
            $topicsBySubject[(int) $t->subject_id][] = $t;
        }

        return view('subjects.index', [
            'subjects' => $subjects,
            'hardCards' => $hardCards,
            'topicsBySubject' => $topicsBySubject,
            'totalCardsAll' => $totalCards,
            'totalMasteredAll' => $totalMastered,
            'totalHardAll' => $totalHard,
            'overallMasteredPct' => $totalCards ? round($totalMastered / $totalCards * 100) : 0,
            'weakestSubjectName' => $weakestName,
            'weakestSubjectPct' => $weakestPct,
        ]);
    }

    public function store(Request $request)
    {
        $name = trim((string) $request->input('name'));
        if ($name !== '') {
            Subject::insertOrIgnore(['user_id' => Auth::id(), 'name' => $name]);
        }
        return redirect()->route('subjects.index');
    }

    public function storeTopic(Request $request, int $subject)
    {
        $name = trim((string) $request->input('name'));
        $owned = Subject::where('id', $subject)->where('user_id', Auth::id())->exists();
        if ($owned && $name !== '') {
            Topic::insertOrIgnore(['user_id' => Auth::id(), 'subject_id' => $subject, 'name' => $name]);
        }
        return redirect()->route('subjects.index');
    }

    public function destroy(int $subject)
    {
        if (Lesson::where('subject_id', $subject)->where('user_id', Auth::id())->exists()) {
            return redirect()->route('subjects.index')->with('error', 'Essa matéria tem aulas vinculadas e não pode ser removida.');
        }
        Subject::where('id', $subject)->where('user_id', Auth::id())->delete();
        return redirect()->route('subjects.index');
    }

    public function destroyTopic(int $topic)
    {
        if (Lesson::where('topic_id', $topic)->where('user_id', Auth::id())->exists()) {
            return redirect()->route('subjects.index')->with('error', 'Esse assunto tem aulas vinculadas e não pode ser removido.');
        }
        Topic::where('id', $topic)->where('user_id', Auth::id())->delete();
        return redirect()->route('subjects.index');
    }
}

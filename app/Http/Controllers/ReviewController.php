<?php

namespace App\Http\Controllers;

use App\Models\Flashcard;
use App\Models\Lesson;
use App\Models\ReviewLog;
use App\Services\Study\Sm2;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Revisão espaçada. Dois modos:
 *   - normal: só cards vencidos (due_date <= hoje)
 *   - prova (cram=1): todos os cards do escopo escolhido, ignorando a data
 * Escopo: tudo, uma matéria, um assunto ou uma aula.
 */
class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();

        // Matérias e assuntos já com "pra revisar hoje" e "no total" -- alimenta
        // os <select> e o rótulo ao vivo do botão de aplicar filtro.
        $subjects = DB::select(
            'SELECT s.id, s.name, ' .
            '(SELECT COUNT(*) FROM flashcards f JOIN classes c ON c.id = f.class_id WHERE c.subject_id = s.id AND f.user_id = s.user_id AND f.due_date <= CURDATE()) AS due_count, ' .
            '(SELECT COUNT(*) FROM flashcards f JOIN classes c ON c.id = f.class_id WHERE c.subject_id = s.id AND f.user_id = s.user_id) AS total_count ' .
            'FROM subjects s WHERE s.user_id = ? ORDER BY s.name',
            [$userId]
        );
        $topics = DB::select(
            'SELECT t.id, t.subject_id, t.name, ' .
            '(SELECT COUNT(*) FROM flashcards f JOIN classes c ON c.id = f.class_id WHERE c.topic_id = t.id AND f.user_id = t.user_id AND f.due_date <= CURDATE()) AS due_count, ' .
            '(SELECT COUNT(*) FROM flashcards f JOIN classes c ON c.id = f.class_id WHERE c.topic_id = t.id AND f.user_id = t.user_id) AS total_count ' .
            'FROM topics t WHERE t.user_id = ? ORDER BY t.name',
            [$userId]
        );

        $topicsBySubject = [];
        foreach ($topics as $t) {
            $topicsBySubject[(int) $t->subject_id][] = [
                'id' => (int) $t->id,
                'name' => $t->name,
                'due' => (int) $t->due_count,
                'total' => (int) $t->total_count,
            ];
        }

        $subjectId = max(0, $request->integer('subject_id'));
        $topicId = max(0, $request->integer('topic_id'));
        // "Revisar esta aula" na edição -- escopo mais estreito que existe.
        $classId = max(0, $request->integer('class_id'));
        // Modo prova exige algum escopo -- sem ele a flag é ignorada.
        $cram = $request->query('cram') === '1' && ($subjectId > 0 || $classId > 0);
        // ?setup=1 pede a tela de escolher o que revisar em vez do player.
        $setup = $request->query('setup') === '1';

        $selectedSubjectName = collect($subjects)->firstWhere('id', $subjectId)?->name;
        $selectedTopicName = $topicId ? collect($topics)->firstWhere('id', $topicId)?->name : null;
        $selectedClassName = $classId
            ? Lesson::where('id', $classId)->where('user_id', $userId)->value('title')
            : null;

        $query = DB::table('flashcards as f')
            ->join('classes as c', 'c.id', '=', 'f.class_id')
            ->where('f.user_id', $userId)
            ->select(
                'f.id', 'f.question', 'f.answer', 'f.repetitions', 'f.interval_days', 'f.due_date', 'f.last_reviewed_at',
                'c.title as class_title', 'c.subject as class_subject', 'c.topic as class_topic'
            );
        if (!$cram) {
            $query->whereRaw('f.due_date <= CURDATE()');
        }
        if ($subjectId) {
            $query->where('c.subject_id', $subjectId);
        }
        if ($topicId) {
            $query->where('c.topic_id', $topicId);
        }
        if ($classId) {
            $query->where('c.id', $classId);
        }
        // No modo prova, cards menos praticados vêm primeiro depois dos vencidos.
        $query->orderBy('f.due_date');
        if ($cram) {
            $query->orderBy('f.repetitions')->orderBy('f.id');
        }

        $today = new DateTime('today');
        $cards = $query->get()->map(function ($card) use ($today) {
            $card = (array) $card;
            $card['id'] = (int) $card['id'];
            $card['repetitions'] = (int) $card['repetitions'];
            $card['interval_days'] = (int) $card['interval_days'];
            $card['subject_class'] = subject_badge_class($card['class_subject']);
            $card['last_reviewed_fmt'] = $card['last_reviewed_at'] ? (new DateTime($card['last_reviewed_at']))->format('d/m/Y') : null;
            $dueDate = new DateTime($card['due_date']);
            $card['overdue_days'] = $today > $dueDate ? (int) $today->diff($dueDate)->format('%a') : 0;
            // Só existe em modo prova: card puxado antes da data de revisão dele.
            $card['not_due_yet'] = $today < $dueDate;
            return $card;
        })->all();

        $totalDueAll = array_sum(array_map(fn($s) => (int) $s->due_count, $subjects));
        $totalCardsAll = array_sum(array_map(fn($s) => (int) $s->total_count, $subjects));

        return view('review', [
            'subjects' => $subjects,
            'topicsBySubject' => $topicsBySubject,
            'cards' => $cards,
            'subjectId' => $subjectId,
            'topicId' => $topicId,
            'classId' => $classId,
            'cram' => $cram,
            'setup' => $setup,
            'selectedSubjectName' => $selectedSubjectName,
            'selectedTopicName' => $selectedTopicName,
            'selectedClassName' => $selectedClassName,
            'totalDueAll' => $totalDueAll,
            'totalCardsAll' => $totalCardsAll,
            // Com cards na fila abre direto no player em tela cheia.
            'showPlayer' => !$setup && count($cards) > 0,
        ]);
    }

    /** Nota de um card (0, 3, 4, 5): aplica o SM-2 e registra no review_log. */
    public function grade(Request $request)
    {
        $cardId = (int) $request->input('card_id', 0);
        $quality = (int) $request->input('quality', -1);
        $userId = Auth::id();

        if ($cardId <= 0 || $quality < 0 || $quality > 5) {
            return response()->json(['error' => 'parâmetros inválidos'], 400);
        }

        $card = Flashcard::where('id', $cardId)->where('user_id', $userId)->first();
        if (!$card) {
            return response()->json(['error' => 'card não encontrado'], 404);
        }

        $next = Sm2::next((float) $card->ease_factor, (int) $card->interval_days, (int) $card->repetitions, $quality);

        DB::transaction(function () use ($card, $next, $userId, $quality) {
            $card->update([
                'ease_factor' => $next['ease_factor'],
                'interval_days' => $next['interval_days'],
                'repetitions' => $next['repetitions'],
                'due_date' => $next['due_date'],
                'last_reviewed_at' => DB::raw('NOW()'),
            ]);
            ReviewLog::create(['user_id' => $userId, 'flashcard_id' => $card->id, 'quality' => $quality]);
        });

        return response()->json(['ok' => true, 'next_due_date' => $next['due_date']]);
    }
}

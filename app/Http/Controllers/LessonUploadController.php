<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\Pipeline\ProcessingTrigger;
use App\Services\Pipeline\YoutubeLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Envio de aula por três caminhos que convergem no mesmo pipeline:
 *   - file:    mp3 salvo em uploads/pending  -> status pending
 *   - youtube: um link por linha             -> status queued_youtube (worker baixa)
 *   - text:    transcrição colada            -> status pending, pula a transcrição
 */
class LessonUploadController extends Controller
{
    public function create(Request $request)
    {
        $userId = Auth::id();
        $subjects = Subject::where('user_id', $userId)->orderBy('name')->get();

        $selectedSubjectId = (int) $request->old('subject_id', 0);
        if (!$selectedSubjectId && $subjects->isNotEmpty()) {
            $selectedSubjectId = (int) $subjects->first()->id;
        }

        return view('lessons.upload', [
            'subjects' => $subjects,
            'selectedSubjectId' => $selectedSubjectId,
            'selectedTopicId' => (int) $request->old('topic_id', 0),
            'mode' => $request->old('mode', 'file'),
            'topicsBySubject' => self::topicsBySubject($userId),
        ]);
    }

    public function store(Request $request)
    {
        $userId = Auth::id();
        $mode = in_array($request->input('mode'), ['file', 'youtube', 'text'], true) ? $request->input('mode') : 'file';
        $title = trim((string) $request->input('title'));

        $subject = Subject::where('id', (int) $request->input('subject_id'))->where('user_id', $userId)->first();
        $topic = $subject && $request->integer('topic_id')
            ? Topic::where('id', $request->integer('topic_id'))->where('subject_id', $subject->id)->where('user_id', $userId)->first()
            : null;

        $fail = fn(string $message) => redirect()->route('lessons.create')
            ->withInput($request->except('audio'))
            ->with('error', $message);

        if (!$subject) {
            return $fail('Selecione uma matéria.');
        }

        $base = [
            'user_id' => $userId,
            'subject' => $subject->name,
            'subject_id' => $subject->id,
            'topic' => $topic?->name,
            'topic_id' => $topic?->id,
        ];

        if ($mode === 'youtube') {
            $urls = [];
            foreach (preg_split('/\r\n|\r|\n/', (string) $request->input('youtube_urls')) as $line) {
                $line = trim($line);
                if ($line !== '' && YoutubeLinks::isValid($line) && !in_array($line, $urls, true)) {
                    $urls[] = $line;
                }
            }
            if (!$urls) {
                return $fail('Cole pelo menos um link válido do YouTube (um por linha).');
            }

            foreach ($urls as $url) {
                Lesson::create($base + [
                    'title' => YoutubeLinks::fetchTitle($url) ?? ($title !== '' ? $title : 'Aula do YouTube'),
                    'status' => Lesson::STATUS_QUEUED_YOUTUBE,
                    'source_type' => 'youtube',
                    'youtube_url' => $url,
                ]);
            }
            $created = count($urls);
            $success = $created === 1
                ? '1 aula do YouTube na fila! O áudio é baixado, transcrito e resumido automaticamente — você recebe um e-mail quando estiver pronta.'
                : "{$created} aulas do YouTube na fila! O áudio de cada uma é baixado, transcrito e resumido automaticamente — você recebe um e-mail conforme forem ficando prontas.";
        } elseif ($mode === 'text') {
            $transcript = trim((string) $request->input('transcript_text'));
            if ($title === '') {
                return $fail('Dê um título para a aula.');
            }
            if ($transcript === '') {
                return $fail('Cole o texto da transcrição.');
            }
            Lesson::create($base + [
                'title' => $title,
                'status' => Lesson::STATUS_PENDING,
                'source_type' => 'text',
                'transcript' => $transcript,
            ]);
            ProcessingTrigger::fire();
            $success = 'Transcrição enviada! O resumo e os flashcards são gerados automaticamente — você recebe um e-mail quando estiver pronta.';
        } else {
            $file = $request->file('audio');
            if (!$file || !$file->isValid()) {
                $code = $file ? $file->getError() : ($_FILES['audio']['error'] ?? 'desconhecido');
                return $fail('Falha no upload do arquivo (código ' . $code . ').');
            }
            if (strtolower($file->getClientOriginalExtension()) !== 'mp3') {
                return $fail('Envie um arquivo .mp3.');
            }
            if ($title === '') {
                return $fail('Dê um título para a aula.');
            }

            $dir = config('lectta.upload_dir_pending');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file->getClientOriginalName());
            try {
                $file->move($dir, $safeName);
            } catch (\Throwable $e) {
                return $fail('Não foi possível salvar o arquivo no servidor.');
            }

            Lesson::create($base + [
                'title' => $title,
                'status' => Lesson::STATUS_PENDING,
                'audio_filename' => $safeName,
            ]);
            ProcessingTrigger::fire();
            $success = 'Aula enviada! Ela vai ser transcrita e resumida automaticamente — você recebe um e-mail quando estiver pronta.';
        }

        return redirect()->route('lessons.create')
            ->withInput($request->only('subject_id', 'topic_id', 'mode'))
            ->with('success', $success);
    }

    /** {subject_id: [{id, name}, ...]} pros <select> encadeados de matéria/assunto. */
    public static function topicsBySubject(int $userId): array
    {
        $map = [];
        foreach (Topic::where('user_id', $userId)->orderBy('name')->get() as $t) {
            $map[(int) $t->subject_id][] = ['id' => (int) $t->id, 'name' => $t->name];
        }
        return $map;
    }
}

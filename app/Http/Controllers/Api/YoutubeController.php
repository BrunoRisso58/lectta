<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\Pipeline\ProcessingTrigger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contrato com o worker Python (worker/youtube_worker.py), que roda via cron
 * no SSH porque o PHP web não tem yt-dlp/ffmpeg:
 *   1. GET  api/youtube/jobs    -> pega o próximo link e marca "downloading"
 *   2. POST api/youtube/ingest  -> entrega o mp3 (ou o erro); a aula volta a
 *      "pending" e segue o pipeline normal, igual a um upload manual.
 */
class YoutubeController extends Controller
{
    public function nextJob()
    {
        // Job "downloading" há mais de 20min = worker caiu no meio; devolve pra fila.
        DB::update(
            "UPDATE classes SET status = 'queued_youtube' " .
            "WHERE status = 'downloading' AND created_at < NOW() - INTERVAL 20 MINUTE"
        );

        $job = Lesson::where('status', Lesson::STATUS_QUEUED_YOUTUBE)
            ->orderBy('created_at')
            ->first(['id', 'user_id', 'title', 'youtube_url']);

        if (!$job) {
            return response()->json(['job' => null]);
        }

        // UPDATE condicional evita corrida entre duas execuções do worker.
        $claimed = Lesson::where('id', $job->id)
            ->where('status', Lesson::STATUS_QUEUED_YOUTUBE)
            ->update(['status' => Lesson::STATUS_DOWNLOADING]);

        if ($claimed === 0) {
            return response()->json(['job' => null]);
        }

        return response()->json([
            'job' => [
                'id' => (int) $job->id,
                'title' => $job->title,
                'youtube_url' => $job->youtube_url,
            ],
        ]);
    }

    public function ingest(Request $request)
    {
        $classId = (int) $request->input('id', 0);
        if (!$classId) {
            return response()->json(['error' => 'id é obrigatório'], 400);
        }

        $class = Lesson::where('id', $classId)->where('source_type', 'youtube')->first();
        if (!$class) {
            return response()->json(['error' => 'aula não encontrada'], 404);
        }

        // O worker não conseguiu baixar/converter (vídeo privado, removido, etc).
        if ($request->filled('error')) {
            $class->update([
                'status' => Lesson::STATUS_ERROR,
                'error_message' => (string) $request->input('error'),
                'processed_at' => DB::raw('NOW()'),
            ]);
            return response()->json(['ok' => true, 'status' => 'error']);
        }

        $file = $request->file('audio');
        if (!$file || !$file->isValid()) {
            $code = $file ? $file->getError() : 'desconhecido';
            return response()->json(['error' => 'upload do áudio falhou (código ' . $code . ')'], 400);
        }

        $dir = config('lectta.upload_dir_pending');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $safeName = time() . '_yt' . $classId . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file->getClientOriginalName());

        try {
            $file->move($dir, $safeName);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'não foi possível salvar o arquivo no servidor'], 500);
        }

        $class->update(['audio_filename' => $safeName, 'status' => Lesson::STATUS_PENDING]);

        ProcessingTrigger::fire();

        return response()->json(['ok' => true, 'status' => 'pending']);
    }
}

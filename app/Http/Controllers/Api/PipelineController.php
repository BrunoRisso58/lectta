<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pipeline\LessonPipeline;
use App\Services\Pipeline\ProcessingTrigger;

/**
 * GET api/process?token=... -- avança um passo do pipeline (ver LessonPipeline).
 * Chamado pelo cron a cada minuto e pelos "empurrões" após cada envio.
 */
class PipelineController extends Controller
{
    public function __invoke(LessonPipeline $pipeline)
    {
        ignore_user_abort(true);
        // Cada passo é pequeno -- se isso disparar, algo está preso; melhor
        // morrer e deixar o próximo tick tentar de novo.
        set_time_limit(110);

        $result = $pipeline->tick();

        if ($result['chain']) {
            // Ainda sobra trabalho: encadeia o próximo passo na hora em vez de
            // esperar o cron do minuto seguinte. O lock já foi liberado.
            ProcessingTrigger::fire();
        }

        return response()->json($result['response']);
    }
}

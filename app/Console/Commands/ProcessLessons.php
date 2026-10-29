<?php

namespace App\Console\Commands;

use App\Services\Pipeline\LessonPipeline;
use Illuminate\Console\Command;

/**
 * Mesmo passo do endpoint api/process, só que pela linha de comando -- útil
 * pra rodar/depurar no SSH sem passar pelo proxy HTTP:
 *   php artisan lectta:process           (um passo)
 *   php artisan lectta:process --until-idle
 */
class ProcessLessons extends Command
{
    protected $signature = 'lectta:process {--until-idle : Repete até não sobrar trabalho encadeável}';

    protected $description = 'Avança o pipeline de processamento de aulas (transcrição, resumo e flashcards)';

    public function handle(LessonPipeline $pipeline): int
    {
        do {
            $result = $pipeline->tick();
            $this->line(json_encode($result['response'], JSON_UNESCAPED_UNICODE));
        } while ($this->option('until-idle') && $result['chain']);

        return self::SUCCESS;
    }
}

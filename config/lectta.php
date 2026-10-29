<?php

return [

    // Protege os endpoints chamados por máquina (cron e worker do YouTube):
    // api/process, api/youtube/jobs, api/youtube/ingest. O worker usa o mesmo
    // valor no config.py dele.
    'api_token' => env('LECTTA_API_TOKEN'),

    'gemini_api_key' => env('GEMINI_API_KEY'),

    // Remetente do e-mail de "aula pronta" (o destinatário é o dono da aula).
    'notify_from' => env('LECTTA_NOTIFY_FROM', 'no-reply@lectta.online'),

    // Áudios enviados aguardando processamento. Fica dentro da pasta pública
    // (public/uploads/pending), mas com .htaccess negando qualquer acesso web.
    'upload_dir_pending' => env('LECTTA_UPLOAD_DIR_PENDING') ?: public_path('uploads/pending'),

    // Pedaços temporários do mp3 durante a transcrição.
    'work_dir' => env('LECTTA_WORK_DIR') ?: storage_path('app/pipeline'),

];

<?php

namespace App\Services\Pipeline;

/**
 * "Empurrão" de melhor esforço: chama o endpoint de processamento com um
 * timeout de 300ms só pra acordá-lo na hora, sem fazer quem chamou esperar o
 * pipeline. O cron de 1 em 1 minuto é quem garante que a aula é processada de
 * qualquer jeito, mesmo se isto falhar.
 */
final class ProcessingTrigger
{
    public static function fire(): void
    {
        $url = route('api.process', ['token' => config('lectta.api_token')]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT_MS => 300,
            CURLOPT_CONNECTTIMEOUT_MS => 300,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL => true,
        ]);
        @curl_exec($ch); // ignorado de propósito -- é só um empurrão, não uma dependência
        curl_close($ch);
    }
}

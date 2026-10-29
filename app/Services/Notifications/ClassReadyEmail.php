<?php

namespace App\Services\Notifications;

use App\Support\Markdown;

/**
 * E-mail (HTML + texto) enviado quando uma aula termina de ser processada.
 *
 * O markdown é renderizado pelo mesmo App\Support\Markdown da interface, só
 * que com estilos inline: cliente de e-mail não carrega folha de estilo nem
 * entende var(), então a paleta aqui é hexadecimal fixa.
 *
 * O envio usa mail() nativo de propósito: é o que já funcionava na Hostinger
 * sem configurar SMTP.
 */
final class ClassReadyEmail
{
    private const SUBJECT_PALETTE = [
        1 => ['bg' => '#ece3fb', 'text' => '#5c3ea8'],
        2 => ['bg' => '#dcedfb', 'text' => '#23588a'],
        3 => ['bg' => '#dcf3ea', 'text' => '#1f6e4d'],
        4 => ['bg' => '#fdecd2', 'text' => '#96601a'],
        5 => ['bg' => '#fbe2df', 'text' => '#a3402e'],
        6 => ['bg' => '#fbe0ef', 'text' => '#9c2f6d'],
        7 => ['bg' => '#e2f0d9', 'text' => '#4c6e28'],
        8 => ['bg' => '#dceef0', 'text' => '#1f6b76'],
    ];

    private const MD_STYLES = [
        'h3' => "font-family:Georgia,'Times New Roman',serif;font-size:19px;font-weight:600;margin:26px 0 10px;color:#201c15;",
        'h4' => "font-family:Georgia,'Times New Roman',serif;font-size:16px;font-weight:600;margin:22px 0 8px;color:#201c15;",
        'h5' => "font-family:Georgia,'Times New Roman',serif;font-size:15px;font-weight:600;margin:18px 0 6px;color:#201c15;",
        'h6' => "font-family:Georgia,'Times New Roman',serif;font-size:14px;font-weight:600;margin:16px 0 6px;color:#201c15;",
        'ul' => 'margin:6px 0 16px;padding-left:22px;color:#201c15;',
        'ol' => 'margin:6px 0 16px;padding-left:22px;color:#201c15;',
        'li' => 'margin:0 0 7px;line-height:1.6;',
        'p' => 'margin:0 0 13px;line-height:1.65;color:#201c15;',
        'strong' => 'color:#9c5f15;',
    ];

    public static function send(
        string $to,
        string $from,
        string $title,
        string $subject,
        ?string $topic,
        string $summaryMarkdown,
        int $cardCount,
        string $reviewUrl,
        string $logoUrl
    ): bool {
        $built = self::build($title, $subject, $topic, $summaryMarkdown, $cardCount, $reviewUrl, $logoUrl);
        $boundary = 'np' . md5(uniqid((string) mt_rand(), true));

        $headers = 'From: ' . $from . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $built['text'] . "\r\n\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $built['html'] . "\r\n\r\n";

        $body .= "--{$boundary}--";

        $encodedSubject = mb_encode_mimeheader($built['subject'], 'UTF-8', 'B', "\r\n");

        return @mail($to, $encodedSubject, $body, $headers);
    }

    /** Monta assunto, versão HTML e versão texto-puro do e-mail. */
    public static function build(
        string $title,
        string $subject,
        ?string $topic,
        string $summaryMarkdown,
        int $cardCount,
        string $reviewUrl,
        string $logoUrl
    ): array {
        $n = (crc32(mb_strtolower(trim($subject))) % 8) + 1;
        $colors = self::SUBJECT_PALETTE[$n];
        $summaryHtml = Markdown::render($summaryMarkdown, self::MD_STYLES);
        $titleSafe = htmlspecialchars($title);
        $subjectSafe = htmlspecialchars($subject);
        $topicSafe = $topic ? htmlspecialchars($topic) : null;
        $cardLabel = $cardCount . ' flashcard' . ($cardCount === 1 ? '' : 's') . ' criado' . ($cardCount === 1 ? '' : 's');

        $mailSubject = '[' . $subject . '] ' . $title . ' está pronta';

        $topicBadgeHtml = $topicSafe
            ? '<span style="display:inline-block;background:#f2ede1;color:#6c6455;font-size:12px;font-weight:700;padding:5px 12px;border-radius:999px;margin:0 0 0 6px;">' . $topicSafe . '</span>'
            : '';

        $html = <<<HTML
    <!doctype html>
    <html lang="pt-BR">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{$titleSafe}</title>
    <style>
      body, table, td { margin:0; padding:0; }
      img { border:0; display:block; }
      a { text-decoration:none; }
      @media (max-width: 620px) {
        .email-container { width:100% !important; }
        .email-px { padding-left:22px !important; padding-right:22px !important; }
      }
    </style>
    </head>
    <body style="background:#faf7f0;margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#201c15;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f0;padding:36px 12px;">
    <tr><td align="center">
    <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#ffffff;border:1px solid #e6ddc9;border-radius:18px;overflow:hidden;box-shadow:0 6px 20px -8px rgba(32,28,21,0.18);">

    <tr><td class="email-px" style="background:#ffffff;padding:22px 32px;border-bottom:1px solid #e6ddc9;">
      <table role="presentation" cellpadding="0" cellspacing="0"><tr>
        <td style="width:34px;height:34px;">
          <img src="{$logoUrl}" width="34" height="34" alt="Lectta" style="display:block;width:34px;height:34px;">
        </td>
        <td style="padding-left:10px;color:#33415c;font-size:16px;font-weight:700;font-family:Georgia,'Times New Roman',serif;">
          Lectta
        </td>
      </tr></table>
    </td></tr>

    <tr><td class="email-px" style="padding:34px 32px 8px;">
      <span style="display:inline-flex;align-items:center;gap:6px;background:#e3f1e6;color:#2f6f4e;font-size:12px;font-weight:700;padding:5px 12px;border-radius:999px;">
        &#10003;&nbsp; aula pronta
      </span>
      <h1 style="font-family:Georgia,'Times New Roman',serif;font-size:25px;font-weight:600;line-height:1.3;margin:16px 0 10px;color:#201c15;">
        {$titleSafe}
      </h1>
      <div style="margin:0 0 18px;">
        <span style="display:inline-block;background:{$colors['bg']};color:{$colors['text']};font-size:12px;font-weight:700;padding:5px 12px;border-radius:999px;">
          {$subjectSafe}
        </span>{$topicBadgeHtml}
      </div>
      <p style="margin:0 0 24px;font-size:14px;color:#6c6455;">
        {$cardLabel} para a revisão espaçada. O resumo completo está logo abaixo.
      </p>
      <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
        <tr><td style="border-radius:10px;background:#c17a1f;">
          <a href="{$reviewUrl}" style="display:inline-block;padding:13px 22px;font-size:14px;font-weight:700;color:#fffaf3;">
            Abrir aula no Lectta &rarr;
          </a>
        </td></tr>
      </table>
    </td></tr>

    <tr><td class="email-px" style="padding:8px 32px 6px;">
      <div style="border-top:1px solid #e6ddc9;margin:20px 0 26px;"></div>
    </td></tr>

    <tr><td class="email-px" style="padding:0 32px 30px;font-size:15px;">
      {$summaryHtml}
    </td></tr>

    <tr><td class="email-px" style="padding:18px 32px;background:#f2ede1;">
      <p style="margin:0;font-size:12px;color:#9a9384;line-height:1.6;">
        Você recebeu este e-mail porque uma aula terminou de ser transcrita e resumida automaticamente pelo Lectta.
      </p>
    </td></tr>

    </table>
    </td></tr>
    </table>
    </body>
    </html>
    HTML;

        $textSummary = trim(strip_tags(str_replace(['</p>', '</li>', '</h3>', '</h4>', '</h5>', '</h6>'], "\n", $summaryMarkdown)));
        $text = "Aula pronta: {$title} ({$subject}" . ($topic ? " — {$topic}" : '') . ")\n\n" .
            "{$cardLabel}.\n\n" .
            "Resumo:\n\n{$summaryMarkdown}\n\n" .
            "Acesse: {$reviewUrl}\n";

        return [
            'subject' => $mailSubject,
            'html' => $html,
            'text' => $text,
        ];
    }
}

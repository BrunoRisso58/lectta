<?php

namespace App\Services\Audio;

use RuntimeException;

// MP3 é uma sequência de frames autocontidos, então cortar entre frames gera arquivos válidos sem
// precisar de ffmpeg (que não existe em hospedagem compartilhada). Processa o
// arquivo em stream (nunca carrega o áudio inteiro na memória), importante
// porque aulas de 1-2h podem passar de 100-200MB e o memory_limit do PHP
// compartilhado costuma ser baixo.
final class Mp3Splitter
{

    private const BITRATE_V1_L1 = [0, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448, 0];
    private const BITRATE_V1_L2 = [0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384, 0];
    private const BITRATE_V1_L3 = [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 0];
    private const BITRATE_V2_L1 = [0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256, 0];
    private const BITRATE_V2_L23 = [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160, 0];

    private const SAMPLE_RATES = [
        3 => [44100, 48000, 32000], // MPEG1
        2 => [22050, 24000, 16000], // MPEG2
        0 => [11025, 12000, 8000],  // MPEG2.5
    ];

    private const SAMPLES_PER_FRAME = [
        '3:3' => 384, '3:2' => 1152, '3:1' => 1152, // MPEG1  L1/L2/L3
        '2:3' => 384, '2:2' => 1152, '2:1' => 576,  // MPEG2  L1/L2/L3
        '0:3' => 384, '0:2' => 1152, '0:1' => 576,  // MPEG2.5 L1/L2/L3
    ];

    /** Retorna ['length' => int] pra um header válido em $bytes4, ou null. */
    private static function parseFrameHeader(string $bytes4): ?array
    {
        if (strlen($bytes4) < 4) {
            return null;
        }
        $b0 = ord($bytes4[0]);
        $b1 = ord($bytes4[1]);
        $b2 = ord($bytes4[2]);

        if ($b0 !== 0xFF || ($b1 & 0xE0) !== 0xE0) {
            return null;
        }

        $versionIndex = ($b1 >> 3) & 0x03;
        $layerIndex = ($b1 >> 1) & 0x03;
        if ($versionIndex === 1 || $layerIndex === 0) {
            return null;
        }

        $bitrateIndex = ($b2 >> 4) & 0x0F;
        $samplerateIndex = ($b2 >> 2) & 0x03;
        $padding = ($b2 >> 1) & 0x01;
        if ($bitrateIndex === 0x0F || $samplerateIndex === 0x03) {
            return null;
        }

        if ($versionIndex === 3) {
            $bitrateTable = match ($layerIndex) {
                3 => self::BITRATE_V1_L1,
                2 => self::BITRATE_V1_L2,
                default => self::BITRATE_V1_L3,
            };
        } else {
            $bitrateTable = $layerIndex === 3 ? self::BITRATE_V2_L1 : self::BITRATE_V2_L23;
        }

        $bitrateKbps = $bitrateTable[$bitrateIndex];
        $sampleRate = self::SAMPLE_RATES[$versionIndex][$samplerateIndex];
        if ($bitrateKbps === 0 || $sampleRate === 0) {
            return null;
        }

        $bitrateBps = $bitrateKbps * 1000;
        $samplesPerFrame = self::SAMPLES_PER_FRAME["{$versionIndex}:{$layerIndex}"];

        if ($layerIndex === 3) { // Layer I usa fórmula com *4
            $frameLen = (intdiv(intdiv($samplesPerFrame, 8) * $bitrateBps, $sampleRate) + $padding) * 4;
        } else {
            $frameLen = intdiv(intdiv($samplesPerFrame, 8) * $bitrateBps, $sampleRate) + $padding;
        }

        if ($frameLen <= 0) {
            return null;
        }

        return ['length' => $frameLen];
    }

    private static function skipId3v2(string $header10): int
    {
        if (strlen($header10) < 10 || substr($header10, 0, 3) !== 'ID3') {
            return 0;
        }
        $flags = ord($header10[5]);
        $size = ((ord($header10[6]) & 0x7F) << 21)
            | ((ord($header10[7]) & 0x7F) << 14)
            | ((ord($header10[8]) & 0x7F) << 7)
            | (ord($header10[9]) & 0x7F);
        $headerLen = 10;
        if ($flags & 0x10) {
            $headerLen += 10;
        }
        return $headerLen + $size;
    }

    /**
     * Divide $inputPath em pedaços MP3 sequenciais, cada um até $targetBytes.
     * Retorna a lista de caminhos dos pedaços gerados, em ordem.
     */
    public static function split(string $inputPath, string $outputDir, int $targetBytes = 20 * 1024 * 1024): array
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $in = fopen($inputPath, 'rb');
        if ($in === false) {
            throw new RuntimeException("Não foi possível abrir $inputPath");
        }

        $head = fread($in, 10);
        $id3Len = self::skipId3v2($head);
        fseek($in, $id3Len);

        $baseName = pathinfo($inputPath, PATHINFO_FILENAME);
        $chunkPaths = [];
        $chunkIndex = 1;
        $chunkSize = 0;
        $out = null;

        $openNewChunk = function () use (&$out, &$chunkIndex, $outputDir, $baseName, &$chunkPaths) {
            if ($out !== null) {
                fclose($out);
            }
            $path = sprintf('%s/%s.part%03d.mp3', $outputDir, $baseName, $chunkIndex);
            $out = fopen($path, 'wb');
            $chunkPaths[] = $path;
            $chunkIndex++;
        };

        $openNewChunk();

        while (!feof($in)) {
            $header = fread($in, 4);
            if ($header === false || strlen($header) < 4) {
                break;
            }
            $frame = self::parseFrameHeader($header);
            if ($frame === null) {
                // não é um frame válido aqui -- volta 3 bytes e tenta a partir do próximo byte
                fseek($in, -3, SEEK_CUR);
                continue;
            }

            $payloadLen = $frame['length'] - 4;
            $payload = $payloadLen > 0 ? fread($in, $payloadLen) : '';
            if ($payload === false || strlen($payload) < $payloadLen) {
                break; // truncado no fim do arquivo
            }

            if ($chunkSize > 0 && $chunkSize + $frame['length'] > $targetBytes) {
                $openNewChunk();
                $chunkSize = 0;
            }

            fwrite($out, $header . $payload);
            $chunkSize += $frame['length'];
        }

        if ($out !== null) {
            fclose($out);
        }
        fclose($in);

        // se o último pedaço ficou vazio (arquivo sem frames após o ID3), descarta
        if ($chunkPaths && filesize(end($chunkPaths)) === 0) {
            unlink(end($chunkPaths));
            array_pop($chunkPaths);
        }

        if (!$chunkPaths) {
            throw new RuntimeException("Nenhum frame MP3 encontrado em $inputPath");
        }

        return $chunkPaths;
    }
}

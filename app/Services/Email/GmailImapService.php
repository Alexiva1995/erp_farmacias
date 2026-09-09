<?php

declare(strict_types=1);

namespace App\Services\Email;

use Exception;
use Illuminate\Support\Facades\Log;

class GmailImapService
{
    private $socket = null;
    private int $tagIndex = 0;

    /**
     * Establece conexión SSL segura con el servidor IMAP de Gmail.
     */
    public function connect(string $host, int $port, string $email, string $password, int $timeout = 25): void
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $connectionString = "ssl://{$host}:{$port}";
        $this->socket = @stream_socket_client(
            $connectionString,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new Exception("No se pudo conectar al servidor IMAP ({$connectionString}): [{$errno}] {$errstr}");
        }

        stream_set_timeout($this->socket, $timeout);

        // Leer saludo inicial
        $this->readLine();

        // Autenticación con LOGIN
        $escapedEmail = addslashes($email);
        $escapedPass = addslashes($password);
        $response = $this->sendCommand("LOGIN \"{$escapedEmail}\" \"{$escapedPass}\"");

        if (!$this->isResponseOk($response)) {
            $this->disconnect();
            throw new Exception("Error de autenticación IMAP en Gmail para '{$email}'. Verifique usuario y contraseña de aplicación.");
        }
    }

    /**
     * Selecciona una carpeta (por defecto INBOX).
     */
    public function selectFolder(string $folder = 'INBOX'): array
    {
        $response = $this->sendCommand("SELECT \"{$folder}\"");
        if (!$this->isResponseOk($response)) {
            throw new Exception("No se pudo seleccionar la carpeta IMAP '{$folder}'.");
        }

        $exists = 0;
        foreach ($response as $line) {
            if (preg_match('/^\*\s+(\d+)\s+EXISTS/i', $line, $m)) {
                $exists = (int) $m[1];
            }
        }

        return [
            'folder' => $folder,
            'exists' => $exists,
        ];
    }

    /**
     * Busca los números de mensaje según criterio (ej. FROM "ventas@proveedor.com" o ALL o UNSEEN).
     */
    public function search(string $criteria = 'ALL'): array
    {
        $response = $this->sendCommand("SEARCH {$criteria}");
        $messageNumbers = [];

        foreach ($response as $line) {
            if (str_starts_with($line, '* SEARCH')) {
                $parts = explode(' ', trim($line));
                array_shift($parts); // *
                array_shift($parts); // SEARCH
                foreach ($parts as $num) {
                    if (is_numeric($num)) {
                        $messageNumbers[] = (int) $num;
                    }
                }
            }
        }

        return $messageNumbers;
    }

    /**
     * Extrae el contenido completo, encabezados y archivos adjuntos de un mensaje específico.
     */
    public function fetchMessage(int $messageNumber, array $allowedExtensions = ['xlsx', 'xls', 'csv']): ?array
    {
        $response = $this->sendCommand("FETCH {$messageNumber} (BODY.PEEK[])");
        $rawMessage = implode("\r\n", $response);

        if (empty($rawMessage)) {
            return null;
        }

        return $this->parseEmailContent($rawMessage, $allowedExtensions, $messageNumber);
    }

    /**
     * Marca un mensaje como leído.
     */
    public function markAsRead(int $messageNumber): void
    {
        $this->sendCommand("STORE {$messageNumber} +FLAGS (\\Seen)");
    }

    /**
     * Cierra la conexión IMAP.
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            try {
                $this->sendCommand("LOGOUT");
            } catch (\Throwable) {
                // Silenciar
            }
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Envía un comando IMAP con etiqueta única y captura la respuesta completa con soporte para literales {N}.
     */
    private function sendCommand(string $command): array
    {
        $this->tagIndex++;
        $tag = sprintf('A%04d', $this->tagIndex);
        $fullCommand = "{$tag} {$command}\r\n";

        fwrite($this->socket, $fullCommand);

        $lines = [];
        while (!feof($this->socket)) {
            $line = $this->readLine();
            if ($line === null) {
                break;
            }
            $lines[] = $line;

            // Detección de literal IMAP {12345}
            if (preg_match('/\{(\d+)\}$/', $line, $m)) {
                $bytesToRead = (int) $m[1];
                $literalContent = '';
                $readSoFar = 0;
                while ($readSoFar < $bytesToRead && !feof($this->socket)) {
                    $chunk = fread($this->socket, min(65536, $bytesToRead - $readSoFar));
                    if ($chunk === false || strlen($chunk) === 0) {
                        break;
                    }
                    $literalContent .= $chunk;
                    $readSoFar += strlen($chunk);
                }
                $lines[] = $literalContent;
            }

            // Verificar si la respuesta incluye la etiqueta de finalización
            if (str_starts_with($line, "{$tag} OK") || str_starts_with($line, "{$tag} NO") || str_starts_with($line, "{$tag} BAD")) {
                break;
            }
        }

        return $lines;
    }

    /**
     * Lee una línea individual del socket.
     */
    private function readLine(): ?string
    {
        if (!$this->socket || feof($this->socket)) {
            return null;
        }

        $line = fgets($this->socket, 8192);
        return $line !== false ? rtrim($line, "\r\n") : null;
    }

    /**
     * Verifica si la respuesta final de un comando IMAP fue OK.
     */
    private function isResponseOk(array $response): bool
    {
        $lastLine = end($response);
        return is_string($lastLine) && str_contains($lastLine, ' OK');
    }

    /**
     * Parsea encabezados y adjuntos de un mensaje MIME crudo.
     */
    private function parseEmailContent(string $raw, array $allowedExtensions, int $messageNumber): array
    {
        $parts = explode("\r\n\r\n", $raw, 2);
        $headerSection = $parts[0] ?? '';
        $bodySection = $parts[1] ?? '';

        // Extraer encabezados principales
        $headers = [];
        $headerLines = explode("\r\n", $headerSection);
        $currentHeader = '';

        foreach ($headerLines as $hline) {
            if (preg_match('/^\s+/', $hline)) {
                if ($currentHeader) {
                    $headers[$currentHeader] .= ' ' . trim($hline);
                }
            } elseif (str_contains($hline, ':')) {
                [$k, $v] = explode(':', $hline, 2);
                $k = strtolower(trim($k));
                $currentHeader = $k;
                $headers[$k] = trim($v);
            }
        }

        $subject = $this->decodeMimeHeader($headers['subject'] ?? '(Sin asunto)');
        $from = $this->decodeMimeHeader($headers['from'] ?? '');
        $date = $headers['date'] ?? '';

        $fromEmail = '';
        if (preg_match('/<([^>]+)>/', $from, $m)) {
            $fromEmail = trim($m[1]);
        } else {
            $fromEmail = trim($from);
        }

        $attachments = [];
        $contentType = $headers['content-type'] ?? '';

        if (preg_match('/boundary=["\']?([^"\';\r\n]+)["\']?/i', $contentType, $matches)) {
            $boundary = $matches[1];
            $sections = explode("--{$boundary}", $bodySection);

            foreach ($sections as $section) {
                if (empty(trim($section)) || trim($section) === '--') {
                    continue;
                }

                $subParts = explode("\r\n\r\n", $section, 2);
                $partHeaders = $subParts[0] ?? '';
                $partBody = $subParts[1] ?? '';

                $filename = null;
                if (preg_match('/filename\*?=["\']?(?:UTF-8\'\')?([^"\'\r\n;]+)["\']?/i', $partHeaders, $fnMatches)) {
                    $filename = urldecode($fnMatches[1]);
                } elseif (preg_match('/name=["\']?([^"\'\r\n;]+)["\']?/i', $partHeaders, $fnMatches)) {
                    $filename = $fnMatches[1];
                }

                if ($filename) {
                    $filename = $this->decodeMimeHeader($filename);
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    if (in_array($ext, $allowedExtensions)) {
                        $isBase64 = str_contains(strtolower($partHeaders), 'content-transfer-encoding: base64');
                        $fileData = $isBase64 ? base64_decode(str_replace(["\r", "\n", " "], '', $partBody)) : $partBody;

                        $attachments[] = [
                            'filename' => $filename,
                            'extension' => $ext,
                            'content' => $fileData,
                            'size' => strlen($fileData),
                        ];
                    }
                }
            }
        }

        return [
            'message_number' => $messageNumber,
            'subject' => $subject,
            'from' => $from,
            'from_email' => strtolower($fromEmail),
            'date' => $date,
            'attachments' => $attachments,
        ];
    }

    private function decodeMimeHeader(string $text): string
    {
        if (function_exists('mb_decode_mimeheader')) {
            return mb_decode_mimeheader($text);
        }
        return iconv_mime_decode($text, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    }
}

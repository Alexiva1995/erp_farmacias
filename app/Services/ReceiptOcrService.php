<?php

namespace App\Services;

use App\Contracts\ReceiptOcrServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ReceiptOcrService implements ReceiptOcrServiceInterface
{
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Extrae el número de referencia bancario desde un comprobante (imagen o PDF).
     */
    public function extractReference(UploadedFile|string $file): ?string
    {
        try {
            $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
            $extension = strtolower($file instanceof UploadedFile ? $file->getClientOriginalExtension() : pathinfo($filePath, PATHINFO_EXTENSION));

            if (!file_exists($filePath)) {
                return null;
            }

            // 1. Si es imagen, intentar primero con Gemini AI (alta precisión)
            if ($extension !== 'pdf') {
                $geminiRef = $this->geminiService->extractPaymentReference($filePath);
                if (!empty($geminiRef)) {
                    return $geminiRef;
                }
            }

            $rawText = "";

            if ($extension === "pdf") {
                $rawText = $this->extractTextFromPdf($filePath);
            } else {
                $rawText = $this->extractTextFromImage($filePath);
            }

            if (empty(trim($rawText))) {
                return null;
            }

            return $this->findReferenceInText($rawText);
        } catch (\Throwable $e) {
            Log::warning("[ReceiptOcrService] Error procesando comprobante: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extrae texto plano desde un archivo PDF (usando Smalot PDF Parser).
     */
    protected function extractTextFromPdf(string $path): string
    {
        try {
            if (class_exists(\Smalot\PdfParser\Parser::class)) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($path);
                return (string) $pdf->getText();
            }
        } catch (\Throwable $e) {
            Log::warning("[ReceiptOcrService] Falló lectura PDF: " . $e->getMessage());
        }
        return "";
    }

    /**
     * Extrae texto desde una imagen usando Tesseract OCR si está disponible en el servidor.
     */
    protected function extractTextFromImage(string $path): string
    {
        $tesseractBinary = $this->findTesseractBinary();
        if (!$tesseractBinary) {
            return "";
        }

        try {
            $redirect = PHP_OS_FAMILY === 'Windows' ? '2>nul' : '2>/dev/null';
            $cmd = escapeshellcmd($tesseractBinary) . " " . escapeshellarg($path) . " stdout -l spa+eng --psm 6 {$redirect}";
            $text = shell_exec($cmd);
            return is_string($text) ? $text : "";
        } catch (\Throwable $e) {
            Log::warning("[ReceiptOcrService] Falló OCR imagen: " . $e->getMessage());
            return "";
        }
    }

    /**
     * Detecta la ubicación del ejecutable tesseract.
     */
    protected function findTesseractBinary(): ?string
    {
        $paths = [
            "/usr/bin/tesseract",
            "/usr/local/bin/tesseract",
            "tesseract",
            "C:\\Program Files\\Tesseract-OCR\\tesseract.exe",
            "C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe",
        ];

        foreach ($paths as $p) {
            if ($p === "tesseract") {
                $check = PHP_OS_FAMILY === "Windows" ? @shell_exec("where tesseract 2>nul") : @shell_exec("which tesseract 2>/dev/null");
                if (!empty(trim((string)$check))) {
                    return "tesseract";
                }
            } elseif (file_exists($p)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Analiza el texto extraído buscando números de referencia bancaria havituales en Venezuela.
     */
    public function findReferenceInText(string $text): ?string
    {
        // 1. Patrones directos en la misma línea
        $patterns = [
            '/(?:n[uú]mero\s*(?:de)?\s*operaci[oó]n|nro\.?\s*(?:de)?\s*operaci[oó]n|operaci[oó]n|n[uú]mero\s*(?:de)?\s*referencia|nro\.?\s*(?:de)?\s*referencia|referencia|ref\.?|aprobaci[oó]n|secuencia|transacci[oó]n|clave\s*de\s*pago|c[oó]digo\s*de\s*pago|c[oó]digo\s*de\s*referencia)[^0-9\n\r:]{0,25}[:\s#]*([0-9]{4,18})/iu',
            '/(?:operaci[oó]n|referencia|ref)[\s:]+([0-9]{4,18})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $ref = ltrim(trim($match), '# ');
                    if (strlen($ref) >= 4 && strlen($ref) <= 18) {
                        return $ref;
                    }
                }
            }
        }

        // 2. Patrones de dos líneas (ej. Banco de Venezuela donde 'Operación' está en una línea y el número en la siguiente)
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn($l) => $l !== ''));
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (preg_match('/(?:operaci[oó]n|referencia|ref|secuencia|transacci[oó]n|aprobaci[oó]n)/iu', $line)) {
                if (preg_match('/([0-9]{4,18})/', $line, $inlineMatch)) {
                    $cleanInline = ltrim(trim($inlineMatch[1]), '# ');
                    if (strlen($cleanInline) >= 4 && strlen($cleanInline) <= 18) {
                        return $cleanInline;
                    }
                }
                if (isset($lines[$i + 1])) {
                    $nextLine = $lines[$i + 1];
                    if (preg_match('/([0-9]{4,18})/', $nextLine, $m)) {
                        $cleanNext = ltrim(trim($m[1]), '# ');
                        if (strlen($cleanNext) >= 4 && strlen($cleanNext) <= 18) {
                            return $cleanNext;
                        }
                    }
                }
            }
        }

        return null;
    }
}

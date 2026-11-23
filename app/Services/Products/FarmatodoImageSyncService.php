<?php

namespace App\Services\Products;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FarmatodoImageSyncService
{
    private const ALGOLIA_HOSTS = [
        'https://VCOJEYD2PO-dsn.algolia.net',
        'https://api-search.farmatodo.com',
    ];

    private const ALGOLIA_INDEX = 'products-venezuela';
    private const ALGOLIA_APP_ID = 'VCOJEYD2PO';
    private const ALGOLIA_API_KEY = '869a91e98550dd668b8b1dc04bca9011';
    private const DROMEGA_IMAGE_BASE_URL = 'https://www.drogueriamega.com/ventas/imagenes';

    private const MAX_CONSECUTIVE_BLOCKS = 3;
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024; // 5 MB

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
    ];

    private int $consecutiveBlocks = 0;

    /**
     * Sincroniza imágenes de productos desde Farmatodo usando su código de barras.
     *
     * @return array{processed: int, updated: int, not_found: int, errors: int, aborted: bool}
     */
    public function syncMissingImages(int $limit = 100, bool $force = false, bool $dryRun = false): array
    {
        $stats = [
            'processed' => 0,
            'updated' => 0,
            'not_found' => 0,
            'errors' => 0,
            'aborted' => false,
        ];

        $masterConn = $this->getMasterConnection();
        $lastId = (int) Cache::get('farmatodo_sync_last_id', 0);

        $buildQuery = function (int $afterId) use ($force, $masterConn) {
            $q = Product::on($masterConn)
                ->select(['id', 'name', 'barcode', 'photo_url'])
                ->whereNotNull('barcode')
                ->where('barcode', '!=', '')
                ->where('id', '>', $afterId);

            if (!$force) {
                $q->where(function ($sub) {
                    $sub->whereNull('photo_url')->orWhere('photo_url', '');
                });
            }

            return $q->orderBy('id');
        };

        $products = $buildQuery($lastId)->limit($limit)->get();

        // Si llegamos al final de la tabla y había un cursor previo, reiniciar desde el principio
        if ($products->isEmpty() && $lastId > 0) {
            $lastId = 0;
            Cache::forever('farmatodo_sync_last_id', 0);
            $products = $buildQuery(0)->limit($limit)->get();
        }

        $lastProcessedId = $lastId;

        foreach ($products as $index => $product) {
            if ($this->consecutiveBlocks >= self::MAX_CONSECUTIVE_BLOCKS) {
                $stats['aborted'] = true;
                Log::warning('FarmatodoImageSync: Proceso abortado por bloqueos consecutivos (403/429).');
                break;
            }

            $stats['processed']++;
            $lastProcessedId = (int) $product->id;
            $barcode = trim((string) $product->barcode);

            if ($barcode === '' || strlen($barcode) < 6) {
                $stats['not_found']++;
                continue;
            }

            try {
                $imageUrl = $this->findImageUrlByBarcode($barcode);

                if ($imageUrl) {
                    if ($dryRun) {
                        $stats['updated']++;
                    } else {
                        $storedPath = $this->downloadAndStoreImage($imageUrl, $barcode);
                        if ($storedPath) {
                            $product->update(['photo_url' => $storedPath]);
                            $stats['updated']++;
                        } else {
                            $stats['errors']++;
                        }
                    }
                } elseif ($dryRun) {
                    if ($this->checkDromegaImageExists($barcode)) {
                        $stats['updated']++;
                    } else {
                        $stats['not_found']++;
                    }
                } else {
                    // Fallback a Droguería Mega cuando Farmatodo no tiene imagen
                    $storedPath = $this->downloadDromegaImage($barcode);
                    if ($storedPath) {
                        $product->update(['photo_url' => $storedPath]);
                        $stats['updated']++;
                    } else {
                        $stats['not_found']++;
                    }
                }
            } catch (Throwable $e) {
                $stats['errors']++;
                Log::error('FarmatodoImageSync: Error procesando producto', [
                    'product_id' => $product->id,
                    'barcode' => $barcode,
                    'error' => $e->getMessage(),
                ]);
            }

            // Pausa aleatoria (jitter) entre peticiones para evitar detección/baneo
            $this->humanDelay($index + 1);
        }

        // Guardar cursor para avanzar al siguiente lote sin repetir los no encontrados
        if (!$dryRun) {
            $nextCursor = ($products->count() < $limit && !$stats['aborted']) ? 0 : $lastProcessedId;
            Cache::forever('farmatodo_sync_last_id', $nextCursor);
        }

        return $stats;
    }

    /**
     * Consulta Algolia buscando coincidencia exacta por código de barras.
     */
    public function findImageUrlByBarcode(string $barcode): ?string
    {
        $payload = [
            'query' => $barcode,
            'filters' => "barcode:{$barcode}",
            'hitsPerPage' => 3,
        ];

        foreach (self::ALGOLIA_HOSTS as $host) {
            $url = "{$host}/1/indexes/" . self::ALGOLIA_INDEX . '/query';

            $response = Http::timeout(10)
                ->withHeaders($this->buildAlgoliaHeaders())
                ->post($url, $payload);

            if ($response->status() === 429 || $response->status() === 403) {
                $this->handleRateLimit($response->header('Retry-After'));
                continue;
            }

            if (!$response->successful()) {
                continue;
            }

            $this->consecutiveBlocks = 0;
            $hits = $response->json('hits', []);

            foreach ($hits as $hit) {
                $hitBarcode = trim((string) ($hit['barcode'] ?? ''));
                if ($hitBarcode !== $barcode) {
                    continue;
                }

                return $this->extractImageUrl($hit);
            }

            return null;
        }

        return null;
    }

    /**
     * Descarga la imagen validando tipo MIME y tamaño, y la guarda en el disco público.
     */
    private function downloadAndStoreImage(
        string $imageUrl,
        string $barcode,
        string $referer = 'https://www.farmatodo.com.ve/',
        bool $trackRateLimit = true,
        int $timeoutSeconds = 15,
    ): ?string {
        $response = Http::timeout($timeoutSeconds)
            ->withHeaders([
                'User-Agent' => $this->randomUserAgent(),
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'Referer' => $referer,
            ])
            ->get($imageUrl);

        if ($trackRateLimit && ($response->status() === 429 || $response->status() === 403)) {
            $this->handleRateLimit($response->header('Retry-After'));
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $body = $response->body();
        if (strlen($body) === 0 || strlen($body) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $extension = match ($contentType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };

        if (!$extension) {
            return null;
        }

        $safeBarcode = preg_replace('/[^A-Za-z0-9_-]/', '', $barcode);
        $path = "products/{$safeBarcode}.{$extension}";

        Storage::disk('public')->put($path, $body);

        return $path;
    }

    /**
     * Construye la URL pública de imagen en Droguería Mega a partir del código de barras.
     */
    private function buildDromegaImageUrl(string $barcode): ?string
    {
        $safeBarcode = preg_replace('/[^A-Za-z0-9_-]/', '', trim($barcode));
        if ($safeBarcode === '') {
            return null;
        }

        return self::DROMEGA_IMAGE_BASE_URL . "/{$safeBarcode}.jpg";
    }

    /**
     * Verifica mediante HEAD si la imagen existe en Droguería Mega (para modo dry-run).
     */
    private function checkDromegaImageExists(string $barcode): bool
    {
        $url = $this->buildDromegaImageUrl($barcode);
        if (!$url) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => $this->randomUserAgent()])
                ->head($url);

            $contentType = strtolower((string) $response->header('Content-Type'));
            return $response->successful() && str_starts_with($contentType, 'image/');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Intenta descargar la imagen desde Droguería Mega sin afectar el control anti-ban de Farmatodo.
     */
    private function downloadDromegaImage(string $barcode): ?string
    {
        $url = $this->buildDromegaImageUrl($barcode);
        if (!$url) {
            return null;
        }

        try {
            return $this->downloadAndStoreImage(
                imageUrl: $url,
                barcode: $barcode,
                referer: 'https://www.drogueriamega.com/',
                trackRateLimit: false,
                timeoutSeconds: 6,
            );
        } catch (Throwable $e) {
            Log::debug('FarmatodoImageSync: Fallback Droguería Mega no disponible', [
                'barcode' => $barcode,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extrae la URL de imagen principal desde un hit de Algolia.
     */
    private function extractImageUrl(array $hit): ?string
    {
        $candidates = [];

        if (!empty($hit['mediaImageUrl']) && is_string($hit['mediaImageUrl'])) {
            $candidates[] = $hit['mediaImageUrl'];
        }

        if (!empty($hit['media']) && is_array($hit['media'])) {
            foreach ($hit['media'] as $item) {
                if (is_string($item)) {
                    $candidates[] = $item;
                } elseif (is_array($item) && !empty($item['url'])) {
                    $candidates[] = $item['url'];
                }
            }
        }

        if (!empty($hit['images']) && is_array($hit['images'])) {
            foreach ($hit['images'] as $item) {
                if (is_string($item)) {
                    $candidates[] = $item;
                }
            }
        }

        foreach ($candidates as $url) {
            if (is_string($url) && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Construye headers simulando tráfico legítimo del navegador.
     *
     * @return array<string, string>
     */
    private function buildAlgoliaHeaders(): array
    {
        return [
            'x-algolia-application-id' => self::ALGOLIA_APP_ID,
            'x-algolia-api-key' => self::ALGOLIA_API_KEY,
            'User-Agent' => $this->randomUserAgent(),
            'Accept' => 'application/json',
            'Accept-Language' => 'es-VE,es;q=0.9,en;q=0.8',
            'Origin' => 'https://www.farmatodo.com.ve',
            'Referer' => 'https://www.farmatodo.com.ve/',
        ];
    }

    private function randomUserAgent(): string
    {
        return self::USER_AGENTS[array_rand(self::USER_AGENTS)];
    }

    /**
     * Retorna la conexión al catálogo maestro (tovaerp_master) con fallback a la conexión por defecto.
     */
    private function getMasterConnection(): string
    {
        try {
            if (Schema::connection('master_catalog')->hasTable('products')) {
                return 'master_catalog';
            }
        } catch (Throwable) {
            // Fallback a conexión por defecto
        }

        return config('database.default');
    }

    /**
     * Aplica backoff exponencial ante respuestas 403/429.
     */
    private function handleRateLimit(?string $retryAfter): void
    {
        $this->consecutiveBlocks++;
        $seconds = is_numeric($retryAfter)
            ? min((int) $retryAfter, 60)
            : (5 * (3 ** ($this->consecutiveBlocks - 1)));

        Log::warning("FarmatodoImageSync: Rate limit detectado. Esperando {$seconds}s.");
        sleep($seconds);
    }

    /**
     * Pausa aleatoria entre peticiones y enfriamiento periódico por lote.
     */
    private function humanDelay(int $iteration): void
    {
        // Pausa aleatoria entre 350 ms y 850 ms
        usleep(random_int(350_000, 850_000));

        // Cada 50 productos, pausa de enfriamiento de 3 a 5 segundos
        if ($iteration % 50 === 0) {
            sleep(random_int(3, 5));
        }
    }
}

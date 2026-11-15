<?php

namespace App\Services\Products;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        $lastId = (int) Cache::get('farmatodo_sync_last_id', 0);

        $buildQuery = function (int $afterId) use ($force) {
            $q = Product::query()
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

                if (!$imageUrl) {
                    $stats['not_found']++;
                } elseif ($dryRun) {
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
    private function downloadAndStoreImage(string $imageUrl, string $barcode): ?string
    {
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => $this->randomUserAgent(),
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'Referer' => 'https://www.farmatodo.com.ve/',
            ])
            ->get($imageUrl);

        if ($response->status() === 429 || $response->status() === 403) {
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

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScraperKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierScraper extends Model
{
    use HasFactory;

    protected $table = 'supplier_scrapers';

    protected $fillable = [
        'supplier_id',
        'scraper_key',
        'portal_url',
        'username',
        'password',
        'auth_payload',
        'is_active',
        'last_scraped_at',
        'last_status',
        'last_message',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'scraper_key' => ScraperKey::class,
        'auth_payload' => 'array',
        'is_active' => 'boolean',
        'last_scraped_at' => 'datetime',
    ];

    /**
     * Caché en memoria por conexión para evitar verificar esquema repetidamente.
     *
     * @var array<string, bool>
     */
    protected static array $schemaEnsuredCache = [];

    /**
     * Garantiza que la tabla supplier_scrapers exista en la conexión activa y migra credenciales heredadas.
     */
    public static function ensureSchema($connection = null): bool
    {
        try {
            $conn = $connection ?? (new static())->getConnection();
            $connName = (string) $conn->getName();
            $tenantKey = function_exists('tenancy') && tenancy()->initialized
                ? (string) tenancy()->tenant?->id
                : 'central';
            $cacheKey = "{$connName}:{$tenantKey}";

            if (isset(static::$schemaEnsuredCache[$cacheKey])) {
                return static::$schemaEnsuredCache[$cacheKey];
            }

            if ($conn->transactionLevel() > 0) {
                return false;
            }

            $schema = $conn->getSchemaBuilder();

            if (!$schema->hasTable('supplier_scrapers')) {
                $schema->create('supplier_scrapers', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('supplier_id')->index();
                    $table->string('scraper_key', 50);
                    $table->string('portal_url', 255)->nullable();
                    $table->string('username', 255)->nullable();
                    $table->text('password')->nullable();
                    $table->json('auth_payload')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->timestamp('last_scraped_at')->nullable();
                    $table->string('last_status', 30)->nullable();
                    $table->text('last_message')->nullable();
                    $table->timestamps();

                    $table->unique(['supplier_id', 'scraper_key'], 'supplier_scrapers_supplier_key_unique');
                });

                static::migrateLegacyBotConnections($conn);
            }

            return static::$schemaEnsuredCache[$cacheKey] = true;
        } catch (\Throwable $e) {
            Log::warning('[SupplierScraper] Error asegurando esquema: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Migra credenciales de bots heredadas desde supplier_connections hacia supplier_scrapers.
     */
    protected static function migrateLegacyBotConnections($conn): void
    {
        $schema = $conn->getSchemaBuilder();
        if (!$schema->hasTable('supplier_connections')) {
            return;
        }

        $botTypes = [
            'dronena_bot' => 'dronena',
            'dronena' => 'dronena',
            'drocerca_bot' => 'drocerca',
            'drocerca' => 'drocerca',
            'mafarta_bot' => 'mafarta',
            'mafarta' => 'mafarta',
            'cristmedicals_bot' => 'cristmedicals',
            'cristmedicals' => 'cristmedicals',
            'dromega_bot' => 'dromega',
            'dromega' => 'dromega',
            'drosymca_bot' => 'drosymca',
            'drosymca' => 'drosymca',
        ];

        $rows = $conn->table('supplier_connections')
            ->whereIn('type', array_keys($botTypes))
            ->orderByDesc('id')
            ->get();

        foreach ($rows as $row) {
            $key = $botTypes[strtolower((string) $row->type)] ?? null;
            if (!$key || !$row->supplier_id) {
                continue;
            }

            $authPayload = null;
            if (!empty($row->structure)) {
                $decoded = is_string($row->structure) ? json_decode($row->structure, true) : (array) $row->structure;
                if (is_array($decoded) && !empty($decoded['session_token'])) {
                    $authPayload = json_encode(['session_token' => $decoded['session_token']]);
                }
            }

            $exists = $conn->table('supplier_scrapers')
                ->where('supplier_id', $row->supplier_id)
                ->where('scraper_key', $key)
                ->exists();

            if (!$exists) {
                $conn->table('supplier_scrapers')->insert([
                    'supplier_id' => $row->supplier_id,
                    'scraper_key' => $key,
                    'portal_url' => $row->host,
                    'username' => $row->username,
                    'password' => $row->password,
                    'auth_payload' => $authPayload,
                    'is_active' => true,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }
        }

        $conn->table('supplier_connections')
            ->whereIn('type', array_keys($botTypes))
            ->delete();
    }

    /**
     * Resuelve la configuración del scraper asegurando esquema y con respaldo para proveedores heredados.
     */
    public static function resolveForScraper(string $scraperKey, ?int $supplierId = null): ?self
    {
        static::ensureSchema();

        $normalizedKey = strtolower(trim($scraperKey));

        if ($supplierId) {
            $direct = static::where('supplier_id', $supplierId)
                ->where('scraper_key', $normalizedKey)
                ->first();

            if ($direct) {
                if (!$direct->is_active) {
                    return null;
                }

                if (!empty($direct->username) || !empty($direct->password) || !empty($direct->auth_payload)) {
                    return $direct;
                }
            }
        }

        // Respaldo: si el proveedor fue duplicado o separado, buscar un scraper activo con credenciales para esa clave
        $byKey = static::where('scraper_key', $normalizedKey)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('username')->where('username', '!=', '');
                })->orWhereNotNull('auth_payload');
            })
            ->orderByDesc('id')
            ->first();

        if ($byKey) {
            return $byKey;
        }

        // Respaldo final: migrar bajo demanda desde supplier_connections si existiera con tipo genérico (http/api/bot)
        if ($supplierId && static::migrateGenericConnectionForSupplier($supplierId, $normalizedKey)) {
            return static::where('supplier_id', $supplierId)
                ->where('scraper_key', $normalizedKey)
                ->first();
        }

        return null;
    }

    /**
     * Migra una conexión genérica (http/api/bot o por dominio de portal) desde supplier_connections si aún no existe en supplier_scrapers.
     */
    protected static function migrateGenericConnectionForSupplier(int $supplierId, string $scraperKey): bool
    {
        try {
            $conn = (new static())->getConnection();
            if (!$conn->getSchemaBuilder()->hasTable('supplier_connections')) {
                return false;
            }

            $hostKeywords = match ($scraperKey) {
                'dronena' => ['dronena'],
                'drocerca' => ['drocerca'],
                'mafarta' => ['cobeca', 'mafarta'],
                'cristmedicals' => ['cristmedicals'],
                'dromega' => ['dromega', 'mega'],
                'drosymca' => ['drosymca'],
                default => [$scraperKey],
            };

            $legacy = $conn->table('supplier_connections')
                ->where('supplier_id', $supplierId)
                ->where(function ($q) use ($scraperKey, $hostKeywords) {
                    $q->whereIn('type', ["{$scraperKey}_bot", $scraperKey, 'bot', 'http', 'api']);
                    foreach ($hostKeywords as $kw) {
                        $q->orWhere('host', 'LIKE', "%{$kw}%");
                    }
                })
                ->orderByDesc('id')
                ->first();

            if (!$legacy || (empty($legacy->username) && empty($legacy->password))) {
                return false;
            }

            $authPayload = null;
            if (!empty($legacy->structure)) {
                $decoded = is_string($legacy->structure) ? json_decode($legacy->structure, true) : (array) $legacy->structure;
                if (is_array($decoded) && !empty($decoded['session_token'])) {
                    $authPayload = ['session_token' => $decoded['session_token']];
                }
            }

            static::updateOrCreate(
                [
                    'supplier_id' => $supplierId,
                    'scraper_key' => $scraperKey,
                ],
                [
                    'portal_url' => $legacy->host,
                    'username' => $legacy->username,
                    'password' => $legacy->password,
                    'auth_payload' => $authPayload,
                    'is_active' => true,
                ]
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning("[SupplierScraper] No se pudo migrar conexión genérica ({$scraperKey}) para proveedor {$supplierId}: " . $e->getMessage());

            return false;
        }
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Obtiene la contraseña descifrada del scraper.
     */
    public function getDecryptedPassword(): ?string
    {
        if (empty($this->password)) {
            return null;
        }

        try {
            return \App\Helpers\FtpCrypt::decrypt((string) $this->password);
        } catch (\Throwable) {
            return (string) $this->password;
        }
    }

    /**
     * Obtiene el token de sesión almacenado en auth_payload si existe.
     */
    public function getSessionToken(): ?string
    {
        $payload = is_array($this->auth_payload) ? $this->auth_payload : [];
        $token = $payload['session_token'] ?? null;

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }
}

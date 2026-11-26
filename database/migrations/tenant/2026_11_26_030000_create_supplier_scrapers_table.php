<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier_scrapers')) {
            Schema::create('supplier_scrapers', function (Blueprint $table) {
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
        }

        // Migrar credenciales de bots heredadas desde supplier_connections hacia supplier_scrapers
        if (Schema::hasTable('supplier_connections')) {
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

            $rows = DB::table('supplier_connections')
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

                $exists = DB::table('supplier_scrapers')
                    ->where('supplier_id', $row->supplier_id)
                    ->where('scraper_key', $key)
                    ->exists();

                if (!$exists) {
                    DB::table('supplier_scrapers')->insert([
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

            DB::table('supplier_connections')
                ->whereIn('type', array_keys($botTypes))
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_scrapers');
    }
};

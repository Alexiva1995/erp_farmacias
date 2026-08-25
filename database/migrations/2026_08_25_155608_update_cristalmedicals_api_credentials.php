<?php

use App\Helpers\FtpCrypt;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $token = 'GLyQBJZhVhEgCoGm1QTxKxgeUTR9PWPUEcR6GbWJEAVopyV8Tj9Av8JMuYIbmsF7ZcIZapDPUp3nCJnU';
        $encryptedToken = FtpCrypt::encrypt($token);

        // Buscar proveedor Cristalmedicals por ID 1002 o por nombre
        $supplier = Supplier::where('id', 1002)
            ->orWhere('name', 'LIKE', '%CRISTALMEDICALS%')
            ->orWhere('name', 'LIKE', '%CRISTMEDICALS%')
            ->first();

        if ($supplier) {
            SupplierConnection::updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'type' => 'api',
                ],
                [
                    'host' => 'https://apienterprise.cristmedicals.com',
                    'username' => 'FAR00818',
                    'password' => $encryptedToken,
                    'port' => null,
                    'path' => null,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se requiere revertir
    }
};

<?php

use App\Helpers\FtpCrypt;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. DRONENA
        $dronena = Supplier::where('name', 'LIKE', '%DRONENA%')
            ->orWhere('name', 'LIKE', '%NENA%')
            ->orWhere('id', 1014)
            ->first();

        if ($dronena) {
            SupplierConnection::updateOrCreate(
                ['supplier_id' => $dronena->id],
                [
                    'type'         => 'ftp',
                    'host'         => 'ftp.dronena.com',
                    'port'         => 21,
                    'username'     => 'D719-foraneo',
                    'password'     => FtpCrypt::encrypt('dronena2025'),
                    'path'         => 'Clientes/d719/Inventario.txt',
                    'invoice_path' => 'Clientes/d719/Factura',
                    'pasv'         => false,
                    'has_header'   => true,
                ]
            );
        }

        // 2. DROCERCA
        $drocerca = Supplier::where('name', 'LIKE', '%DROCERCA%')
            ->orWhere('name', 'LIKE', '%CERCA%')
            ->orWhere('id', 1001)
            ->first();

        if ($drocerca) {
            SupplierConnection::updateOrCreate(
                ['supplier_id' => $drocerca->id],
                [
                    'type'         => 'ftp',
                    'host'         => 'drocerca.proteoerp.org',
                    'port'         => 21,
                    'username'     => 'W008B3',
                    'password'     => FtpCrypt::encrypt('J505406957'),
                    'path'         => 'articulos.txt',
                    'invoice_path' => 'facturas/',
                    'pasv'         => false,
                    'has_header'   => true,
                ]
            );
        }

        // 3. DROGUERÍA MEGA (DROMEGA)
        $dromega = Supplier::where('name', 'LIKE', '%DROMEGA%')
            ->orWhere('name', 'LIKE', '%MEGA%')
            ->orWhere('id', 1005)
            ->first();

        if ($dromega) {
            $megaCookie = 'wordpress_test_cookie=WP%20Cookie%20check; wp_lang=es_ES; wordpress_logged_in_39574764368bb892fdea55c61228e833=Farmacia_Barrio_Sucre%7C1789522005%7CYWx0d9WkwLcNilkn5JDCcVxXwC4xCWiXdW5dXvzvmCb%7Cd8a89bfde4906ecd86eabc0061b580cce09bb1b71de7a7f85fe54ec1657bed9d; PHPSESSID=394ae3b6804e7d2b6e052a44b2cdd93d';

            SupplierConnection::updateOrCreate(
                ['supplier_id' => $dromega->id],
                [
                    'type'         => 'api',
                    'host'         => 'https://www.drogueriamega.com',
                    'port'         => null,
                    'username'     => '7586',
                    'password'     => FtpCrypt::encrypt($megaCookie),
                    'path'         => null,
                    'invoice_path' => 'https://www.drogueriamega.com/ventas/estado-de-cuenta/?cliente=7586',
                    'pasv'         => false,
                    'has_header'   => true,
                ]
            );
        }

        // 4. DROSYMCA
        $drosymca = Supplier::where('name', 'LIKE', '%DROSYM%')
            ->orWhere('name', 'LIKE', '%DROSI%')
            ->orWhere('id', 1006)
            ->first();

        if ($drosymca) {
            SupplierConnection::updateOrCreate(
                ['supplier_id' => $drosymca->id],
                [
                    'type'         => 'api',
                    'host'         => 'https://app.drosymca.com',
                    'port'         => null,
                    'username'     => 'farmab.sucre2024@gmail.com',
                    'password'     => FtpCrypt::encrypt('J505406957'),
                    'path'         => 'https://app.drosymca.com/facturas',
                    'invoice_path' => 'https://app.drosymca.com/cobranza',
                    'pasv'         => false,
                    'has_header'   => true,
                ]
            );
        }

        // 5. MAFARTA (COBECA)
        $mafarta = Supplier::where('name', 'LIKE', '%MAFARTA%')
            ->orWhere('name', 'LIKE', '%COBECA%')
            ->orWhere('id', 1011)
            ->first();

        if ($mafarta) {
            SupplierConnection::updateOrCreate(
                ['supplier_id' => $mafarta->id],
                [
                    'type'         => 'api',
                    'host'         => 'https://comparadores.drogueriascobeca.com/api/Login',
                    'port'         => null,
                    'username'     => 'F31373',
                    'password'     => FtpCrypt::encrypt('Mafarta2026*'),
                    'path'         => 'https://comparadores.drogueriascobeca.com/api/articulos/drogueria',
                    'invoice_path' => 'https://comparadores.drogueriascobeca.com/api/facturas/resumen',
                    'pasv'         => false,
                    'has_header'   => true,
                ]
            );
        }

        // 6. CRISTALMEDICALS / CRISTMEDICALS
        $cristmedicals = Supplier::where('name', 'LIKE', '%CRIST%')
            ->orWhere('id', 1002)
            ->first();

        if ($cristmedicals) {
            SupplierConnection::updateOrCreate(
                ['supplier_id' => $cristmedicals->id],
                [
                    'type'         => 'api',
                    'host'         => 'https://apienterprise.cristmedicals.com',
                    'port'         => null,
                    'username'     => 'FAR00818',
                    'password'     => FtpCrypt::encrypt('FAR00818'),
                    'path'         => 'https://apienterprise.cristmedicals.com/api/v1/articulos',
                    'invoice_path' => 'https://apienterprise.cristmedicals.com/api/v1/facturas',
                    'pasv'         => false,
                    'has_header'   => true,
                ]
            );
        }

        // 7. VITALCLINIC
        $vitalclinic = Supplier::where('name', 'LIKE', '%VITAL%')
            ->orWhere('id', 1009)
            ->first();

        if ($vitalclinic) {
            SupplierConnection::updateOrCreate(
                ['supplier_id' => $vitalclinic->id],
                [
                    'type'         => 'ftp',
                    'host'         => '195.35.33.28',
                    'port'         => 21,
                    'username'     => '3613',
                    'password'     => FtpCrypt::encrypt('3613'),
                    'path'         => 'Articulos.txt',
                    'invoice_path' => 'Facturas/3613',
                    'pasv'         => false,
                    'has_header'   => true,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se eliminan conexiones para preservar configuraciones existentes
    }
};

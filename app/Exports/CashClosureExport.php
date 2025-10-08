<?php

namespace App\Exports;

use App\Models\CashClosure;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CashClosureExport implements FromCollection, WithHeadings
{
    protected $cashData;

    public function __construct($cashData)
    {
        $this->cashData = $cashData;
    }

    public function collection()
    {
        // Devuelve una colección a partir del objeto que recibiste
        // Asegúrate de que los datos tengan un formato de colección
        // En este ejemplo, se convierte un objeto a un array dentro de una colección
        return collect([
            [
                'id' => $this->cashData['id'],
                'usd_binance' => $this->cashData['usd_binance'],
                // Agrega el resto de los campos aquí
            ]
        ]);
    }

    public function headings(): array
    {
        // Define los encabezados (nombres de las columnas) del archivo de Excel
        return [
            'ID de Cierre',
            'Monto Binance',
            // Agrega los nombres de los encabezados correspondientes a los campos de arriba
        ];
    }
}

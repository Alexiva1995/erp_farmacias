<?php

namespace App\Exports;

use App\Models\Client;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
// use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClientsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    // /**
    // * @return \Illuminate\Support\Collection
    // */
    // public function collection()
    // {
    //     return Client::all();
    // }
    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query->with('company'); // Carga la relación company
    }

    public function headings(): array
    {
        return [
            '#',
            'Identificación',
            'Tipo ID',
            'Nombre Completo',
            'Teléfono',
            'Email',
            'Empresa',
            'Dirección',
            'Fecha Nacimiento'
        ];
    }

    public function map($client): array
    {
        $fecha = $client->birthdate;
        $formato = "N/A";
        if ($fecha) {
            $fecha = new DateTime($fecha);
            $formato = $fecha->format("d/m/Y");
        }

        return [
            $client->id, // O usa un contador: $this->row++ si prefieres numeración consecutiva
            $client->identification,
            $this->formatIdentificationType($client->identification_type),
            $client->last_name
                ? $client->name . ' ' . $client->last_name
                : $client->name,
            $client->phone ?? 'N/A',
            $client->email ?? 'N/A',
            $client->company->name ?? 'N/A',
            $client->address ?? 'N/A',
            // $client->birthdate->format("d-m-Y") ?? 'N/A'
            $formato,
        ];
    }

    private function formatIdentificationType($type): string
    {
        $types = [
            Client::IDENTIFICATION_TYPE_VENEZOLANO => 'V-',
            Client::IDENTIFICATION_TYPE_JURIDICO => 'J-',
            Client::IDENTIFICATION_TYPE_GOBIERNO => 'G-',
            Client::IDENTIFICATION_TYPE_EXTRANJERO => 'E-'
        ];

        return $types[$type] ?? $type;
    }
}

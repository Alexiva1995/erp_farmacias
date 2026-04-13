<?php

namespace App\Repository;

use App\Models\Client;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ClientRepository
{


    public function create(array $data): Model
    {
        $record = Client::create($data);
        return $record;
    }

    public function edit(array $data): Model
    {
        if (($data['name'] || empty($data['name'])) && ($data['last_name'] || empty($data['last_name'])) && strlen($data['phone']) === 10) {
            $data['status'] = 2;
        }

        if ($data['phone'] === '0') {
            $data['status'] = 1;
        }

        Client::where("id", "=", $data["id"])->update($data);
        return Client::find($data["id"]);
    }

    public function consultById(string $id): ?Model
    {
        $client = Client::query()->with("company")->where("id", "=", $id)->first();
        return $client;
    }

    public function consultByIdentification(string $identification): ?Model
    {
        return Client::query()->with("company")->where("identification", "=", $identification)->first();
    }

    public function consultAll(): Collection
    {
        return Client::query()->with("company")->get();
    }

    public function pending($filters, $perPage = 10): LengthAwarePaginator
    {
        if (empty($filters['status']))
            $filters['status'] = 0;

        $query = $this->builerPaginate($filters);

        return $query->paginate($perPage);
    }

    public function builerPaginate($filtros): Builder
    {
        $consulta = Client::query()
            ->select('clients.*')
            ->addSelect([
                'days_since_last_purchase' => \DB::table('orders')
                    ->selectRaw('DATEDIFF(NOW(), MAX(order_date))') // Sincronizado con stats (usa order_date)
                    ->whereColumn('client_id', 'clients.id')
                    // Sin filtro de status para reflejar actividad real (intento de compra)
            ])
            ->with([
                "company" => function ($query) {
                    $query->withTrashed();
                }
            ]);

        if (array_key_exists("buscardor_filtro", $filtros)) {
            if ($filtros["buscardor_filtro"] != "") {
                $consulta->where(function ($query) use ($filtros) {
                    $query->whereRaw("CONCAT(name,' ',last_name) LIKE ?", ["%{$filtros["buscardor_filtro"]}%"])
                        ->orWhereRaw("CONCAT(last_name,' ',name) LIKE ?", ["%{$filtros["buscardor_filtro"]}%"])
                        ->orWhere("name", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("last_name", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("address", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("id", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("identification", "like", "%" . $filtros["buscardor_filtro"] . "%");
                });
            }
        }

        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if ($filtros["fechaDesde_filtro"] != "" && $filtros["fechaHasta_filtro"] != "") {
                $consulta->whereBetween("created_at", [$filtros["fechaDesde_filtro"], $filtros["fechaHasta_filtro"]]);
            }
        }

        if (!array_key_exists("tipo_identificacion_filtro", $filtros)) {
            if (array_key_exists("tipo", $filtros)) {
                $consulta->whereIn("identification_type", $filtros["tipo"]);
            }
        }


        if (array_key_exists("tipo_identificacion_filtro", $filtros)) {
            if ($filtros["tipo_identificacion_filtro"] != "") {
                $consulta->where("identification_type", $filtros["tipo_identificacion_filtro"]);
            } else {
                $consulta->whereIn("identification_type", $filtros["tipo"]);
            }
        }

        if (array_key_exists("company_id", $filtros)) {
            $consulta->where("company_id", "=", $filtros["company_id"]);
        }

        if (array_key_exists("client_type", $filtros) && $filtros["client_type"] != "") {
            $consulta->where("client_type", "=", $filtros["client_type"]);
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("name", "ASC");
        }

        if (array_key_exists('status', $filtros)) {
            $consulta->where("status", "=", $filtros["status"]);
        }

        if (array_key_exists("has_phone", $filtros) && $filtros["has_phone"] !== null && $filtros["has_phone"] !== "") {
            if ($filtros["has_phone"] === 'yes') {
                $consulta->whereNotNull('phone')->where('phone', '!=', '');
            } elseif ($filtros["has_phone"] === 'no') {
                $consulta->where(function($q) {
                    $q->whereNull('phone')->orWhere('phone', '=', '');
                });
            }
        }

        // $consulta->orderBy("name", "ASC");

        return $consulta;
    }

    public function filterWithoutPaginate($filtros): Collection
    {

        $consulta = $this->builerPaginate($filtros);

        return $consulta->get();
    }

    public function consultAllWithoutCompany(): Collection
    {
        return Client::query()->get();
    }

    public function deleteById(string $id): void
    {
        Client::where("id", "=", $id)->delete();
    }


    public function filtrar($filtros, $perPage = 10): LengthAwarePaginator
    {
        $consulta = $this->builerPaginate($filtros);

        return $consulta->paginate($perPage);
    }

    public function assignCompany(int $client_id, int $company_id): ?Model
    {
        $client = $this->consultById($client_id);
        if (!$client) {
            return null;
        }
        $client->company_id = $company_id;
        $client->save();

        return $client;
    }
    public function removerAssignCompany(int $client_id): ?Model
    {
        $client = $this->consultById($client_id);
        if (!$client) {
            return null;
        }
        $client->company_id = null;
        $client->save();

        return $client;
    }

    public function bulkCleanupInvalid(): int
    {
        // En lugar de borrar, limpiamos los teléfonos basura poniéndolos en NULL
        // Esto afecta a: solo ceros, solo prefijos (0424, 04, etc), o números muy cortos
        return Client::where(function ($query) {
                $query->where('phone', 'REGEXP', '^[0]+$')             // Solo ceros
                    ->orWhere('phone', 'REGEXP', '^04[12][246]$')      // Solo el prefijo (0412, 0424, etc)
                    ->orWhere('phone', 'REGEXP', '^4[12][246]$')       // Solo el prefijo sin el 0
                    ->orWhere('phone', 'REGEXP', '^04$')               // Solo "04"
                    ->orWhere('phone', '12345678')                     // Genérico
                    ->orWhere(function($q) {
                        $q->whereNotNull('phone')
                          ->where('phone', '!=', '')
                          ->whereRaw('LENGTH(phone) < 10');            // Longitud insuficiente para Vzla
                    });
            })
            ->update(['phone' => null]);
    }
}

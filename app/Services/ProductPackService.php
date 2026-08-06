<?php

namespace App\Services;

use App\Contracts\Repositories\ProductPackRepositoryInterface;
use App\Models\ProductPack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductPackService
{
    public function __construct(
        protected ProductPackRepositoryInterface $repository
    ) {}

    public function listPacks(array $filters): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters);
    }

    public function createPack(array $data): ProductPack
    {
        $this->validatePackConfig($data['pack_config'] ?? []);

        return DB::transaction(function () use ($data) {
            return $this->repository->create([
                'name' => $data['name'],
                'pack_config' => $data['pack_config'],
                'total_price' => $data['total_price'],
                'max_quantity' => $data['max_quantity'] ?? null,
                'max_sale_date' => $data['max_sale_date'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function updatePack(ProductPack $pack, array $data): ProductPack
    {
        if (isset($data['pack_config'])) {
            $this->validatePackConfig($data['pack_config']);
        }

        return DB::transaction(function () use ($pack, $data) {
            return $this->repository->update($pack, [
                'name' => $data['name'] ?? $pack->name,
                'pack_config' => $data['pack_config'] ?? $pack->pack_config,
                'total_price' => $data['total_price'] ?? $pack->total_price,
                'max_quantity' => array_key_exists('max_quantity', $data) ? $data['max_quantity'] : $pack->max_quantity,
                'max_sale_date' => array_key_exists('max_sale_date', $data) ? $data['max_sale_date'] : $pack->max_sale_date,
                'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : $pack->is_active,
            ]);
        });
    }

    public function deletePack(ProductPack $pack): bool
    {
        return DB::transaction(function () use ($pack) {
            return $this->repository->delete($pack);
        });
    }

    public function toggleStatus(ProductPack $pack): ProductPack
    {
        return $this->repository->update($pack, [
            'is_active' => !$pack->is_active
        ]);
    }

    protected function validatePackConfig(array $packConfig): void
    {
        if (empty($packConfig)) {
            throw new \InvalidArgumentException('El pack debe contener al menos un producto');
        }

        $productIds = array_keys($packConfig);
        $products = $this->repository->validatePackConfigProducts($productIds);

        $errors = [];

        foreach ($packConfig as $productId => $config) {
            $product = $products->get($productId);

            if (!$product) {
                $errors["product_{$productId}"] = ["El producto con ID {$productId} no existe"];
                continue;
            }

            if (!is_array($config)) {
                $errors["product_{$productId}"] = ["Configuración inválida para el producto {$productId}"];
                continue;
            }

            if (!isset($config['quantity']) || $config['quantity'] < 1) {
                $errors["product_{$productId}"] = ["La cantidad debe ser al menos 1 para el producto {$product->name}"];
            }

            if (isset($config['discount_percentage']) && ($config['discount_percentage'] < 0 || $config['discount_percentage'] > 100)) {
                $errors["product_{$productId}"] = ["El descuento debe estar entre 0% y 100% para el producto {$product->name}"];
            }

            if (isset($config['sale_price']) && $config['sale_price'] < 0) {
                $errors["product_{$productId}"] = ["El precio de venta no puede ser negativo para el producto {$product->name}"];
            }

            if (isset($config['quantity']) && $product->stock < $config['quantity']) {
                $errors["product_{$productId}"] = [
                    "Stock insuficiente para {$product->name}. Disponible: {$product->stock}, Solicitado: {$config['quantity']}"
                ];
            }
        }

        if (!empty($errors)) {
            $exception = new \Illuminate\Validation\ValidationException(validator([], []));
            throw $exception->withMessages($errors);
        }
    }
}

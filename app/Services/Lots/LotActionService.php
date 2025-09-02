<?php

namespace App\Services\Lots;

use App\Models\Product;
use App\Models\ProductLot;
use App\Rules\ValidateLotQuantity;
use DB;
use Illuminate\Support\Facades\Validator;

class LotActionService
{
    public function createLot(array $data)
    {
        $validator = Validator::make($data, [
            'quantity' => ['required', 'integer', 'min:1', new ValidateLotQuantity($data['product_id'])],
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return ProductLot::create($data);
    }

    public function updateLot(ProductLot $productLot, array $data)
    {
        if (isset($data['quantity'])) {
            $validator = Validator::make($data, [
                'quantity' => ['required', 'integer', 'min:0', new ValidateLotQuantity($productLot->product_id, $productLot->id)],
            ]);

            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
        }

        $productLot->update($data);
        return $productLot;
    }

    public function deleteLot(ProductLot $productLot)
    {
        $productLot->update(['quantity' => 0]);
    }

    public function batchUpdateLots(array $data)
    {
        $productId = $data['product_id'];
        $lotsData = $data['lots'];
        $errors = [];

        DB::beginTransaction();

        try {
            $product = Product::find($productId);
            if (!$product) {
                return ['errors' => ['product' => 'Producto no encontrado.']];
            }

            $lotsToUpdate = [];
            $lotsToCreate = [];
            $lotsToDelete = [];

            foreach ($lotsData as $index => $lotData) {
                $isNew = !isset($lotData['id']) || $lotData['id'] <= 0;
                $isMarkedForDeletion = isset($lotData['quantity']) && (int) $lotData['quantity'] === 0;

                if ($isNew && !$isMarkedForDeletion) {
                    $lotsToCreate[] = ['index' => $index, 'data' => $lotData];
                } elseif (!$isNew && $isMarkedForDeletion) {
                    $lotsToDelete[] = ['index' => $index, 'data' => $lotData];
                } elseif (!$isNew && !$isMarkedForDeletion) {
                    $lotsToUpdate[] = ['index' => $index, 'data' => $lotData];
                }
            }
            $currentExistingLotsSum = ProductLot::where('product_id', $productId)->sum('quantity');
            $deletedQuantity = 0;
            foreach ($lotsToDelete as $lot) {
                $existingLot = ProductLot::find($lot['data']['id']);
                if ($existingLot) {
                    $deletedQuantity += $existingLot->quantity;
                }
            }
            $newTotalQuantity = $currentExistingLotsSum - $deletedQuantity;
            foreach ($lotsToUpdate as $lot) {
                $existingLot = ProductLot::find($lot['data']['id']);
                if ($existingLot) {
                    $newTotalQuantity = $newTotalQuantity - $existingLot->quantity + (int) $lot['data']['quantity'];
                }
            }
            foreach ($lotsToCreate as $lot) {
                $newTotalQuantity += (int) $lot['data']['quantity'];
            }
            if ($newTotalQuantity !== $product->stock) {
                $errors['stock'] = "La cantidad total de lotes ({$newTotalQuantity}) debe ser igual al stock del producto ({$product->stock}).";
            }
            $allLotsToValidate = array_merge($lotsToCreate, $lotsToUpdate);

            foreach ($allLotsToValidate as $lotItem) {
                $index = $lotItem['index'];
                $lotData = $lotItem['data'];
                $isNew = !isset($lotData['id']) || $lotData['id'] <= 0;

                $rules = [
                    'lot_number' => 'required|string|max:255',
                    'quantity' => 'required|integer|min:1',
                    'expiration_date' => 'required|date',
                    'unit_cost' => $isNew ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
                    'location' => 'nullable|string|max:100',
                ];

                $validator = Validator::make($lotData, $rules);

                if ($validator->fails()) {
                    $errors["lote_{$index}"] = $validator->errors()->toArray();
                    continue;
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return ['errors' => $errors];
            }

            foreach ($lotsToDelete as $lotItem) {
                $lotData = $lotItem['data'];
                $productLot = ProductLot::find($lotData['id']);
                if ($productLot) {
                    $productLot->update([
                        'quantity' => 0
                    ]);
                }
            }

            foreach ($lotsToUpdate as $lotItem) {
                $lotData = $lotItem['data'];
                $productLot = ProductLot::find($lotData['id']);
                if ($productLot) {
                    $productLot->update([
                        'lot_number' => $lotData['lot_number'],
                        'quantity' => $lotData['quantity'],
                        'expiration_date' => $lotData['expiration_date'],
                        'unit_cost' => $lotData['unit_cost'],
                        'location' => $lotData['location'] ?? null,
                    ]);
                }
            }

            foreach ($lotsToCreate as $lotItem) {
                $lotData = $lotItem['data'];
                ProductLot::create([
                    'product_id' => $productId,
                    'lot_number' => $lotData['lot_number'],
                    'quantity' => $lotData['quantity'],
                    'expiration_date' => $lotData['expiration_date'],
                    'unit_cost' => $lotData['unit_cost'] ?? null,
                    'location' => $lotData['location'] ?? null,
                ]);
            }

            DB::commit();
            return ['success' => true];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

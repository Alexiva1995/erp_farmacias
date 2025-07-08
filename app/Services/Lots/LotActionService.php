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
        $productLot->delete();
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

            $existingLotsIds = collect($lotsData)->pluck('id')->filter()->toArray();
            $currentLotsSum = ProductLot::where('product_id', $productId)
                ->whereNotIn('id', $existingLotsIds)
                ->sum('quantity');

            $newTotalQuantity = $currentLotsSum;

            foreach ($lotsData as $index => $lotData) {
                $isNew = !isset($lotData['id']) || $lotData['id'] <= 0;

                $rules = $isNew ? [
                    'lot_number' => 'required|string|max:255',
                    'quantity' => 'required|integer|min:0',
                    'expiration_date' => 'required|date',
                    'unit_cost' => 'nullable|numeric|min:0',
                    'location' => 'nullable|string|max:100',

                ] : [
                    'lot_number' => 'required|string|max:255',
                    'expiration_date' => 'required|date',
                    'quantity' => 'required|integer|min:0',
                    'unit_cost' => 'required|numeric|min:0',
                    'location' => 'nullable|string|max:100',
                ];

                $validator = Validator::make($lotData, $rules);

                if ($validator->fails()) {
                    $errors["lote_{$index}"] = $validator->errors();
                    continue;
                }

                $validatedData = $validator->validated();
                $newTotalQuantity += $validatedData['quantity'];
            }

            if ($currentLotsSum < $product->stock && $newTotalQuantity > $product->stock) {
                $errors['stock'] = "La cantidad total de lotes ({$newTotalQuantity}) no puede exceder el stock del producto ({$product->stock}).";
            }

            if (!empty($errors)) {
                DB::rollBack();
                return ['errors' => $errors];
            }

            foreach ($lotsData as $index => $lotData) {
                $isNew = !isset($lotData['id']) || $lotData['id'] <= 0;
                $validatedData = Validator::make($lotData, $isNew ? [
                    'lot_number' => 'required|string|max:255',
                    'quantity' => 'required|integer|min:0',
                    'expiration_date' => 'required|date',
                    'unit_cost' => 'nullable|numeric|min:0',
                    'location' => 'nullable|string|max:100',
                ] : [
                    'lot_number' => 'required|string|max:255',
                    'expiration_date' => 'required|date',
                    'quantity' => 'required|integer|min:0',
                    'unit_cost' => 'required|numeric|min:0',
                    'location' => 'nullable|string|max:100',
                ])->validated();

                if ($isNew) {
                    ProductLot::create([
                        'product_id' => $productId,
                        'lot_number' => $validatedData['lot_number'],
                        'quantity' => $validatedData['quantity'],
                        'expiration_date' => $validatedData['expiration_date'],
                        'unit_cost' => $validatedData['unit_cost'] ?? null,
                        'location' => $validatedData['location'] ?? null,
                    ]);
                } else {
                    $productLot = ProductLot::find($lotData['id']);
                    if ($productLot) {
                        $productLot->update($validatedData);
                    }
                }
            }

            DB::commit();
            return ['success' => true];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

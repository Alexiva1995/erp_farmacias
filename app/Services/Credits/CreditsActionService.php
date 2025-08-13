<?php

namespace App\Services\Credits;


use App\Models\Credit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CreditsActionService
{

 public function updateStatus(array $creditIds, string $status): bool
    {
       /* if (!in_array($status, ['Active', 'Paid'])) {
            throw new \InvalidArgumentException("El estado proporcionado no es válido.");
        }

        $credit->update([
            'status' => $status,
        ]);
        return $credit;*/

          try {
            DB::beginTransaction();
            
            Credit::whereIn('id', $creditIds)
                  ->update(['status' => $status]);
            
            DB::commit();
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Puedes registrar el error para depuración
            // Log::error($e->getMessage()); 
            
            return false;
        }
    }

}

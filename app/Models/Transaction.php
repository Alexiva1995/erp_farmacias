<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    // 1. Actualizamos el nombre del campo en fillable
    protected $fillable = [
        "user_id", 
        "category_id", 
        "exchange_rate", // Cambiado de exchange_rate_id a exchange_rate
        "description", 
        "currency", 
        "type", 
        "amount", 
        "movement_type", 
        "transaction_date"
    ];

    // 2. Casting (Opcional pero recomendado)
    // Esto asegura que Laravel siempre trate el valor como un decimal/float
    protected $casts = [
        'exchange_rate' => 'decimal:4',
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        // Nota: Si es una transacción, usualmente es belongsTo(ExpenseCategory::class)
        // ya que la transacción "pertenece" a una categoría.
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * 3. RELACIÓN ELIMINADA:
     * El método public function exchange() ya no debe buscar una relación hasOne
     * porque ahora guardas el VALOR numérico directamente, no un ID.
     */
}

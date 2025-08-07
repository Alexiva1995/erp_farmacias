<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceCountDistribution extends Model
{
    use HasFactory;

    /**
     * El nombre de la tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'invoice_count_distributions';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_count_id',
        'product_lot_id',
        'quantity',
    ];

    /**
     * Define la relación inversa con el conteo de factura.
     */
    public function invoiceCount()
    {
        return $this->belongsTo(InvoiceCount::class, 'invoice_count_id');
    }

    /**
     * Define la relación con el lote de producto.
     */
    public function productLot()
    {
        return $this->belongsTo(ProductLot::class, 'product_lot_id');
    }
}

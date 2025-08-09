<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'suppliers';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'social_reason',
        'sales_phone',
        'collections_phone',
        'credit_days',
        'dispatch_days',
        'order_days',
        'payment_method',
        'cash_payment',
        'charges_igtf',
        'rating',
        'is_deleted'
    ];

    protected $casts = [
        'dispatch_days' => 'array',
        'order_days' => 'array',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     * Esto es muy útil para manejar JSON, booleanos, fechas, etc.
     *
     * @var array<string, string>
     */

    protected $appends = ['debt'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function laboratories(): BelongsToMany
    {
        return $this->belongsToMany(Laboratory::class, 'suppliers_laboratories');
    }

    public function autoOrders()
    {
        return $this->hasMany(AutoOrder::class);
    }

    public function productSuppliers()
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function laboratoryLinks()
    {
        return $this->hasMany(SupplierLaboratory::class);
    }

    public function expirations()
    {
        return $this->hasMany(Expiration::class);
    }

    public function ratings()
    {
        return $this->hasMany(SupplierRating::class);
    }

    public function configProducts()
    {
        return $this->hasMany(SuppliersConfigProduct::class);
    }

    public function paymentRules()
    {
        return $this->hasMany(PaymentRule::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function psychotropicControls()
    {
        return $this->hasMany(PsychotropicControl::class);
    }

    public function scores()
    {
        return $this->hasMany(SupplierScore::class);
    }

    public function latestScore()
    {
        return $this->hasOne(SupplierScore::class)->latestOfMany('evaluated_on');
    }

    public function getDebtAttribute(): float
    {
        return $this->invoices->sum->outstanding_debt;
    }

    public function discounts()
    {
        return $this->hasMany(SupplierDiscount::class);
    }
}

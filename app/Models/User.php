<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'is_active'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
        ];
    }

    public function cashClosings()
    {
        return $this->hasMany(CashClosing::class, 'seller_id');
    }

    public function ordersSold()
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function fiscalHistories()
    {
        return $this->hasMany(FiscalHistory::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'created_by');
    }

    public function reviewedCounts() // supervisor_id
    {
        return $this->hasMany(ProductCount::class, 'supervisor_id');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function payrollDetails()
    {
        return $this->hasMany(PayrollDetail::class);
    }

    public function invoicePayments()
    {
        return $this->hasMany(InvoicePayment::class, 'payment_by');
    }

    public function uploadedInvoices()
    {
        return $this->hasMany(Invoice::class, 'uploaded_by');
    }

    public function registeredInvoices()
    {
        return $this->hasMany(Invoice::class, 'registered_by');
    }

    public function orderedInvoices()
    {
        return $this->hasMany(Invoice::class, 'ordered_by');
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function psychotropicControls()
    {
        return $this->hasMany(PsychotropicControl::class);
    }
}

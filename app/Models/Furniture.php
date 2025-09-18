<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Furniture extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cost',
        'acquisition_year',
        'annual_depreciation_rate',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'annual_depreciation_rate' => 'decimal:2',
        'acquisition_year' => 'integer',
    ];

    /**
     * Calcula el valor actual del mobiliario considerando la depreciación
     * 
     * @return float
     */
    public function getCurrentValue(): float
    {
        $currentYear = Carbon::now()->year;
        $yearsDepreciated = max(0, $currentYear - $this->acquisition_year);

        $totalDepreciation = ($this->annual_depreciation_rate / 100) * $yearsDepreciated;
        $depreciationFactor = max(0, 1 - $totalDepreciation);

        return $this->cost * $depreciationFactor;
    }
}

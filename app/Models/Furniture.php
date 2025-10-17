<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Furniture extends Model
{
    use HasFactory;
    protected $table = 'furnitures';
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

    // ------------------------------------------------------------------
    // ACCESSORS & MUTATORS (Para formateo)
    // ------------------------------------------------------------------

    /**
     * Accesor: Obtiene el costo del mobiliario formateado como moneda.
     * Úsalo con $furniture->formatted_cost
     * * @return string
     */
    public function getFormattedCostAttribute(): string
    {
        // Se usa el locale 'es_ES' para formato en español.
        // Cambia 'EUR' a 'USD', 'MXN', etc., según tu moneda.
        $formatter = new \NumberFormatter('es_ES', \NumberFormatter::CURRENCY);
        return $formatter->format($this->cost);
    }

    /**
     * Accesor: Obtiene la tasa de depreciación formateada como porcentaje.
     * Úsalo con $furniture->formatted_rate
     * * @return string
     */
    public function getFormattedRateAttribute(): string
    {
        return number_format($this->annual_depreciation_rate, 2) . '%';
    }

    // ------------------------------------------------------------------
    // CÁLCULO DE VALOR
    // ------------------------------------------------------------------

    /**
     * Calcula el valor actual del mobiliario usando el método de depreciación
     * lineal. El valor actual no puede ser menor a cero.
     * * @return float
     */
    public function getCurrentValue(): float
    {
        // 1. Convertir la tasa de porcentaje a decimal (ej: 10.00 -> 0.10)
        $rateAsDecimal = $this->annual_depreciation_rate / 100; 

        // 2. Años transcurridos desde la adquisición (no puede ser negativo)
        $currentYear = Carbon::now()->year;
        $yearsDepreciated = max(0, $currentYear - $this->acquisition_year);

        // 3. Factor de Depreciación Acumulada
        // Se limita el factor acumulado a 1.0 (100% de depreciación total)
        $cumulativeDepreciationFactor = min(1.0, $rateAsDecimal * $yearsDepreciated);

        // 4. Factor de Valor Actual (1 - Factor de Depreciación)
        $currentValueFactor = 1 - $cumulativeDepreciationFactor;

        // 5. Calcular el Valor Actual (redondeado a 2 decimales)
        // El uso de 'min(0, ...)' ya no es estrictamente necesario aquí 
        // porque el factor currentValueFactor nunca será negativo.
        return round($this->cost * $currentValueFactor, 2);
    }

    /**
     * Calcula la cantidad total de dinero depreciado hasta la fecha.
     * * @return float
     */
    public function getTotalDepreciationAmount(): float
    {
        $currentValue = $this->getCurrentValue();
        return round($this->cost - $currentValue, 2);
    }
}

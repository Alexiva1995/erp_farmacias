<?php

namespace App\Models\Concerns;

use Carbon\Carbon;

trait HasPromotionSchedule
{
    /**
     * Determina si la promoción está vigente en la fecha, hora y canal indicados.
     */
    public function isCurrentlyValid(?Carbon $moment = null, ?string $channel = null): bool
    {
        $now = $moment ?? Carbon::now();

        if (isset($this->is_active) && !$this->is_active) {
            return false;
        }

        if (!empty($this->start_date) && $now->toDateString() < Carbon::parse($this->start_date)->toDateString()) {
            return false;
        }

        if (!empty($this->end_date) && $now->toDateString() > Carbon::parse($this->end_date)->toDateString()) {
            return false;
        }

        if (!empty($this->days_of_week) && is_array($this->days_of_week)) {
            $isoDay = $now->dayOfWeekIso; // 1 (Lunes) .. 7 (Domingo)
            if (!in_array($isoDay, array_map('intval', $this->days_of_week), true)) {
                return false;
            }
        }

        if (!empty($this->start_time) || !empty($this->end_time)) {
            $currentTime = $now->format('H:i:s');
            $start = !empty($this->start_time) ? substr((string) $this->start_time, 0, 8) : '00:00:00';
            $end = !empty($this->end_time) ? substr((string) $this->end_time, 0, 8) : '23:59:59';

            if (strlen($start) === 5) {
                $start .= ':00';
            }
            if (strlen($end) === 5) {
                $end .= ':59';
            }

            // Soporte para franjas que cruzan medianoche (ej. 22:00 a 02:00)
            if ($start <= $end) {
                if ($currentTime < $start || $currentTime > $end) {
                    return false;
                }
            } else {
                if ($currentTime < $start && $currentTime > $end) {
                    return false;
                }
            }
        }

        if ($channel !== null && !empty($this->sales_channel) && $this->sales_channel !== 'all') {
            if ($this->sales_channel !== $channel) {
                return false;
            }
        }

        return true;
    }
}

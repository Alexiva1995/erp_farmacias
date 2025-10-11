<?php

namespace App\Services\CleaningActivities;

use App\Models\CleaningActivity;

class CleaningActivityActionService
{
    /**
     * Crea una nueva actividad de limpieza.
     *
     * @param array $validatedData
     * @return CleaningActivity
     */
    public function createActivity(array $validatedData): CleaningActivity
    {
        $activity = CleaningActivity::create($validatedData);

        return $activity;
    }

    /**
     * Actualiza una actividad de limpieza existente.
     *
     * @param CleaningActivity $activity
     * @param array $validatedData
     * @return CleaningActivity
     */
    public function updateActivity(CleaningActivity $activity, array $validatedData): CleaningActivity
    {
        $activity->update($validatedData);

        return $activity->fresh();
    }

    /**
     * Elimina una actividad de limpieza.
     *
     * @param CleaningActivity $activity
     */
    public function deleteActivity(CleaningActivity $activity): void
    {
        $activity->delete();
    }
}

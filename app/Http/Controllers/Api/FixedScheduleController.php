<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFixedScheduleRequest;
use App\Models\FixedSchedule;
use App\Mail\FixedScheduleMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Exception;

class FixedScheduleController extends Controller
{
    /**
     * Crear un horario fijo.
     */
    public function store(StoreFixedScheduleRequest $request): JsonResponse
    {
        try {
            // Verificar si choca con algún otro horario fijo
            $conflict = FixedSchedule::where('court_id', $request->court_id)
                ->where('day_of_week', $request->day_of_week)
                ->where(function ($query) use ($request) {
                    $query->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>', $request->start_time);
                })
                ->exists();

            if ($conflict) {
                return response()->json([
                    'message' => 'Este horario fijo choca con otro horario fijo ya registrado para ese día.'
                ], 422);
            }

            $fixedSchedule = FixedSchedule::create($request->validated());

            // Enviar email de notificación
            try {
                Mail::to('Alexisjsoeva95@gmail.com')->send(new FixedScheduleMail($fixedSchedule));
            } catch (Exception $e) {
                // Loguear error pero continuar
                logger()->error("Error al enviar email de horario fijo: " . $e->getMessage());
            }

            return response()->json([
                'message' => 'Horario fijo configurado correctamente.',
                'data' => $fixedSchedule
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un horario fijo.
     */
    public function update(StoreFixedScheduleRequest $request, int $id): JsonResponse
    {
        try {
            $fixedSchedule = FixedSchedule::find($id);

            if (!$fixedSchedule) {
                return response()->json([
                    'message' => 'El horario fijo no existe.'
                ], 404);
            }

            // Verificar choque excluyendo el actual
            $conflict = FixedSchedule::where('court_id', $request->court_id)
                ->where('day_of_week', $request->day_of_week)
                ->where('id', '!=', $id)
                ->where(function ($query) use ($request) {
                    $query->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>', $request->start_time);
                })
                ->exists();

            if ($conflict) {
                return response()->json([
                    'message' => 'Este horario fijo choca con otro horario fijo ya registrado para ese día.'
                ], 422);
            }

            $fixedSchedule->update($request->validated());

            return response()->json([
                'message' => 'Horario fijo actualizado correctamente.',
                'data' => $fixedSchedule
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un horario fijo.
     */
    public function destroy(\Illuminate\Http\Request $request, int $id): JsonResponse
    {
        try {
            $fixedSchedule = FixedSchedule::find($id);

            if (!$fixedSchedule) {
                return response()->json([
                    'message' => 'El horario fijo no existe.'
                ], 404);
            }

            $dateParam = $request->query('date');

            if ($dateParam) {
                // Crear excepción para esa semana específica si no existe ya
                \App\Models\FixedScheduleException::firstOrCreate([
                    'fixed_schedule_id' => $id,
                    'date' => $dateParam
                ]);

                // Comprobar excepciones acumuladas en las últimas semanas (ejemplo: si tiene 4 excepciones consecutivas)
                // Obtenemos las excepciones ordenadas por fecha
                $exceptionsCount = \App\Models\FixedScheduleException::where('fixed_schedule_id', $id)
                    ->orderBy('date', 'desc')
                    ->take(4)
                    ->get();

                if ($exceptionsCount->count() >= 4) {
                    // Si ha sido cancelado 4 veces (o 4 semanas consecutivas)
                    $fixedSchedule->delete();
                    return response()->json([
                        'message' => 'Horario fijo eliminado permanentemente al acumular 4 cancelaciones consecutivas.'
                    ]);
                }

                return response()->json([
                    'message' => 'Horario fijo cancelado únicamente para la fecha seleccionada.'
                ]);
            }

            // Borrado inmediato permanente si no se pasa 'date'
            $fixedSchedule->delete();

            return response()->json([
                'message' => 'Horario fijo eliminado correctamente de todas las semanas.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

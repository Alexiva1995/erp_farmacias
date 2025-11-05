<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorOffer;
use App\Models\DoctorOfferScale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorOfferController extends Controller
{
    /**
     * Obtener lista de las ofertas.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DoctorOffer::with(['doctor', 'scales'])
            ->join('doctors', 'doctor_offers.doctor_id', '=', 'doctors.id')
            ->select('doctor_offers.*', 'doctors.name as doctor_name'); // Seleccionar columnas específicas

        // Búsqueda por ID de médico o nombre
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('doctors.id', 'LIKE', "%{$search}%")
                    ->orWhere('doctors.name', 'LIKE', "%{$search}%")
                    ->orWhere('doctor_offers.id', 'LIKE', "%{$search}%");
            });
        }

        // Ordenamiento - especificar explícitamente la tabla
        $sortBy = $request->get('sort_by', 'doctor_offers.id');
        $sortOrder = $request->get('sort_order', 'desc');

        // Mapear nombres de columnas amigables a nombres reales de base de datos
        $sortMapping = [
            'id' => 'doctor_offers.id',
            'doctor.name' => 'doctors.name',
            'start_date' => 'doctor_offers.start_date',
            'end_date' => 'doctor_offers.end_date',
            'is_active' => 'doctor_offers.is_active',
        ];

        $sortColumn = $sortMapping[$sortBy] ?? $sortBy;

        $query->orderBy($sortColumn, $sortOrder);

        $doctorOffers = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $doctorOffers->items(),
            'total' => $doctorOffers->total(),
            'current_page' => $doctorOffers->currentPage(),
            'per_page' => $doctorOffers->perPage(),
            'last_page' => $doctorOffers->lastPage()
        ]);
    }

    /**
     * Creacion de la oferta.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'required|boolean',
            'scales' => 'required|array|min:1',
            'scales.*.min_volume' => 'required|integer|min:0',
            'scales.*.max_volume' => 'required|integer|min:0|gt:scales.*.min_volume',
            'scales.*.discount_percentage' => 'required|numeric|min:0|max:100',
        ]);

        try {
            DB::beginTransaction();

            // Crear la oferta principal
            $doctorOffer = DoctorOffer::create([
                'doctor_id' => $validated['doctor_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'is_active' => $validated['is_active'],
            ]);

            // Crear las escalas de la oferta
            foreach ($validated['scales'] as $scale) {
                DoctorOfferScale::create([
                    'doctor_offer_id' => $doctorOffer->id,
                    'min_volume' => $scale['min_volume'],
                    'max_volume' => $scale['max_volume'],
                    'discount_percentage' => $scale['discount_percentage'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Oferta creada exitosamente',
                'data' => $doctorOffer->load(['doctor', 'scales'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la oferta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizacion de una oferta.
     */

    public function update(Request $request, $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Buscar el modelo manualmente
            $doctorOffer = DoctorOffer::find($id);

            if (!$doctorOffer) {
                return response()->json([
                    'message' => 'Oferta no encontrada.',
                    'error' => 'La Oferta Medica con ID ' . $id . ' no fue encontrada'
                ], 404);
            }

            $validated = $request->validate([
                'doctor_id' => 'required|exists:companies,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'is_active' => 'required|boolean',
                'scales' => 'required|array|min:1',
                'scales.*.min_volume' => 'required|integer|min:0',
                'scales.*.max_volume' => 'required|integer|min:0|gt:scales.*.min_volume',
                'scales.*.discount_percentage' => 'required|numeric|min:0|max:100',
            ]);

            // Update the main offer
            $updated = $doctorOffer->update([
                'doctor_id' => $validated['doctor_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'is_active' => $validated['is_active'],
            ]);

            // Eliminar escalas existentes
            $deleted = DoctorOfferScale::where('doctor_offer_id', $doctorOffer->id)->delete();

            // Crear nuevas escalas
            $scalesToCreate = [];
            foreach ($validated['scales'] as $index => $scaleData) {
                $scalesToCreate[] = [
                    'doctor_offer_id' => $doctorOffer->id,
                    'min_volume' => $scaleData['min_volume'],
                    'max_volume' => $scaleData['max_volume'],
                    'discount_percentage' => $scaleData['discount_percentage'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insertar todas las escalas
            if (!empty($scalesToCreate)) {
                $inserted = DoctorOfferScale::insert($scalesToCreate);
            }

            DB::commit();

            // Recargar con datos frescos
            $doctorOffer->load(['doctor', 'scales']);

            return response()->json([
                'message' => 'Oferta actualizada exitosamente.',
                'data' => $doctorOffer
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al actualizar la oferta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una oferta.
     */

    public function destroy($id): JsonResponse
    {
        DB::beginTransaction();
        try {

            // Buscar la oferta manualmente
            $doctorOffer = DoctorOffer::find($id);

            if (!$doctorOffer) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Oferta no encontrada.',
                    'error' => 'Oferta Medica con ID ' . $id . ' no encontrada'
                ], 404);
            }

            // 1. Eliminar escalas primero (de forma explícita)
            $scalesDeleted = DoctorOfferScale::where('doctor_offer_id', $id)->delete();

            // 2. Eliminar la oferta principal
            $offerDeleted = $doctorOffer->delete();

            if (!$offerDeleted) {
                throw new \Exception('Fallo al eliminar oferta medica');
            }

            DB::commit();

            // Verificar que realmente se eliminó
            $stillExists = DoctorOffer::find($id);

            return response()->json([
                'message' => 'Oferta eliminada exitosamente.',
                'success' => true
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al eliminar la oferta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

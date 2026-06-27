<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Services\ReservationServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class ReservationController extends Controller
{
    protected ReservationServices $reservationServices;

    public function __construct(ReservationServices $reservationServices)
    {
        $this->reservationServices = $reservationServices;
    }

    /**
     * Obtener canchas y su disponibilidad para una fecha específica.
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date', date('Y-m-d'));
        
        $availability = $this->reservationServices->getAvailability($date);

        return response()->json([
            'data' => $availability
        ]);
    }

    /**
     * Crear una reserva pendiente.
     */
    public function store(StoreReservationRequest $request): JsonResponse
    {
        try {
            $reservation = $this->reservationServices->createReservation($request->validated());

            $message = auth()->check()
                ? 'Pre-reserva creada correctamente. Se ha enviado un mensaje de confirmación a WhatsApp.'
                : 'Reserva registrada correctamente.';

            return response()->json([
                'message' => $message,
                'data' => new ReservationResource($reservation)
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Webhook para recibir confirmaciones de WhatsApp.
     */
    public function webhook(Request $request): JsonResponse
    {
        $sender = $request->input('sender'); // Número de teléfono (WhatsApp) del cliente
        $message = $request->input('message'); // Mensaje recibido

        if (!$sender || !$message) {
            return response()->json(['message' => 'Datos incompletos.'], 400);
        }

        $verified = $this->reservationServices->verifyReservationByWhatsapp($sender, $message);

        if ($verified) {
            return response()->json(['message' => 'Reserva verificada con éxito.']);
        }

        return response()->json(['message' => 'No se encontró ninguna reserva pendiente para este remitente o el mensaje no es de confirmación.'], 404);
    }

    /**
     * Confirmar reserva directamente a través de enlace de correo electrónico.
     */
    public function confirmDirect(int $id): \Illuminate\Http\Response
    {
        $reservation = \App\Models\Reservation::find($id);

        if (!$reservation) {
            return response("<div style='font-family: Arial, sans-serif; text-align: center; margin-top: 50px;'><h3>La reserva no existe.</h3></div>", 404);
        }

        if ($reservation->status === 'verified') {
            return response("
                <div style='font-family: Arial, sans-serif; text-align: center; margin-top: 50px; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 500px; margin-left: auto; margin-right: auto;'>
                    <h2 style='color: #25D366;'>✅ Reserva ya verificada</h2>
                    <p>Esta reserva ya se encuentra verificada y confirmada en el sistema.</p>
                </div>
            ");
        }

        $reservation->update(['status' => 'verified']);

        // Transmitir en tiempo real
        broadcast(new \App\Events\ReservationUpdated($reservation))->toOthers();

        // Notificar a Telegram
        $this->reservationServices->sendTelegramNotification($reservation, 'verified');

        // Notificar al cliente
        $this->reservationServices->sendWhatsAppMessage(
            $reservation->client_whatsapp,
            "¡Excelente! Tu reserva para la cancha de '{$reservation->court->name}' el día {$reservation->date->format('d/m/Y')} a las {$reservation->start_time} ha sido VERIFICADA exitosamente. ¡Te esperamos!"
        );

        return response("
            <div style='font-family: Arial, sans-serif; text-align: center; margin-top: 50px; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 500px; margin-left: auto; margin-right: auto;'>
                <h2 style='color: #E20074;'>⚽ ¡Reserva Confirmada con Éxito!</h2>
                <p>La reserva de <strong>{$reservation->client_name}</strong> para la cancha <strong>{$reservation->court->name}</strong> ha sido verificada en el sistema.</p>
                <p>Se ha enviado un mensaje de confirmación al WhatsApp del cliente.</p>
                <br>
                <button onclick='window.close()' style='background-color: #E20074; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;'>Cerrar Ventana</button>
            </div>
        ");
    }

    /**
     * Cancelar (eliminar) una reserva.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $reservation = \App\Models\Reservation::findOrFail($id);
            $this->reservationServices->cancelReservation($reservation);

            return response()->json([
                'message' => 'Reserva cancelada correctamente.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Confirmar reserva directamente desde el botón público de WhatsApp.
     */
    public function publicConfirm(int $id): JsonResponse
    {
        try {
            $reservation = \App\Models\Reservation::findOrFail($id);
            
            // Si ya está verificada, omitimos repetir lógica
            if ($reservation->status !== 'verified') {
                $reservation->update(['status' => 'verified']);

                // Notificar en tiempo real por WebSockets
                broadcast(new \App\Events\ReservationUpdated($reservation))->toOthers();

                // 1. Notificar al administrador vía Telegram
                $this->reservationServices->sendTelegramNotification($reservation, 'verified');

                // 2. Intentar enviar confirmación por WhatsApp mediante Evolution API en segundo plano
                try {
                    $this->reservationServices->sendWhatsAppMessage(
                        $reservation->client_whatsapp,
                        "¡Excelente! Tu reserva para la cancha de '{$reservation->court->name}' el día {$reservation->date->format('d/m/Y')} a las " . \Carbon\Carbon::parse($reservation->start_time)->format('g:i A') . " ha sido VERIFICADA exitosamente. ¡Te esperamos!"
                    );
                } catch (\Exception $e) {
                    \Log::error("Error al enviar confirmación de WhatsApp: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Reserva confirmada exitosamente.',
                'data' => new \App\Http\Resources\ReservationResource($reservation)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al confirmar la reserva: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Actualizar el estado de una reservación (ej: iniciar, no asistió).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:pending,verified,canceled,in_progress,no_show,completed'
            ]);

            $reservation = \App\Models\Reservation::findOrFail($id);
            $newStatus = $request->input('status');
            
            // Si el estado es 'in_progress', guardar hora de inicio real si es necesario o simplemente el status
            $reservation->update([
                'status' => $newStatus
            ]);

            // Transmitir en tiempo real
            broadcast(new \App\Events\ReservationUpdated($reservation))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Estado de la reservación actualizado correctamente.',
                'reservation' => $reservation
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
}

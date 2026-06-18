<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Reservation $reservation;

    /**
     * Create a new event instance.
     */
    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('reservations')
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ReservationUpdated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->reservation->id,
            'court_id' => $this->reservation->court_id,
            'date' => $this->reservation->date ? $this->reservation->date->format('Y-m-d') : null,
            'start_time' => substr($this->reservation->start_time, 0, 5),
            'end_time' => substr($this->reservation->end_time, 0, 5),
            'client_name' => $this->reservation->client_name,
            'status' => $this->reservation->status,
        ];
    }
}

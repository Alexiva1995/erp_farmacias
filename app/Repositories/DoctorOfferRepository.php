<?php

namespace App\Repositories;

use App\Contracts\Repositories\DoctorOfferRepositoryInterface;
use App\Models\DoctorOffer;
use App\Models\DoctorOfferScale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DoctorOfferRepository implements DoctorOfferRepositoryInterface
{
    public function getPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = DoctorOffer::query()
            ->with(['doctor:id,name', 'scales'])
            ->join('doctors', 'doctor_offers.doctor_id', '=', 'doctors.id')
            ->select('doctor_offers.*', 'doctors.name as doctor_name');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('doctors.id', 'LIKE', "%{$search}%")
                  ->orWhere('doctors.name', 'LIKE', "%{$search}%")
                  ->orWhere('doctor_offers.id', 'LIKE', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'doctor_offers.id';
        $sortOrder = strtolower($filters['sort_order'] ?? $filters['order_by'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $sortMapping = [
            'id' => 'doctor_offers.id',
            'doctor.name' => 'doctors.name',
            'start_date' => 'doctor_offers.start_date',
            'end_date' => 'doctor_offers.end_date',
            'is_active' => 'doctor_offers.is_active',
        ];

        $sortColumn = $sortMapping[$sortBy] ?? $sortBy;
        $query->orderBy($sortColumn, $sortOrder);

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 10;

        return $query->paginate($perPage);
    }

    public function create(array $data): DoctorOffer
    {
        $doctorOffer = DoctorOffer::create($data);
        return $doctorOffer->load('doctor');
    }

    public function update(DoctorOffer $doctorOffer, array $data): DoctorOffer
    {
        return DB::transaction(function () use ($doctorOffer, $data) {
            $doctorOffer->update($data);
            return $doctorOffer->fresh('doctor');
        });
    }

    public function delete(DoctorOffer $doctorOffer): bool
    {
        return DB::transaction(function () use ($doctorOffer) {
            DoctorOfferScale::where('doctor_offer_id', $doctorOffer->id)->delete();
            return (bool) $doctorOffer->delete();
        });
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CleaningActivity\StoreCleaningActivityRequest;
use App\Http\Requests\CleaningActivity\UpdateCleaningActivityRequest;
use App\Http\Resources\CleaningActivityResource;
use App\Models\CleaningActivity;
use App\Services\CleaningActivities\CleaningActivityActionService;
use App\Services\CleaningActivities\CleaningActivityQueryService;
use Illuminate\Http\Request;

class CleaningActivityController extends Controller
{
    public function __construct(
        private CleaningActivityQueryService $queryService,
        private CleaningActivityActionService $actionService
    ) {
    }

    public function index(Request $request)
    {
        $query = $this->queryService->getFilteredQuery($request);
        $perPage = (int) $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json([
                'data' => CleaningActivityResource::collection($items),
                'total' => $items->count()
            ]);
        }

        $paginatedResult = $query->paginate($perPage);
        return response()->json([
            'data' => CleaningActivityResource::collection($paginatedResult->items()),
            'total' => $paginatedResult->total()
        ]);
    }

    public function store(StoreCleaningActivityRequest $request)
    {
        $activity = $this->actionService->createActivity($request->validated());

        return response()->json([
            'message' => 'Actividad creada con éxito.',
            'activity' => new CleaningActivityResource($activity)
        ], 201);
    }

    public function update(UpdateCleaningActivityRequest $request, CleaningActivity $cleaningActivity)
    {
        $updatedActivity = $this->actionService->updateActivity($cleaningActivity, $request->validated());

        return response()->json([
            'message' => 'Actividad actualizada con éxito.',
            'activity' => new CleaningActivityResource($updatedActivity)
        ], 200);
    }

    public function destroy(CleaningActivity $cleaningActivity)
    {
        $this->actionService->deleteActivity($cleaningActivity);
        return response()->noContent();
    }
}


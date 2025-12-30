<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Dentist;
use App\Models\DentistWorkingHour;
use App\Models\Service;
use App\Services\DentistAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DentistController extends Controller
{
    use AuthorizesPermissions;

    public function index(): JsonResponse
    {
        $dentists = Dentist::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $dentists,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'catalog.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'working_hours' => ['nullable', 'array'],
            'working_hours.*.day_of_week' => ['required_with:working_hours', 'integer', 'min:0', 'max:6'],
            'working_hours.*.start_time' => ['required_with:working_hours', 'date_format:H:i'],
            'working_hours.*.end_time' => ['required_with:working_hours', 'date_format:H:i'],
            'working_hours.*.break_start_time' => ['nullable', 'date_format:H:i'],
            'working_hours.*.break_end_time' => ['nullable', 'date_format:H:i'],
            'working_hours.*.slot_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'working_hours.*.is_active' => ['nullable', 'boolean'],
        ]);

        $dentist = Dentist::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'specialty' => $validated['specialty'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['working_hours'])) {
            foreach ($validated['working_hours'] as $wh) {
                DentistWorkingHour::updateOrCreate(
                    [
                        'dentist_id' => $dentist->id,
                        'day_of_week' => $wh['day_of_week'],
                    ],
                    [
                        'start_time' => $wh['start_time'],
                        'end_time' => $wh['end_time'],
                        'break_start_time' => $wh['break_start_time'] ?? null,
                        'break_end_time' => $wh['break_end_time'] ?? null,
                        'slot_minutes' => $wh['slot_minutes'] ?? 30,
                        'is_active' => $wh['is_active'] ?? true,
                    ],
                );
            }
        }

        return response()->json([
            'data' => $dentist->load('workingHours'),
        ], 201);
    }

    public function availability(Request $request, Dentist $dentist, DentistAvailabilityService $availabilityService): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
        ]);

        $date = CarbonImmutable::parse($validated['date'])->startOfDay();
        $serviceDurationMinutes = (int) ($validated['duration_minutes'] ?? 0);
        $bufferMinutes = (int) ($validated['buffer_minutes'] ?? 0);

        if (isset($validated['service_id'])) {
            $service = Service::findOrFail($validated['service_id']);
            $serviceDurationMinutes = (int) $service->duration_minutes;
            $bufferMinutes = (int) $service->buffer_minutes;
        }

        return response()->json([
            'data' => [
                'dentist_id' => $dentist->id,
                'date' => $date->toDateString(),
                'slots' => $availabilityService->getDailyAvailability($dentist, $date, $serviceDurationMinutes, $bufferMinutes),
            ],
        ]);
    }
}

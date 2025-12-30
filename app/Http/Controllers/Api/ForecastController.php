<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dentist;
use App\Services\BusyDayForecastService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function busyDays(Request $request, BusyDayForecastService $forecastService): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'dentist_id' => ['nullable', 'integer', 'exists:dentists,id'],
            'lookback_days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'busy_threshold' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'almost_threshold' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $from = CarbonImmutable::parse($validated['from'])->startOfDay();
        $to = CarbonImmutable::parse($validated['to'])->startOfDay();
        $dentistId = isset($validated['dentist_id']) ? (int) $validated['dentist_id'] : null;
        $lookbackDays = (int) ($validated['lookback_days'] ?? 56);
        $busyThreshold = isset($validated['busy_threshold']) ? (int) $validated['busy_threshold'] : null;
        $almostThreshold = isset($validated['almost_threshold']) ? (int) $validated['almost_threshold'] : null;

        $days = $forecastService->forecastBusyDays($from, $to, $dentistId, $lookbackDays);

        if ($busyThreshold !== null) {
            $almostThreshold = $almostThreshold ?? max(0, $busyThreshold - 1);

            $days = array_map(function (array $day) use ($busyThreshold, $almostThreshold) {
                $predicted = (int) ($day['predicted_count'] ?? 0);

                if ($predicted >= $busyThreshold) {
                    $day['traffic_light'] = 'red';
                    $day['level'] = 'high';
                } elseif ($predicted >= $almostThreshold) {
                    $day['traffic_light'] = 'yellow';
                    $day['level'] = 'medium';
                } else {
                    $day['traffic_light'] = 'green';
                    $day['level'] = 'low';
                }

                return $day;
            }, $days);
        }

        $leastBusy = $days;
        usort($leastBusy, fn (array $a, array $b) => $a['predicted_count'] <=> $b['predicted_count']);
        $suggestedDates = array_slice(array_map(fn (array $d) => $d['date'], $leastBusy), 0, 3);

        $suggestedDentists = [];
        if ($dentistId !== null) {
            $suggestedDentists = Dentist::query()
                ->where('is_active', true)
                ->where('id', '!=', $dentistId)
                ->orderBy('name')
                ->limit(3)
                ->get(['id', 'name']);
        }

        return response()->json([
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'dentist_id' => $dentistId,
                'lookback_days' => $lookbackDays,
                'busy_threshold' => $busyThreshold,
                'almost_threshold' => $almostThreshold,
                'days' => $days,
                'suggested_dates' => $suggestedDates,
                'suggested_dentists' => $suggestedDentists,
            ],
        ]);
    }
}

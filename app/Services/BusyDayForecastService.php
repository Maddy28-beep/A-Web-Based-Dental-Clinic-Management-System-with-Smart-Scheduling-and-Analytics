<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\CarbonImmutable;

class BusyDayForecastService
{
    public function forecastBusyDays(CarbonImmutable $from, CarbonImmutable $to, ?int $dentistId = null, int $lookbackDays = 56): array
    {
        if ($to->lessThan($from)) {
            return [];
        }

        $lookbackEnd = $from->subDay()->endOfDay();
        $lookbackStart = $lookbackEnd->subDays(max(1, $lookbackDays - 1))->startOfDay();

        $appointmentsQuery = Appointment::query()
            ->where('status', '!=', 'cancelled')
            ->where('start_at', '>=', $lookbackStart)
            ->where('start_at', '<=', $lookbackEnd);

        if ($dentistId !== null) {
            $appointmentsQuery->where('dentist_id', $dentistId);
        }

        $appointments = $appointmentsQuery->get(['start_at']);

        $countByDate = [];
        foreach ($appointments as $appointment) {
            $dateKey = CarbonImmutable::parse($appointment->start_at)->toDateString();
            $countByDate[$dateKey] = ($countByDate[$dateKey] ?? 0) + 1;
        }

        $weekdayTotals = array_fill(0, 7, 0);
        $weekdayDays = array_fill(0, 7, 0);

        for ($cursor = $lookbackStart; $cursor->lessThanOrEqualTo($lookbackEnd); $cursor = $cursor->addDay()) {
            $weekday = $cursor->dayOfWeek;
            $weekdayDays[$weekday]++;
            $weekdayTotals[$weekday] += $countByDate[$cursor->toDateString()] ?? 0;
        }

        $weekdayAverages = [];
        $maxAverage = 0.0;
        for ($w = 0; $w < 7; $w++) {
            $avg = $weekdayDays[$w] > 0 ? ($weekdayTotals[$w] / $weekdayDays[$w]) : 0.0;
            $weekdayAverages[$w] = $avg;
            $maxAverage = max($maxAverage, $avg);
        }

        $results = [];
        for ($cursor = $from->startOfDay(); $cursor->lessThanOrEqualTo($to->startOfDay()); $cursor = $cursor->addDay()) {
            $avg = $weekdayAverages[$cursor->dayOfWeek] ?? 0.0;
            $predicted = (int) round($avg);
            $score = $maxAverage > 0 ? (int) round(($avg / $maxAverage) * 100) : 0;
            $trafficLight = $score >= 80 ? 'red' : ($score >= 50 ? 'yellow' : 'green');

            $results[] = [
                'date' => $cursor->toDateString(),
                'predicted_count' => $predicted,
                'busy_score' => $score,
                'level' => $score >= 80 ? 'high' : ($score >= 50 ? 'medium' : 'low'),
                'traffic_light' => $trafficLight,
            ];
        }

        usort($results, fn (array $a, array $b) => $b['predicted_count'] <=> $a['predicted_count']);

        return $results;
    }
}

<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Tooth;

class ToothChartingService
{
    /**
     * @return array<int, string>
     */
    public function toothCodes(string $dentition): array
    {
        if ($dentition === 'pediatric') {
            return [
                '55', '54', '53', '52', '51', '61', '62', '63', '64', '65',
                '85', '84', '83', '82', '81', '71', '72', '73', '74', '75',
            ];
        }

        return [
            '18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28',
            '48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38',
        ];
    }

    /**
     * @return array<int, Tooth>
     */
    public function ensureTeethExist(Patient $patient, string $dentition): array
    {
        $dentition = $dentition === 'pediatric' ? 'pediatric' : 'adult';
        $codes = $this->toothCodes($dentition);

        $existing = Tooth::query()
            ->where('patient_id', $patient->id)
            ->whereIn('tooth_code', $codes)
            ->get()
            ->keyBy('tooth_code');

        $toCreate = [];
        foreach ($codes as $code) {
            if (! $existing->has($code)) {
                $toCreate[] = [
                    'patient_id' => $patient->id,
                    'tooth_code' => $code,
                    'dentition' => $dentition,
                    'condition' => 'healthy',
                    'severity' => 'healthy',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($toCreate) {
            Tooth::insert($toCreate);
        }

        $teeth = Tooth::query()
            ->where('patient_id', $patient->id)
            ->whereIn('tooth_code', $codes)
            ->get()
            ->all();

        $order = array_flip($codes);
        usort($teeth, fn (Tooth $a, Tooth $b) => ($order[$a->tooth_code] ?? 0) <=> ($order[$b->tooth_code] ?? 0));

        return $teeth;
    }

    public function severityFromCondition(string $condition): string
    {
        $c = strtolower(trim($condition));

        if (in_array($c, ['urgent', 'pain', 'infection', 'abscess', 'swelling'], true)) {
            return 'urgent';
        }

        if (in_array($c, ['needs_attention', 'cavity', 'decay', 'sensitive', 'gum_issue'], true)) {
            return 'attention';
        }

        return 'healthy';
    }
}

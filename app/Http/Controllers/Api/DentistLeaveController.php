<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Dentist;
use App\Models\DentistTimeOff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DentistLeaveController extends Controller
{
    use AuthorizesPermissions;

    public function store(Request $request, Dentist $dentist): JsonResponse
    {
        $this->requireAnyPermission($request, [
            'dentist.manage_leave_any',
            'dentist.manage_leave_self',
        ]);

        $user = $request->user();
        if ($user && $user->hasPermission('dentist.manage_leave_self')) {
            $byEmail = $user->email ? Dentist::query()->where('email', $user->email)->value('id') : null;
            $byName = Dentist::query()->where('name', $user->name)->value('id');
            $matchId = $byEmail ?: $byName;

            if (! $matchId || (int) $matchId !== (int) $dentist->id) {
                abort(403);
            }
        }

        $validated = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $timeOff = DentistTimeOff::create([
            'dentist_id' => $dentist->id,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'data' => $timeOff,
        ], 201);
    }
}

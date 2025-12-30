<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use AuthorizesPermissions;

    public function index(): JsonResponse
    {
        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $services,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'catalog.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'is_active' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'max:50'],
        ]);

        $service = Service::create([
            'name' => $validated['name'],
            'duration_minutes' => (int) $validated['duration_minutes'],
            'buffer_minutes' => (int) ($validated['buffer_minutes'] ?? 0),
            'is_active' => $validated['is_active'] ?? true,
            'color' => $validated['color'] ?? null,
        ]);

        return response()->json([
            'data' => $service,
        ], 201);
    }
}

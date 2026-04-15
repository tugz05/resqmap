<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return match ($user->role) {
            UserRole::Resident => $this->residentDashboard($user),
            default            => Inertia::render('Dashboard'),
        };
    }

    private function residentDashboard(\App\Models\User $user): Response
    {
        $incidents = Incident::where('reporter_id', $user->id)
            ->with(['assignments.rescuer:id,name'])
            ->orderByDesc('reported_at')
            ->limit(20)
            ->get();

        // Resolve each resource individually to produce a plain array instead of
        // a ResourceCollection (which Inertia serialises as { data: [...] }).
        $resolved = $incidents
            ->map(fn (Incident $i) => (new IncidentResource($i))->resolve())
            ->values()
            ->all();

        return Inertia::render('resident/Dashboard', [
            'incidents' => $resolved,
            'stats'     => [
                'total'    => $incidents->count(),
                'active'   => $incidents->filter(fn (Incident $i) => $i->isActive())->count(),
                'resolved' => $incidents->where('status', IncidentStatus::Resolved)->count(),
            ],
        ]);
    }
}

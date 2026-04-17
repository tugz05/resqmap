<?php

namespace App\Http\Controllers;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\AssignmentStatus;
use App\Enums\UserRole;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\User;
use App\Services\Agentic\OperationsAgentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        return match ($user->role) {
            UserRole::Admin => redirect()->route('admin.dashboard'),
            UserRole::Rescuer => redirect()->route('rescuer.dashboard'),
            UserRole::Resident => $this->residentDashboard($user),
            default            => Inertia::render('Dashboard'),
        };
    }

    /**
     * Analytics-only admin dashboard: KPIs, trends, charts, insights.
     * The incident queue lives on its own page at /admin/incidents.
     */
    public function adminDashboardOverview(): Response
    {
        $now = now();
        $last7Start = $now->copy()->subDays(6)->startOfDay();
        $prev7Start = $now->copy()->subDays(13)->startOfDay();
        $prev7End = $now->copy()->subDays(7)->endOfDay();

        $last30Start = $now->copy()->subDays(29)->startOfDay();

        // --- Time-bounded pulls (kept light) -----------------------------------
        $incidents30d = Incident::query()
            ->where('reported_at', '>=', $last30Start)
            ->get([
                'id', 'type', 'severity', 'status',
                'ai_verdict', 'ai_confidence', 'ai_dispatch',
                'barangay', 'city', 'province',
                'reported_at', 'verified_at', 'dispatched_at', 'resolved_at',
            ]);

        $incidents7d = $incidents30d->filter(
            fn (Incident $i) => $i->reported_at && $i->reported_at->greaterThanOrEqualTo($last7Start),
        );

        $incidentsPrev7d = $incidents30d->filter(
            fn (Incident $i) => $i->reported_at
                && $i->reported_at->betweenIncluded($prev7Start, $prev7End),
        );

        // --- KPI totals & trends -----------------------------------------------
        $totalAll = Incident::count();
        $resolvedAll = Incident::where('status', IncidentStatus::Resolved->value)->count();
        $activeAll = Incident::whereIn('status', [
            IncidentStatus::Pending->value,
            IncidentStatus::Verified->value,
            IncidentStatus::Dispatched->value,
            IncidentStatus::EnRoute->value,
            IncidentStatus::OnScene->value,
        ])->count();

        $reports7d = $incidents7d->count();
        $reportsPrev7d = $incidentsPrev7d->count();
        $reportsTrendPct = $reportsPrev7d > 0
            ? (int) round((($reports7d - $reportsPrev7d) / $reportsPrev7d) * 100)
            : null;

        // --- Response time averages (minutes) ----------------------------------
        $avgMinutes = static fn (iterable $items, string $from, string $to): ?int => (function () use ($items, $from, $to): ?int {
            $durations = [];
            foreach ($items as $item) {
                if ($item->{$from} && $item->{$to}) {
                    $durations[] = abs($item->{$to}->diffInMinutes($item->{$from}));
                }
            }

            return $durations === [] ? null : (int) round(array_sum($durations) / count($durations));
        })();

        $avgReportToVerify = $avgMinutes($incidents30d, 'reported_at', 'verified_at');
        $avgVerifyToDispatch = $avgMinutes($incidents30d, 'verified_at', 'dispatched_at');
        $avgDispatchToResolve = $avgMinutes($incidents30d, 'dispatched_at', 'resolved_at');
        $avgEndToEnd = $avgMinutes($incidents30d, 'reported_at', 'resolved_at');

        // --- Daily trend (last 7 days, aligned) --------------------------------
        $dailyTrend = collect(range(6, 0))
            ->map(function (int $daysAgo) use ($now, $incidents7d): array {
                $day = $now->copy()->subDays($daysAgo);
                $count = $incidents7d->filter(
                    fn (Incident $i) => $i->reported_at?->toDateString() === $day->toDateString(),
                )->count();

                return [
                    'date' => $day->toDateString(),
                    'label' => $day->format('M j'),
                    'weekday' => $day->format('D'),
                    'count' => $count,
                ];
            })
            ->all();

        // --- Group by type / severity / status ---------------------------------
        $incidentsByType = $incidents30d
            ->groupBy(fn (Incident $i) => $i->type->value)
            ->map(fn ($group, $type) => [
                'key' => $type,
                'label' => IncidentType::from($type)->label(),
                'icon' => IncidentType::from($type)->icon(),
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $incidentsBySeverity = collect(IncidentSeverity::cases())
            ->map(fn (IncidentSeverity $sev) => [
                'key' => $sev->value,
                'label' => $sev->label(),
                'count' => $incidents30d->where('severity', $sev)->count(),
            ])
            ->values()
            ->all();

        $incidentsByStatus = collect(IncidentStatus::cases())
            ->map(fn (IncidentStatus $st) => [
                'key' => $st->value,
                'label' => $st->label(),
                'count' => $incidents30d->where('status', $st)->count(),
            ])
            ->values()
            ->all();

        // --- Top barangays / cities --------------------------------------------
        $topBarangays = $incidents30d
            ->filter(fn (Incident $i) => filled($i->barangay))
            ->groupBy(fn (Incident $i) => $i->barangay.'|'.($i->city ?? ''))
            ->map(fn ($group) => [
                'barangay' => $group->first()->barangay,
                'city' => $group->first()->city,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(6)
            ->values()
            ->all();

        // --- AI performance metrics --------------------------------------------
        $aiVerified = $incidents30d->filter(fn (Incident $i) => $i->ai_verdict !== null);
        $aiVerdictDist = collect(['true', 'false', 'uncertain'])
            ->map(fn (string $verdict) => [
                'verdict' => $verdict,
                'count' => $aiVerified->where('ai_verdict', $verdict)->count(),
            ])
            ->all();
        $aiAvgConfidence = $aiVerified->count() > 0
            ? (int) round($aiVerified->avg('ai_confidence') ?? 0)
            : 0;

        $aiDispatchStats = $incidents30d->filter(fn (Incident $i) => is_array($i->ai_dispatch) && isset($i->ai_dispatch['candidates']));
        $aiDispatchAvgKm = null;
        $aiDispatchAvgEta = null;
        if ($aiDispatchStats->count() > 0) {
            $topDistances = $aiDispatchStats->map(fn (Incident $i) => $i->ai_dispatch['candidates'][0]['distance_km'] ?? null)->filter();
            $topEtas = $aiDispatchStats->map(fn (Incident $i) => $i->ai_dispatch['candidates'][0]['eta_minutes'] ?? null)->filter();
            $aiDispatchAvgKm = $topDistances->count() > 0 ? round($topDistances->avg(), 1) : null;
            $aiDispatchAvgEta = $topEtas->count() > 0 ? (int) round($topEtas->avg()) : null;
        }

        // --- Rescuer status ----------------------------------------------------
        $totalRescuers = User::query()->role(UserRole::Rescuer)->count();
        $onlineRescuers = User::query()
            ->role(UserRole::Rescuer)
            ->whereHas('location', fn ($q) => $q->where('located_at', '>=', $now->copy()->subMinutes(15)))
            ->count();
        $busyRescuers = User::query()
            ->role(UserRole::Rescuer)
            ->whereHas('assignments', fn ($q) => $q->whereNotIn('status', [
                \App\Enums\AssignmentStatus::Completed->value,
                \App\Enums\AssignmentStatus::Cancelled->value,
            ]))
            ->count();

        // --- Coverage ----------------------------------------------------------
        $coverage = [
            'municipalities' => Incident::query()->whereNotNull('city')->distinct('city')->count('city'),
            'barangays' => Incident::query()
                ->whereNotNull('barangay')
                ->distinct(DB::raw("concat(barangay,'|',coalesce(city,''))"))
                ->count(),
            'provinces' => Incident::query()->whereNotNull('province')->distinct('province')->count('province'),
        ];

        return Inertia::render('admin/Dashboard', [
            'period' => ['label' => 'Last 30 days', 'window' => '30d'],
            'kpi' => [
                'total_all' => $totalAll,
                'active' => $activeAll,
                'resolved' => $resolvedAll,
                'resolution_rate' => $totalAll > 0 ? (int) round(($resolvedAll / $totalAll) * 100) : 0,
                'reports_7d' => $reports7d,
                'reports_prev_7d' => $reportsPrev7d,
                'reports_trend_pct' => $reportsTrendPct,
                'critical_30d' => $incidents30d->filter(
                    fn (Incident $i) => in_array($i->severity->value, ['high', 'critical'], true),
                )->count(),
            ],
            'responseTimes' => [
                'report_to_verify' => $avgReportToVerify,
                'verify_to_dispatch' => $avgVerifyToDispatch,
                'dispatch_to_resolve' => $avgDispatchToResolve,
                'end_to_end' => $avgEndToEnd,
            ],
            'dailyTrend' => $dailyTrend,
            'incidentsByType' => $incidentsByType,
            'incidentsBySeverity' => $incidentsBySeverity,
            'incidentsByStatus' => $incidentsByStatus,
            'topBarangays' => $topBarangays,
            'aiStats' => [
                'verified_count' => $aiVerified->count(),
                'avg_confidence' => $aiAvgConfidence,
                'verdict_distribution' => $aiVerdictDist,
                'dispatch_runs' => $aiDispatchStats->count(),
                'dispatch_avg_km' => $aiDispatchAvgKm,
                'dispatch_avg_eta' => $aiDispatchAvgEta,
            ],
            'rescuerStats' => [
                'total' => $totalRescuers,
                'online' => $onlineRescuers,
                'busy' => $busyRescuers,
                'available' => max(0, $totalRescuers - $busyRescuers),
            ],
            'coverage' => $coverage,
        ]);
    }

    public function adminIncidentsIndex(): Response
    {
        $incidents = Incident::query()
            ->with([
                'reporter:id,name',
                'reporter.location',
                'assignments.rescuer:id,name,role',
                'assignments.rescuer.location',
                'assignments.rescuer.rescuerProfile',
            ])
            ->orderByRaw("case when status = 'pending' then 0 when status = 'verified' then 1 else 2 end")
            ->orderByDesc('reported_at')
            ->limit(60)
            ->get();

        $incidentsResolved = $incidents
            ->map(fn (Incident $incident) => (new IncidentResource($incident))->resolve())
            ->values()
            ->all();

        $rescuers = User::query()
            ->role(UserRole::Rescuer)
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/IncidentQueue', [
            'currentSection' => 'all',
            'incidents' => $incidentsResolved,
            'rescuers' => $rescuers,
            'overview' => [
                'total_reports' => $incidents->count(),
                'pending' => $incidents->where('status', IncidentStatus::Pending)->count(),
                'verified' => $incidents->where('status', IncidentStatus::Verified)->count(),
                'dispatched' => $incidents->where('status', IncidentStatus::Dispatched)->count(),
                'resolved' => $incidents->where('status', IncidentStatus::Resolved)->count(),
                'high_priority' => $incidents->filter(
                    fn (Incident $incident) => in_array($incident->severity->value, ['high', 'critical'], true)
                )->count(),
            ],
            'coverage' => [
                'municipalities' => Incident::query()->whereNotNull('city')->distinct('city')->count('city'),
                'barangays' => Incident::query()->whereNotNull('barangay')->distinct(DB::raw("concat(barangay,'|',coalesce(city,''))"))->count(),
            ],
        ]);
    }

    /**
     * Manage Users & Roles — admin, rescuer, resident rosters.
     */
    public function adminUsers(): Response
    {
        $freshCutoff = now()->subMinutes(15);

        $users = User::query()
            ->with([
                'rescuerProfile:id,user_id,agency_name,unit_name,specialization,badge_number,is_active',
                'residentProfile:id,user_id,barangay,municipality',
                'location:id,user_id,located_at',
            ])
            ->withCount([
                'reportedIncidents as reported_incidents_count',
                'assignments as active_assignments_count' => fn ($q) => $q->whereNotIn('status', [
                    AssignmentStatus::Completed->value,
                    AssignmentStatus::Cancelled->value,
                ]),
            ])
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        $rows = $users->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role->value,
            'email_verified' => $u->email_verified_at !== null,
            'created_at' => $u->created_at?->toISOString(),
            'last_seen_at' => $u->location?->located_at?->toISOString(),
            'is_online' => $u->location?->located_at?->greaterThanOrEqualTo($freshCutoff) ?? false,
            'agency' => $u->rescuerProfile?->agency_name,
            'specialization' => $u->rescuerProfile?->specialization,
            'barangay' => $u->residentProfile?->barangay,
            'active_assignments' => (int) ($u->active_assignments_count ?? 0),
            'reported_incidents' => (int) ($u->reported_incidents_count ?? 0),
        ])->values()->all();

        $onlineRescuers = $users->filter(
            fn (User $u) => $u->role === UserRole::Rescuer
                && $u->location?->located_at?->greaterThanOrEqualTo($freshCutoff),
        )->count();

        return Inertia::render('admin/Users', [
            'users' => $rows,
            'metrics' => [
                'total' => $users->count(),
                'admins' => $users->where('role', UserRole::Admin)->count(),
                'rescuers' => $users->where('role', UserRole::Rescuer)->count(),
                'residents' => $users->where('role', UserRole::Resident)->count(),
                'verified' => $users->filter(fn (User $u) => $u->email_verified_at !== null)->count(),
                'unverified' => $users->filter(fn (User $u) => $u->email_verified_at === null)->count(),
                'online_rescuers' => $onlineRescuers,
            ],
        ]);
    }

    /**
     * Manage Resources & Responders — rescuer roster, agencies, specializations.
     */
    public function adminResponders(): Response
    {
        $freshCutoff = now()->subMinutes(15);

        $rescuers = User::query()
            ->role(UserRole::Rescuer)
            ->with([
                'rescuerProfile',
                'location:id,user_id,latitude,longitude,located_at',
            ])
            ->withCount([
                'assignments as active_assignments_count' => fn ($q) => $q->whereNotIn('status', [
                    AssignmentStatus::Completed->value,
                    AssignmentStatus::Cancelled->value,
                ]),
                'assignments as completed_assignments_count' => fn ($q) => $q->where(
                    'status',
                    AssignmentStatus::Completed->value,
                ),
            ])
            ->orderBy('name')
            ->get();

        $rows = $rescuers->map(fn (User $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'email' => $r->email,
            'agency' => $r->rescuerProfile?->agency_name,
            'unit' => $r->rescuerProfile?->unit_name,
            'badge' => $r->rescuerProfile?->badge_number,
            'contact' => $r->rescuerProfile?->contact_number,
            'specialization' => $r->rescuerProfile?->specialization,
            'is_active' => (bool) ($r->rescuerProfile?->is_active ?? false),
            'is_online' => $r->location?->located_at?->greaterThanOrEqualTo($freshCutoff) ?? false,
            'last_seen_at' => $r->location?->located_at?->toISOString(),
            'active_assignments' => (int) ($r->active_assignments_count ?? 0),
            'completed_assignments' => (int) ($r->completed_assignments_count ?? 0),
            'latitude' => $r->location?->latitude ? (float) $r->location->latitude : null,
            'longitude' => $r->location?->longitude ? (float) $r->location->longitude : null,
        ])->values()->all();

        $online = collect($rows)->where('is_online', true)->count();
        $busy = collect($rows)->where('active_assignments', '>', 0)->count();
        $total = count($rows);
        $avgLoad = $total > 0 ? round(collect($rows)->avg('active_assignments'), 1) : 0;

        $agencies = collect($rows)
            ->filter(fn (array $r) => filled($r['agency']))
            ->groupBy('agency')
            ->map(fn ($group, $name) => [
                'agency' => $name,
                'count' => $group->count(),
                'online' => $group->where('is_online', true)->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $specializations = collect($rows)
            ->filter(fn (array $r) => filled($r['specialization']))
            ->groupBy('specialization')
            ->map(fn ($group, $name) => [
                'name' => $name,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        return Inertia::render('admin/Responders', [
            'responders' => $rows,
            'metrics' => [
                'total' => $total,
                'active' => collect($rows)->where('is_active', true)->count(),
                'online' => $online,
                'busy' => $busy,
                'available' => max(0, $online - $busy),
                'avg_load' => $avgLoad,
            ],
            'agencies' => $agencies,
            'specializations' => $specializations,
        ]);
    }

    /**
     * System Settings & Configuration — read-only overview of platform config.
     */
    public function adminSettings(): Response
    {
        return Inertia::render('admin/Settings', [
            'settings' => [
                'app_name' => config('app.name'),
                'app_env' => config('app.env'),
                'timezone' => config('app.timezone'),
                'ai' => [
                    'truth_verifier_enabled' => filled(config('services.openai.api_key')),
                    'operations_agent_enabled' => filled(config('services.openai.api_key')),
                    'tts_enabled' => filled(config('services.openai.api_key')),
                    'openai_model' => config('services.openai.model', 'gpt-4o-mini'),
                    'openai_configured' => filled(config('services.openai.api_key')),
                ],
                'dispatch' => [
                    'search_radius_km' => (float) config('services.dispatch.search_radius_km', 15),
                    'max_candidates' => (int) config('services.dispatch.max_candidates', 5),
                    'fresh_window_minutes' => (int) config('services.dispatch.fresh_window_minutes', 15),
                ],
                'map' => [
                    'default_latitude' => (float) config('services.map.default_latitude', 13.4125),
                    'default_longitude' => (float) config('services.map.default_longitude', 122.5625),
                    'default_zoom' => (int) config('services.map.default_zoom', 11),
                ],
                'counts' => [
                    'incidents' => Incident::count(),
                    'users' => User::count(),
                    'rescuers' => User::query()->role(UserRole::Rescuer)->count(),
                ],
            ],
        ]);
    }

    private function residentDashboard(\App\Models\User $user): Response
    {
        $incidents = Incident::where('reporter_id', $user->id)
            ->with([
                'assignments.rescuer:id,name,role',
                'assignments.rescuer.location',
                'assignments.rescuer.rescuerProfile',
            ])
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

    public function rescuerDashboard(Request $request, OperationsAgentService $operationsAgent): Response
    {
        $rescuer = $request->user();

        $assignments = IncidentAssignment::query()
            ->with([
                'incident.reporter:id,name',
                'incident.reporter.location',
                'assigner:id,name',
            ])
            ->where('rescuer_id', $rescuer->id)
            ->orderByRaw("case when status = 'assigned' then 0 when status = 'accepted' then 1 else 2 end")
            ->orderByDesc('assigned_at')
            ->limit(25)
            ->get();

        $missionCards = $assignments->map(function (IncidentAssignment $assignment) use ($operationsAgent): array {
            $brief = null;

            try {
                $brief = $operationsAgent->buildRescuerMissionBrief(
                    incident: $assignment->incident,
                    assignmentNotes: $assignment->notes,
                );
            } catch (Throwable $e) {
                report($e);
            }

            return [
                'assignment_id' => $assignment->id,
                'status' => $assignment->status->value,
                'status_label' => $assignment->status->label(),
                'assigned_at' => $assignment->assigned_at?->toISOString(),
                'notes' => $assignment->notes,
                'incident' => (new IncidentResource($assignment->incident))->resolve(),
                'assigner' => [
                    'name' => $assignment->assigner?->name,
                ],
                'ai_mission_brief' => $brief,
            ];
        })->values()->all();

        return Inertia::render('rescuer/Dashboard', [
            'assignments' => $missionCards,
            'overview' => [
                'total_assignments' => $assignments->count(),
                'active' => $assignments->whereNotIn('status', [AssignmentStatus::Completed, AssignmentStatus::Cancelled])->count(),
                'assigned' => $assignments->where('status', AssignmentStatus::Assigned)->count(),
                'on_scene' => $assignments->where('status', AssignmentStatus::OnScene)->count(),
            ],
        ]);
    }
}

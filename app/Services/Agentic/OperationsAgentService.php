<?php

namespace App\Services\Agentic;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Collection;
use OpenAI\Laravel\Facades\OpenAI;

class OperationsAgentService
{
    /**
     * Build an AI-driven command center recommendation for admin dispatching.
     *
     * The AI agent here handles only judgment-based outputs (urgency,
     * truth assessment, dispatch decision, plan, resident update).
     * Rescuer selection is delegated to the deterministic DispatchAgentService
     * (nearest-location ranking), not the LLM.
     *
     * @param  Collection<int, User>  $rescuerCandidates  Pre-ranked nearest rescuers from DispatchAgentService (context only).
     */
    public function generateAdminCommandCenterPlan(Incident $incident, Collection $rescuerCandidates): array
    {
        $payload = [
            'incident' => [
                'id' => $incident->ulid,
                'type' => $incident->type->value,
                'severity' => $incident->severity->value,
                'status' => $incident->status->value,
                'title' => $incident->title,
                'description' => $incident->description,
                'address' => $incident->address,
                'barangay' => $incident->barangay,
                'city' => $incident->city,
                'province' => $incident->province,
                'ai_verification' => [
                    'verdict' => $incident->ai_verdict,
                    'confidence' => $incident->ai_confidence,
                    'summary' => $incident->ai_summary,
                    'recommended_action' => $incident->ai_recommended_action,
                ],
            ],
            'nearest_rescuers_context' => $rescuerCandidates->map(fn (User $rescuer): array => [
                'id' => $rescuer->id,
                'name' => $rescuer->name,
                'active_assignments' => (int) ($rescuer->active_assignments_count ?? 0),
                'location' => $rescuer->location ? [
                    'latitude' => (float) $rescuer->location->latitude,
                    'longitude' => (float) $rescuer->location->longitude,
                    'located_at' => $rescuer->location->located_at?->toISOString(),
                ] : null,
            ])->values()->all(),
        ];

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => <<<'PROMPT'
You are ResQMap Command Center AI for Philippine DRRMO operations.
Your task is to produce a compact decision brief for admin users.

IMPORTANT: Do NOT pick specific rescuers. Rescuer selection is handled by a
separate deterministic Dispatch Agent that ranks rescuers by nearest location.
Assume the nearest rescuer will be dispatched.

Return ONLY valid JSON with these keys:
- urgency_level: low|medium|high|critical
- incident_truth_assessment: short sentence (references verification verdict)
- dispatch_decision: dispatch_now|manual_review|hold
- responder_plan: 2-4 bullet-style short strings (what the nearest rescuer should do)
- resident_update_cebuano: one concise Cebuano update for resident
- command_notes: one concise operational note for admin
PROMPT,
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
            'max_tokens' => 500,
        ]);

        $raw = trim((string) ($response->choices[0]->message->content ?? '{}'));
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $urgency = $decoded['urgency_level'] ?? 'medium';
        if (! in_array($urgency, ['low', 'medium', 'high', 'critical'], true)) {
            $urgency = 'medium';
        }

        $dispatchDecision = $decoded['dispatch_decision'] ?? 'manual_review';
        if (! in_array($dispatchDecision, ['dispatch_now', 'manual_review', 'hold'], true)) {
            $dispatchDecision = 'manual_review';
        }

        $planSteps = collect($decoded['responder_plan'] ?? [])
            ->filter(static fn ($step): bool => is_string($step) && trim($step) !== '')
            ->map(static fn (string $step): string => trim($step))
            ->values()
            ->take(4)
            ->all();

        return [
            'urgency_level' => $urgency,
            'incident_truth_assessment' => (string) ($decoded['incident_truth_assessment'] ?? 'Incident needs additional validation.'),
            'dispatch_decision' => $dispatchDecision,
            'responder_plan' => $planSteps,
            'resident_update_cebuano' => (string) ($decoded['resident_update_cebuano'] ?? 'Nadawat na ang imong report ug gi-review na sa among team.'),
            'command_notes' => (string) ($decoded['command_notes'] ?? 'Proceed with standard validation and dispatch protocol.'),
            'model' => 'gpt-4o-mini',
            'generated_at' => now()->toISOString(),
        ];
    }

    public function buildRescuerMissionBrief(Incident $incident, ?string $assignmentNotes): string
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => <<<'PROMPT'
You are a mission briefing assistant for rescue responders.
Create a clear and practical briefing for a non-technical rescuer.
Keep it concise: 3 to 5 bullet points only.
Use plain English.
PROMPT,
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'incident' => [
                            'type' => $incident->type->value,
                            'severity' => $incident->severity->value,
                            'title' => $incident->title,
                            'description' => $incident->description,
                            'address' => $incident->address,
                            'barangay' => $incident->barangay,
                            'city' => $incident->city,
                            'status' => $incident->status->value,
                        ],
                        'assignment_notes' => $assignmentNotes,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
            'temperature' => 0.3,
            'max_tokens' => 200,
        ]);

        return trim((string) ($response->choices[0]->message->content ?? 'Proceed carefully and follow standard incident response protocol.'));
    }
}

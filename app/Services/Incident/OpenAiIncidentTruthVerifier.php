<?php

namespace App\Services\Incident;

use App\Models\Incident;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAiIncidentTruthVerifier
{
    public function verify(Incident $incident): array
    {
        $content = [
            [
                'type' => 'text',
                'text' => $this->incidentSummaryPrompt($incident),
            ],
            ...$this->photoInputs($incident),
        ];

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => <<<'PROMPT'
Ikaw usa ka incident authenticity evaluator sa ResQMap.
Tan-awa ang report details ug images aron ma-assess kung tinood ba ang report.

Tubaga ra sa JSON object nga adunay eksaktong keys:
- verdict: true | false | uncertain
- confidence: integer 0-100
- summary_cebuano: mubo nga rason sa Cebuano (1-2 sentence)
- red_flags: array sa strings
- recommended_action: verify_now | manual_review | reject_or_escalate
PROMPT,
                ],
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1,
            'max_tokens' => 300,
        ]);

        $raw = trim((string) ($response->choices[0]->message->content ?? '{}'));
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $verdict = $decoded['verdict'] ?? 'uncertain';
        if (! in_array($verdict, ['true', 'false', 'uncertain'], true)) {
            $verdict = 'uncertain';
        }

        $confidence = (int) ($decoded['confidence'] ?? 0);
        $confidence = max(0, min(100, $confidence));

        return [
            'verdict' => $verdict,
            'confidence' => $confidence,
            'summary_cebuano' => (string) ($decoded['summary_cebuano'] ?? 'Walay igo nga datos para sa klarong desisyon.'),
            'red_flags' => array_values(array_filter($decoded['red_flags'] ?? [], static fn ($v): bool => is_string($v) && $v !== '')),
            'recommended_action' => in_array(($decoded['recommended_action'] ?? ''), ['verify_now', 'manual_review', 'reject_or_escalate'], true)
                ? $decoded['recommended_action']
                : 'manual_review',
            'source' => 'openai_vision',
            'model' => 'gpt-4o-mini',
        ];
    }

    private function incidentSummaryPrompt(Incident $incident): string
    {
        $photos = is_array($incident->photo_paths) ? count($incident->photo_paths) : 0;

        return <<<TEXT
I-verify kung posibleng tinuod ang incident report base sa detalye ug images.

TYPE: {$incident->type->value}
SEVERITY: {$incident->severity->value}
TITLE: {$incident->title}
DESCRIPTION: {$incident->description}
ADDRESS: {$incident->address}
BARANGAY: {$incident->barangay}
CITY: {$incident->city}
PROVINCE: {$incident->province}
LATITUDE: {$incident->latitude}
LONGITUDE: {$incident->longitude}
PHOTO_COUNT: {$photos}
TEXT;
    }

    private function photoInputs(Incident $incident): array
    {
        $paths = array_slice($incident->photo_paths ?? [], 0, 3);
        $inputs = [];

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            $size = Storage::disk('public')->size($path);
            if ($size > 2 * 1024 * 1024) {
                continue;
            }

            $binary = Storage::disk('public')->get($path);
            $mime = $this->mimeTypeFromPath($path);

            $inputs[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => sprintf('data:%s;base64,%s', $mime, base64_encode($binary)),
                ],
            ];
        }

        return $inputs;
    }

    private function mimeTypeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}

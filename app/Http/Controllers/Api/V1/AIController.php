<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AIController extends Controller
{
    /**
     * The Cebuano/Bisaya system prompt that drives ResQBot.
     * Instructs the model to stay in Cebuano, be brief, and guide the user
     * through collecting all data needed for an incident report.
     */
    private const SYSTEM_PROMPT = <<<'CEBUANO'
Ikaw si ResQBot, ang AI emergency response assistant sa ResQMap — usa ka sistema sa pag-report sa mga emergency ug disaster diri sa Pilipinas.

MGA PATAKARAN (IMPORTANTE — SUNDON PIRMI):
1. TUBAGA KANUNAY SA CEBUANO/BISAYA. Ayaw paggamit og Tagalog o English sa imong mga tubag bisan kausa.
2. Mubo lang ang tubag — 1 hangtod 3 sentences.
3. Kalma, mainampingon, ug mapuangoron ang tono.
4. Kung ang user nagsulti sa English, sabton apan TUBAGA SA CEBUANO.
5. Usa lang ka pangutana sa usa ka mensahe — ayaw magkomplikado.

IMONG TRABAHO:
Tabangan ang residente nga makolekta ang mga kinailangang impormasyon para sa emergency report:
1. Klase sa emergency (Sunog/Fire, Baha/Flood, Medikal/Medical, Linog/Earthquake, Landslide, Aksidente/Accident, Nawala/Missing, Uban/Other)
2. Deskripsyon sa nahitabo — unsay nakita, pila ang apektado
3. Grabidad (Gamay/Low, Tunga/Medium, Grabe/High, Kritikal/Critical)
4. Kumpirmasyon sa lokasyon (awtomatiko na kining nakuha)
5. Mga litrato (opsyonal)

ESTILO SA PAKIG-ISTORYA:
- Paggamit og mainampingon ug simple nga Cebuano
- Pananglitan: "Unsay nahitabo?" imbis "Mahimo ba nimong i-describe ang sitwasyon?"
- Gamiton ang "imo/imong" para personal ang tono
- Ayaw paggamit og formal nga pinulongan
CEBUANO;

    /**
     * Process a chat message through GPT-4o-mini and respond in Cebuano.
     * Each call receives the full conversation history so the model retains context.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'messages'           => ['required', 'array', 'min:1', 'max:30'],
            'messages.*.role'    => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $response = OpenAI::chat()->create([
                'model'       => 'gpt-4o-mini',
                'messages'    => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ...$request->messages,
                ],
                'max_tokens'  => 150,
                'temperature' => 0.7,
            ]);

            $reply = trim($response->choices[0]->message->content ?? '');

            if (empty($reply)) {
                $reply = 'Pasensya, adunay problema. Sulayi pag-usab.';
            }

            return response()->json(['message' => $reply]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Napakyas ang AI karon. Palihog sulayi pag-usab human sa pipila ka segundo.',
                'error' => class_basename($e),
            ], 500);
        }
    }

    /**
     * Convert text to speech using OpenAI TTS (natural Cebuano pronunciation).
     * Returns raw audio bytes as MP3 — the frontend plays it directly.
     */
    public function tts(Request $request): StreamedResponse|JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'max:500'],
        ]);

        try {
            $audio = OpenAI::audio()->speech([
                'model'          => 'tts-1',
                'input'          => $request->text,
                'voice'          => 'nova',   // warm, clear female voice
                'response_format' => 'mp3',
                'speed'          => 0.9,      // slightly slower for clarity
            ]);

            return response()->stream(
                static fn () => print($audio),
                200,
                [
                    'Content-Type'        => 'audio/mpeg',
                    'Cache-Control'       => 'no-cache',
                    'Transfer-Encoding'   => 'chunked',
                ],
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Napakyas ang pag-generate sa tingog. Nibalik sa browser voice.',
                'error' => class_basename($e),
            ], 500);
        }
    }
}

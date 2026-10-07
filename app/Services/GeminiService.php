<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    public function analyzeHydroponicSystem(array $payload): string
    {
        $apiKey = config('services.gemini.key');

        if (! $apiKey) {
            throw new RuntimeException('Falta GEMINI_API_KEY en el archivo .env');
        }

        $model = config('services.gemini.model', 'gemini-2.0-flash');

        $prompt = $this->buildPrompt($payload);

        $response = Http::timeout(45)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'maxOutputTokens' => 1024,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Error al consultar Gemini: '.$response->body());
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Gemini no devolvió una respuesta válida.');
        }

        return trim($text);
    }

    private function buildPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Eres un asesor experto en hidroponía (NFT / raíz flotante) para un sistema escolar llamado Hydrolab. Responde en español, claro y práctico, usando SOLO los parámetros medidos.

Datos actuales del sistema:
{$json}

Reglas:
- No inventes lecturas. Si un sensor viene en null o "sin datos", dilo y recomienda cómo medirlo.
- La temperatura del agua es el parámetro que hoy sí envía el ESP32; priorízala.
- Ten en cuenta el cultivo indicado y si la bomba está encendida o apagada.
- Si la temperatura supera 28 °C (o el máximo del cultivo), trata el calor como riesgo para las raíces.

Incluye exactamente estas secciones:
1. Estado general del cultivo (1-2 frases).
2. Parámetros (qué está óptimo, alto, bajo o ausente).
3. Recomendaciones concretas y accionables.
4. Prioridad de acciones (alta / media / baja).
PROMPT;
    }
}

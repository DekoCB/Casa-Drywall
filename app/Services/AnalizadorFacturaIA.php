<?php

namespace App\Services;

use Anthropic\Client;

/**
 * Extrae los datos de una factura de proveedor en PDF usando Claude.
 *
 * Migrado del handler `analizar_pdf_ia` de `administrador/facturas.php`, que
 * llamaba a la API de Anthropic con cURL. Aquí se usa el SDK oficial de PHP.
 */
class AnalizadorFacturaIA
{
    /**
     * @return array{ok: bool, error?: string, ...}
     */
    public function analizar(string $rutaPdf): array
    {
        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            return ['ok' => false, 'error' => 'Falta ANTHROPIC_API_KEY en la configuración.'];
        }

        $client = new Client(apiKey: $apiKey);

        try {
            $mensaje = $client->messages->create(
                maxTokens: 4096,
                messages: [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'document',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => 'application/pdf',
                                'data' => base64_encode(file_get_contents($rutaPdf)),
                            ],
                        ],
                        ['type' => 'text', 'text' => $this->prompt()],
                    ],
                ]],
                model: 'claude-opus-5',
                thinking: ['type' => 'adaptive'],
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Error al consultar la IA: '.$e->getMessage()];
        }

        // Las clasificaciones de seguridad pueden rechazar la solicitud; en ese
        // caso el contenido llega vacío o parcial y no hay nada que interpretar.
        if (($mensaje->stopReason ?? null) === 'refusal') {
            return ['ok' => false, 'error' => 'El modelo rechazó analizar este documento.'];
        }

        $texto = '';
        foreach ($mensaje->content as $bloque) {
            if (($bloque->type ?? null) === 'text') {
                $texto .= $bloque->text;
            }
        }

        // El modelo a veces envuelve el JSON en un bloque markdown.
        $texto = trim(preg_replace('/```json|```/', '', $texto));
        $resultado = json_decode($texto, true);

        if (! is_array($resultado)) {
            return ['ok' => false, 'error' => 'Respuesta de IA inválida: '.mb_substr($texto, 0, 200)];
        }

        return ['ok' => true] + $resultado;
    }

    private function prompt(): string
    {
        return <<<PROMPT
        Analiza esta factura de proveedor.
        Extrae el codigo numerico final del producto (ej: de 09-61-02-1077816 es 1077816).
        Responde SOLO JSON sin markdown, con esta forma:
        {"numero":"","doc":"","emision":"YYYY-MM-DD","vencimiento":"YYYY-MM-DD","importe":0.00,"tc":3.44,"forma_pago":"","items":[{"codigo":"","producto":"","cantidad":0,"presentacion":""}]}
        PROMPT;
    }
}

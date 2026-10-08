<?php

namespace App\Services\Sunat;

use App\Models\Cobranza;
use App\Models\GuiaRemision;
use App\Models\Venta;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador hacia API-GO (proyecto Laravel aparte, ver
 * API-GO-Facturacion-Electronica-sunat-peru-main/), que implementa la
 * emisión electrónica real de comprobantes ante SUNAT vía Greenter.
 *
 * Mismo patrón que ConsultaDocumentoService: peticiones con timeout corto,
 * nunca lanza excepciones hacia el llamador (devuelve null/false y registra
 * un warning), para que un fallo o caída de API-GO nunca bloquee una venta.
 */
class ApiGoEmisionService
{
    /** Tipo de documento API-GO (para `api_go_document_type`) según el código SUNAT de la Venta. */
    private const TIPO_DOCUMENTO = [
        '03' => 'boleta',
        '01' => 'invoice',
        '07' => 'credit_note',
        '08' => 'debit_note',
    ];

    /**
     * Serie sugerida para una Guía de Remisión — a diferencia de Boleta/
     * Factura (donde la persona tipea o se sugiere desde el correlativo
     * real), acá siempre es la misma de talonario electrónico estándar;
     * API-GO corrige el correlativo real igual que con los demás documentos.
     */
    private const SERIE_GUIA_REMISION = 'T001';

    /**
     * Registra localmente en API-GO (sin enviar a SUNAT todavía) el
     * comprobante de una Venta recién guardada. Aplica a Boleta (03),
     * Factura (01), Nota de Crédito (07) y Nota de Débito (08).
     */
    public function crearComprobante(Venta $venta): bool
    {
        if (! array_key_exists($venta->tipcomp, self::TIPO_DOCUMENTO)) {
            return false;
        }

        $tipo = self::TIPO_DOCUMENTO[$venta->tipcomp];
        $endpoint = '/'.$this->recursoApiGo($tipo);

        // API-GO deduplica el Client internamente (por numero_documento) al
        // crear el comprobante a partir de los datos embebidos — no hace
        // falta buscar/crear el cliente por separado antes de esta llamada.
        $payload = [
            'company_id' => config('services.api_go.company_id'),
            'branch_id' => config('services.api_go.branch_id'),
            'serie' => $venta->n_seri,
            'fecha_emision' => optional($venta->fecha)->format('Y-m-d') ?: now()->format('Y-m-d'),
            'moneda' => $venta->moneda ?: 'PEN',
            'metodo_envio' => 'individual',
            'client' => $this->datosCliente($venta),
            'detalles' => $this->datosDetalles($venta),
        ];

        if ($tipo === 'invoice') {
            $payload['forma_pago_tipo'] = $this->esCredito($venta) ? 'Credito' : 'Contado';

            // Ley N° 28194 (Bancarización): facturas mayores a S/ 2,000 / US$
            // 500 necesitan estos datos o API-GO rechaza el comprobante —
            // confirmado en producción (venta real rechazada el 6 de
            // octubre). Solo se manda si el formulario lo pidió y se llenó.
            if ($venta->bancarizacion_medio_pago) {
                $payload['bancarizacion'] = array_filter([
                    'medio_pago' => $venta->bancarizacion_medio_pago,
                    'numero_operacion' => $venta->bancarizacion_numero_operacion,
                    'fecha_pago' => optional($venta->bancarizacion_fecha_pago)->format('Y-m-d'),
                    'banco' => $venta->bancarizacion_banco,
                    'observaciones' => $venta->bancarizacion_observaciones,
                ], fn ($valor) => $valor !== null && $valor !== '');
            }
        }

        if (in_array($tipo, ['credit_note', 'debit_note'], true)) {
            $origen = $venta->ventaOrigen;

            unset($payload['metodo_envio']);
            $payload['tipo_doc_afectado'] = $origen->tipcomp;
            $payload['num_doc_afectado'] = "{$origen->n_seri}-{$origen->n_comp}";
            $payload['cod_motivo'] = $venta->cod_motivo;
            $payload['des_motivo'] = $tipo === 'credit_note'
                ? (Venta::MOTIVOS_CREDITO[$venta->cod_motivo] ?? 'Sin especificar')
                : (Venta::MOTIVOS_DEBITO[$venta->cod_motivo] ?? 'Sin especificar');
        }

        $respuesta = $this->peticion('post', $endpoint, $payload);

        if (! $respuesta || empty($respuesta['success']) || empty($respuesta['data']['id'])) {
            Log::warning('No se pudo registrar el comprobante en API-GO', [
                'venta_id' => $venta->id,
                'respuesta' => $respuesta,
            ]);

            // Antes quedaba solo en el log — la persona que vende nunca se
            // enteraba de que el comprobante NO se registró ante SUNAT (ej.
            // bancarización faltante en una Factura grande), y el número
            // tipeado se quedaba ahí pareciendo válido sin serlo. El mismo
            // bloque del comprobante que ya muestra "rechazado"/"error" de
            // `enviarSunat()` también cubre este caso.
            $venta->update([
                'estado_factura' => 'error',
                'nota_contadora' => $respuesta['message'] ?? 'No se pudo registrar el comprobante en el sistema de facturación electrónica.',
            ]);

            return false;
        }

        $venta->update([
            'api_go_document_id' => $respuesta['data']['id'],
            'api_go_document_type' => $tipo,
            'estado_factura' => 'registrado',
            'numero_sunat' => $respuesta['data']['numero_completo'] ?? null,
        ]);

        $this->sincronizarNumeroReal($venta, $respuesta['data']);

        return true;
    }

    /**
     * Registra la Guía de Remisión en API-GO — mismo "esqueleto" que
     * `crearComprobante()`: nunca lanza excepciones, nunca bloquea el
     * guardado local, deja el motivo real visible si falla. A diferencia
     * de Boleta/Factura (que embeben el cliente inline, API-GO lo
     * deduplica solo), el endpoint de Guías exige un `destinatario_id` ya
     * existente — se resuelve antes de armar el resto del payload.
     */
    public function crearGuiaRemision(GuiaRemision $guia): bool
    {
        $destinatarioId = $this->resolverClienteApiGo($guia->cliente_ruc, $guia->cliente_nombre);

        if ($destinatarioId === null) {
            Log::warning('No se pudo resolver el destinatario en API-GO para la guía', ['guia_id' => $guia->id]);

            $guia->update([
                'estado_sunat' => 'error',
                'nota_sunat' => 'No se pudo identificar al destinatario (RUC/DNI) en el sistema de facturación electrónica.',
            ]);

            return false;
        }

        $payload = [
            'company_id' => config('services.api_go.company_id'),
            'branch_id' => config('services.api_go.branch_id'),
            'destinatario_id' => $destinatarioId,
            'serie' => self::SERIE_GUIA_REMISION,
            'fecha_emision' => optional($guia->fecha)->format('Y-m-d') ?: now()->format('Y-m-d'),
            'cod_traslado' => $guia->cod_traslado,
            'mod_traslado' => $guia->mod_traslado,
            'fecha_traslado' => optional($guia->fecha_traslado)->format('Y-m-d')
                ?: (optional($guia->fecha)->format('Y-m-d') ?: now()->format('Y-m-d')),
            'peso_total' => (float) $guia->peso_total,
            'und_peso_total' => $guia->und_peso_total ?: 'KGM',
            'num_bultos' => max(1, (int) $guia->bultos),
            'partida_ubigeo' => $guia->partida_ubigeo,
            'partida_direccion' => $guia->punto_partida,
            'llegada_ubigeo' => $guia->llegada_ubigeo,
            'llegada_direccion' => $guia->punto_llegada,
            'detalles' => $this->datosDetallesGuia($guia),
            'observaciones' => $guia->observaciones,
        ];

        if ($guia->mod_traslado === '01') {
            $payload['transportista_tipo_doc'] = $this->codigoTipoDocumento($guia->transportista_ruc);
            $payload['transportista_num_doc'] = $guia->transportista_ruc;
            $payload['transportista_razon_social'] = $guia->empresa_transporte;
        } elseif ($guia->mod_traslado === '02') {
            [$nombres, $apellidos] = $this->partirNombre($guia->conductor_nombre);

            $payload['conductor_tipo_doc'] = '1';
            $payload['conductor_num_doc'] = $guia->conductor_dni;
            $payload['conductor_licencia'] = $guia->licencia_conductor;
            $payload['conductor_nombres'] = $nombres;
            $payload['conductor_apellidos'] = $apellidos;
            $payload['vehiculo_placa'] = $guia->placa_vehiculo;
        }

        $respuesta = $this->peticion('post', '/dispatch-guides', $payload);

        if (! $respuesta || empty($respuesta['success']) || empty($respuesta['data']['id'])) {
            Log::warning('No se pudo registrar la guía de remisión en API-GO', [
                'guia_id' => $guia->id,
                'respuesta' => $respuesta,
            ]);

            $guia->update([
                'estado_sunat' => 'error',
                'nota_sunat' => $respuesta['message'] ?? 'No se pudo registrar la guía en el sistema de facturación electrónica.',
            ]);

            return false;
        }

        $guia->update([
            'api_go_document_id' => $respuesta['data']['id'],
            'estado_sunat' => 'registrado',
            'nota_sunat' => null,
        ]);

        $this->sincronizarNumeroRealGuia($guia, $respuesta['data']);

        return true;
    }

    /**
     * Busca al destinatario en API-GO por documento y, si no existe, lo
     * crea — a diferencia de Boleta/Factura, el endpoint de Guías exige
     * el id ya resuelto de antemano, no acepta los datos embebidos.
     */
    private function resolverClienteApiGo(?string $numero, ?string $razonSocial): ?int
    {
        $numeroLimpio = preg_replace('/\D/', '', (string) $numero);

        if ($numeroLimpio === '') {
            return null;
        }

        $tipoDocumento = $this->codigoTipoDocumento($numeroLimpio);
        $companyId = config('services.api_go.company_id');

        $encontrado = $this->peticion('post', '/clients/search-by-document', [
            'company_id' => $companyId,
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numeroLimpio,
        ]);

        if (! empty($encontrado['success']) && ! empty($encontrado['data']['id'])) {
            return (int) $encontrado['data']['id'];
        }

        $creado = $this->peticion('post', '/clients', [
            'company_id' => $companyId,
            'tipo_documento' => $tipoDocumento,
            'numero_documento' => $numeroLimpio,
            'razon_social' => $razonSocial ?: 'CLIENTE VARIOS',
        ]);

        return ! empty($creado['success']) && ! empty($creado['data']['id']) ? (int) $creado['data']['id'] : null;
    }

    /**
     * El número local (`numero_guia`, formato GR-AAAAMMDD-NNNN) es solo una
     * referencia interna — a diferencia de Boleta/Factura, nunca se
     * reemplaza por el real, así que no hay forma de que choque con otra
     * guía. El número real de SUNAT se guarda aparte, solo para mostrarlo.
     */
    private function sincronizarNumeroRealGuia(GuiaRemision $guia, array $datos): void
    {
        $serieReal = $datos['serie'] ?? null;
        $correlativoReal = $datos['correlativo'] ?? null;

        if (! $serieReal || ! $correlativoReal) {
            return;
        }

        $guia->update(['numero_sunat' => "{$serieReal}-{$correlativoReal}"]);
    }

    /**
     * Envía a SUNAT (real) la guía ya registrada en API-GO. A diferencia de
     * Boleta/Factura, SUNAT procesa Guías de Remisión de forma asíncrona —
     * esta respuesta trae un ticket a consultar después
     * (`verificarEstadoGuiaRemision()`), no la aceptación final.
     */
    public function enviarGuiaRemisionSunat(GuiaRemision $guia): bool
    {
        if (! $guia->api_go_document_id) {
            return false;
        }

        $respuesta = $this->peticion('post', "/dispatch-guides/{$guia->api_go_document_id}/send-sunat");

        if (! $respuesta || empty($respuesta['success'])) {
            $guia->update([
                'estado_sunat' => 'error',
                'nota_sunat' => $respuesta['message'] ?? 'No se pudo enviar la guía al servicio de facturación electrónica.',
            ]);

            return false;
        }

        $guia->update([
            'estado_sunat' => $respuesta['data']['estado_sunat'] ?? 'enviado',
            'api_go_ticket' => $respuesta['data']['ticket'] ?? $guia->api_go_ticket,
            'nota_sunat' => $respuesta['message'] ?? null,
        ]);

        return true;
    }

    /** Consulta el estado real del envío async — SUNAT puede tardar en procesar una Guía de Remisión. */
    public function verificarEstadoGuiaRemision(GuiaRemision $guia): void
    {
        if (! $guia->api_go_document_id) {
            return;
        }

        $respuesta = $this->peticion('post', "/dispatch-guides/{$guia->api_go_document_id}/check-status");

        if (! $respuesta || empty($respuesta['success'])) {
            return;
        }

        $guia->update([
            'estado_sunat' => $respuesta['data']['estado_sunat'] ?? $guia->estado_sunat,
            'numero_sunat' => $respuesta['data']['numero_completo'] ?? $guia->numero_sunat,
            'nota_sunat' => $respuesta['message'] ?? $guia->nota_sunat,
        ]);
    }

    private function datosDetallesGuia(GuiaRemision $guia): array
    {
        return collect($guia->productos ?? [])->map(fn ($item) => [
            'codigo' => $item['codigo'] ?? 'ITEM',
            'descripcion' => $item['nombre'] ?? $item['descripcion'] ?? 'Producto',
            'unidad' => $item['unidad'] ?? 'NIU',
            'cantidad' => (float) ($item['cantidad'] ?? 1),
        ])->values()->all();
    }

    /** "Juan Pérez Ramos" → ["Juan", "Pérez Ramos"] — SUNAT pide nombres y apellidos del conductor por separado. */
    private function partirNombre(?string $nombreCompleto): array
    {
        $partes = preg_split('/\s+/', trim((string) $nombreCompleto), 2);

        return [$partes[0] ?? '', $partes[1] ?? ($partes[0] ?? '')];
    }

    /**
     * API-GO asigna el correlativo real (el que de verdad queda ante SUNAT)
     * con su propio contador por sucursal+serie, sin importar qué `n_seri`/
     * `n_comp` haya escrito la persona en el formulario de RentalBoosted
     * (son dos numeraciones independientes) — si no coinciden, el
     * comprobante quedaría mostrando/imprimiendo un número distinto al que
     * SUNAT realmente aceptó. Se corrige acá, apenas se conoce el número
     * real, para que el comprobante y la Cobranza queden siempre con el
     * número verdadero sin depender de que lo hayan tipeado bien.
     */
    private function sincronizarNumeroReal(Venta $venta, array $datos): void
    {
        $serieReal = $datos['serie'] ?? null;
        $correlativoReal = $datos['correlativo'] ?? null;

        if (! $serieReal || ! $correlativoReal) {
            return;
        }

        if ($venta->n_seri === $serieReal && $venta->n_comp === $correlativoReal) {
            return;
        }

        // El número real que acaba de asignar SUNAT puede coincidir con el
        // de OTRO comprobante local que nunca llegó a registrarse de
        // verdad (ej. rechazado por bancarización) — desde el punto de
        // vista de SUNAT ese número nunca se consumió, así que lo vuelve a
        // entregar aquí. Sin este aviso, los dos quedan mostrando el mismo
        // número sin ninguna explicación (caso real: F001-001869 repetido
        // entre la venta #211, atascada, y la #261, recién registrada, el
        // 7 de octubre). El número real igual se aplica — es el correcto
        // para ESTE comprobante — pero queda marcado para que alguien
        // revise y corrija el otro.
        $conflicto = Venta::where('id', '!=', $venta->id)
            ->where('tipcomp', $venta->tipcomp)
            ->where('n_seri', $serieReal)
            ->where('n_comp', $correlativoReal)
            ->first();

        $venta->update([
            'n_seri' => $serieReal,
            'n_comp' => $correlativoReal,
            'nota_contadora' => $conflicto
                ? "Registrado con el número real {$serieReal}-{$correlativoReal} — pero ese mismo número ya aparece en el comprobante #{$conflicto->id} ({$conflicto->razonsocial}), que nunca se registró de verdad ante SUNAT. Revisa y corrige ese comprobante anterior (\"Reintentar registro SUNAT\" o anúlalo)."
                : $venta->nota_contadora,
        ]);

        if ($conflicto) {
            Log::warning('Número real de SUNAT coincide con otro comprobante local sin registrar', [
                'venta_id' => $venta->id,
                'conflicto_venta_id' => $conflicto->id,
                'numero' => "{$serieReal}-{$correlativoReal}",
            ]);
        }

        if ($venta->cobranza_id) {
            Cobranza::where('id', $venta->cobranza_id)->update(['numero' => "{$serieReal}-{$correlativoReal}"]);
        }
    }

    /**
     * Catálogo de respaldo si API-GO no responde al pedir la lista real —
     * los mismos códigos que ya siembra su migración
     * (`medios_pago_bancarizacion`), para que cualquier código elegido acá
     * siga siendo válido cuando el comprobante se registre de verdad.
     */
    private const MEDIOS_PAGO_RESPALDO = [
        ['codigo' => 'TRAN', 'descripcion' => 'Transferencia bancaria'],
        ['codigo' => 'DEPO', 'descripcion' => 'Depósito en cuenta'],
        ['codigo' => 'YAPE', 'descripcion' => 'Yape (BCP)'],
        ['codigo' => 'PLIN', 'descripcion' => 'Plin (Consorcio de bancos)'],
        ['codigo' => 'TDEB', 'descripcion' => 'Tarjeta de débito'],
        ['codigo' => 'TCRE', 'descripcion' => 'Tarjeta de crédito'],
    ];

    /**
     * Catálogo de medios de pago válidos para la bancarización de una
     * Factura (Ley N° 28194) — vive en API-GO, no hay una lista fija de
     * este lado. Si API-GO no responde, cae al respaldo de arriba en vez de
     * dejar el formulario sin ninguna opción elegible — justo el escenario
     * (API-GO caído o lento) en el que más hace falta poder seguir
     * vendiendo.
     */
    public function mediosPagoBancarizacion(): array
    {
        $respuesta = $this->peticion('get', '/bancarizacion/medios-pago');
        $datos = $respuesta['data'] ?? [];

        return $datos !== [] ? $datos : self::MEDIOS_PAGO_RESPALDO;
    }

    /**
     * Sugerencia del próximo N° Comprobante real para Boleta/Factura, para
     * no hacer que la persona lo escriba a ciegas — a diferencia de
     * Cotización/Nota de Venta (numeración 100% interna), este es solo un
     * adelanto de lo que API-GO va a asignar en el momento real de crear el
     * comprobante (`crearComprobante()`, que corrige el número final igual
     * si otra venta se cuela antes). Devuelve null si API-GO no responde o
     * no tiene un correlativo configurado para esa serie — el formulario
     * cae entonces al campo editable de siempre, no bloquea la venta.
     */
    public function siguienteCorrelativo(string $tipcomp, string $serie): ?string
    {
        if (! array_key_exists($tipcomp, self::TIPO_DOCUMENTO)) {
            return null;
        }

        $branchId = config('services.api_go.branch_id');
        $respuesta = $this->peticion('get', "/branches/{$branchId}/correlatives");
        $correlativos = $respuesta['data']['correlatives'] ?? null;

        if (! is_array($correlativos)) {
            return null;
        }

        foreach ($correlativos as $correlativo) {
            if (($correlativo['tipo_documento'] ?? null) === $tipcomp && ($correlativo['serie'] ?? null) === $serie) {
                return str_pad((string) (((int) ($correlativo['correlativo_actual'] ?? 0)) + 1), 6, '0', STR_PAD_LEFT);
            }
        }

        return null;
    }

    /**
     * Envía a SUNAT (real) el comprobante ya registrado en API-GO.
     */
    public function enviarSunat(Venta $venta): bool
    {
        if (! $venta->api_go_document_id || ! $venta->api_go_document_type) {
            return false;
        }

        $recurso = $this->recursoApiGo($venta->api_go_document_type);

        $respuesta = $this->peticion('post', "/{$recurso}/{$venta->api_go_document_id}/send-sunat");

        if (! $respuesta) {
            $venta->update([
                'estado_factura' => 'error',
                'nota_contadora' => 'No se pudo contactar al servicio de facturación electrónica.',
            ]);

            return false;
        }

        $exito = (bool) ($respuesta['success'] ?? false);
        $estadoSunat = $respuesta['data']['estado_sunat'] ?? null;

        $venta->update([
            'estado_factura' => $exito && $estadoSunat === 'ACEPTADO' ? 'aceptado' : 'rechazado',
            'nota_contadora' => $respuesta['message'] ?? null,
        ]);

        return $exito;
    }

    /**
     * Devuelve el PDF oficial (bytes) del comprobante. La ruta autenticada
     * de API-GO lo genera sola si todavía no existe — no hace falta un
     * paso previo de "generate-pdf" por separado.
     */
    public function obtenerPdf(Venta $venta): ?string
    {
        if (! $venta->api_go_document_id || ! $venta->api_go_document_type) {
            return null;
        }

        $recurso = $this->recursoApiGo($venta->api_go_document_type);
        $baseUrl = rtrim(config('services.api_go.base_url'), '/');
        $token = config('services.api_go.token');

        try {
            $respuesta = Http::timeout(20)
                ->when($token, fn ($http) => $http->withToken($token))
                ->get("{$baseUrl}/{$recurso}/{$venta->api_go_document_id}/download-pdf");

            if ($respuesta->failed()) {
                Log::warning('Descarga de PDF de API-GO falló', [
                    'venta_id' => $venta->id,
                    'status' => $respuesta->status(),
                ]);

                return null;
            }

            return $respuesta->body();
        } catch (\Throwable $e) {
            Log::warning('Descarga de PDF de API-GO lanzó excepción', [
                'venta_id' => $venta->id,
                'mensaje' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Segmento de ruta en API-GO (`/{recurso}/...`) según `api_go_document_type`. */
    private function recursoApiGo(string $tipo): string
    {
        return match ($tipo) {
            'boleta' => 'boletas',
            'credit_note' => 'credit-notes',
            'debit_note' => 'debit-notes',
            default => 'invoices',
        };
    }

    private function datosCliente(Venta $venta): array
    {
        $numero = $venta->cliente_ruc ?: $venta->n_ruc ?: '00000000';

        return [
            'tipo_documento' => $this->codigoTipoDocumento($numero),
            'numero_documento' => $numero,
            'razon_social' => $venta->razonsocial ?: $venta->cliente_nombre ?: 'CLIENTE VARIOS',
            'direccion' => $venta->cliente_direccion,
            'distrito' => $venta->cliente_distrito,
            'telefono' => $venta->cliente_telefono,
            'email' => $venta->cliente_correo,
        ];
    }

    private function datosDetalles(Venta $venta): array
    {
        $porcentajeIgv = (float) config('rentaltech.igv', 0.18) * 100;

        if ($venta->detalles->isNotEmpty()) {
            return $venta->detalles->map(fn ($detalle) => [
                'codigo' => $detalle->prod_codigo ?: 'ITEM',
                'descripcion' => $detalle->prod_nombre,
                'unidad' => 'NIU',
                'cantidad' => (float) $detalle->cantidad,
                'mto_valor_unitario' => (float) $detalle->precio_unitario,
                'porcentaje_igv' => $porcentajeIgv,
                'tip_afe_igv' => '10',
            ])->all();
        }

        // Camino de "monto único" (sin líneas de producto): se sintetiza un
        // solo ítem según cuál de los tres montos de la Venta tenga valor.
        $tipAfeIgv = '10';
        $monto = (float) $venta->baseimp;

        if ($monto <= 0 && (float) $venta->exonerado > 0) {
            $tipAfeIgv = '20';
            $monto = (float) $venta->exonerado;
        } elseif ($monto <= 0 && (float) $venta->inafecto > 0) {
            $tipAfeIgv = '30';
            $monto = (float) $venta->inafecto;
        }

        return [[
            'codigo' => 'VENTA',
            'descripcion' => $venta->observaciones ?: 'Venta',
            'unidad' => 'NIU',
            'cantidad' => 1,
            'mto_valor_unitario' => $monto,
            'porcentaje_igv' => $tipAfeIgv === '10' ? $porcentajeIgv : 0,
            'tip_afe_igv' => $tipAfeIgv,
        ]];
    }

    private function esCredito(Venta $venta): bool
    {
        return str_contains(mb_strtolower((string) $venta->condicion_pago), 'cred');
    }

    private function codigoTipoDocumento(?string $numero): string
    {
        return strlen((string) $numero) === 11 ? '6' : '1';
    }

    private function peticion(string $metodo, string $ruta, array $datos = []): ?array
    {
        try {
            $baseUrl = rtrim(config('services.api_go.base_url'), '/');
            $token = config('services.api_go.token');

            $http = Http::timeout(15)->when($token, fn ($http) => $http->withToken($token));

            $respuesta = $metodo === 'get'
                ? $http->get("{$baseUrl}{$ruta}", $datos)
                : $http->post("{$baseUrl}{$ruta}", $datos);

            $cuerpo = $respuesta->json();

            if ($respuesta->failed()) {
                Log::warning('Llamada a API-GO devolvió error', [
                    'ruta' => $ruta,
                    'status' => $respuesta->status(),
                    'body' => $cuerpo,
                ]);

                // API-GO casi siempre responde con un cuerpo JSON explicando
                // el error (ej. certificado no configurado) incluso en 4xx/5xx.
                // Se devuelve igual para que el llamador pueda mostrar el
                // motivo real; solo se devuelve null si no hubo cuerpo.
                return $cuerpo;
            }

            return $cuerpo;
        } catch (\Throwable $e) {
            Log::warning('Llamada a API-GO lanzó excepción', [
                'ruta' => $ruta,
                'mensaje' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

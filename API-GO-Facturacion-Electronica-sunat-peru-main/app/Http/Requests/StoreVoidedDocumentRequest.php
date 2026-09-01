<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\VoidedDocument;

class StoreVoidedDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'required|exists:branches,id',
            'fecha_referencia' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subDays(7)->toDateString(),
            'motivo_baja' => 'required|string|max:500',
            'ubl_version' => 'nullable|string|in:2.0,2.1',
            'usuario_creacion' => 'nullable|string|max:100',
            
            // Detalles de documentos a anular
            'detalles' => 'required|array|min:1|max:100',
            'detalles.*.tipo_documento' => 'required|string|in:01,07,08',
            'detalles.*.serie' => 'required|string|max:4',
            'detalles.*.correlativo' => 'required|string|max:8',
            'detalles.*.motivo_especifico' => 'required|string|max:250',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que la sucursal pertenece a la empresa
            $branch = Branch::where('id', $this->input('branch_id'))
                          ->where('company_id', $this->input('company_id'))
                          ->first();

            if (!$branch) {
                $validator->errors()->add('branch_id', 'La sucursal no pertenece a la empresa seleccionada.');
            }

            // Validar que no hay documentos duplicados en la solicitud
            $detalles = $this->input('detalles', []);
            $documentosEnLista = [];

            foreach ($detalles as $index => $detalle) {
                $tipoDoc = $detalle['tipo_documento'] ?? null;
                $serie = $detalle['serie'] ?? null;
                $correlativo = $detalle['correlativo'] ?? null;

                if (!$tipoDoc || !$serie || !$correlativo) {
                    continue;
                }

                $key = "{$tipoDoc}-{$serie}-{$correlativo}";

                // Validar duplicados en la lista
                if (in_array($key, $documentosEnLista)) {
                    $validator->errors()->add("detalles.{$index}", "Documento duplicado en la lista: {$serie}-{$correlativo}");
                    continue;
                }

                $documentosEnLista[] = $key;

                // VALIDACIÓN 1: Verificar que el documento existe en la base de datos
                $documento = null;
                $modelClass = null;
                $numeroCompleto = "{$serie}-{$correlativo}";

                switch ($tipoDoc) {
                    case '01': // Factura
                        $modelClass = Invoice::class;
                        $documento = Invoice::where('company_id', $this->input('company_id'))
                            ->where('serie', $serie)
                            ->where('correlativo', $correlativo)
                            ->first();
                        break;
                    case '07': // Nota de Crédito
                        $modelClass = CreditNote::class;
                        $documento = CreditNote::where('company_id', $this->input('company_id'))
                            ->where('serie', $serie)
                            ->where('correlativo', $correlativo)
                            ->first();
                        break;
                    case '08': // Nota de Débito
                        $modelClass = DebitNote::class;
                        $documento = DebitNote::where('company_id', $this->input('company_id'))
                            ->where('serie', $serie)
                            ->where('correlativo', $correlativo)
                            ->first();
                        break;
                }

                if (!$documento) {
                    $validator->errors()->add(
                        "detalles.{$index}",
                        "El documento {$numeroCompleto} no existe en el sistema o no pertenece a esta empresa."
                    );
                    continue;
                }

                // VALIDACIÓN 2: Verificar que el documento está ACEPTADO por SUNAT
                if ($documento->estado_sunat !== 'ACEPTADO') {
                    $validator->errors()->add(
                        "detalles.{$index}",
                        "El documento {$numeroCompleto} no está ACEPTADO por SUNAT (Estado actual: {$documento->estado_sunat}). Solo se pueden anular documentos aceptados."
                    );
                    continue;
                }

                // VALIDACIÓN 3: Verificar que no esté ya anulado
                if ($documento->anulado) {
                    $validator->errors()->add(
                        "detalles.{$index}",
                        "El documento {$numeroCompleto} ya está anulado. No se puede anular nuevamente."
                    );
                    continue;
                }

                // VALIDACIÓN 4: Verificar que no tenga una comunicación de baja previa (PENDIENTE, ENVIADO o ACEPTADO)
                $bajaPrevia = VoidedDocument::where('company_id', $this->input('company_id'))
                    ->whereIn('estado_sunat', ['PENDIENTE', 'ENVIADO', 'ACEPTADO'])
                    ->where(function($query) use ($serie, $correlativo) {
                        $query->where('detalles', 'like', "%{$serie}%")
                              ->where('detalles', 'like', "%{$correlativo}%");
                    })
                    ->first();

                if ($bajaPrevia) {
                    $estadoTexto = match($bajaPrevia->estado_sunat) {
                        'PENDIENTE' => 'PENDIENTE de envío',
                        'ENVIADO' => 'ENVIADA y esperando respuesta de SUNAT',
                        'ACEPTADO' => 'ACEPTADA por SUNAT',
                        default => $bajaPrevia->estado_sunat
                    };

                    $validator->errors()->add(
                        "detalles.{$index}",
                        "El documento {$numeroCompleto} ya tiene una comunicación de baja {$estadoTexto} (ID: {$bajaPrevia->id}, Número: {$bajaPrevia->numero_completo}). No se puede crear otra comunicación de baja."
                    );
                    continue;
                }

                // VALIDACIÓN 5: Para Facturas, verificar que no tenga Notas de Crédito o Débito
                if ($tipoDoc === '01') {
                    $tieneNotasCredito = CreditNote::where('company_id', $this->input('company_id'))
                        ->where('num_doc_afectado', $numeroCompleto)
                        ->where('estado_sunat', 'ACEPTADO')
                        ->exists();

                    if ($tieneNotasCredito) {
                        $validator->errors()->add(
                            "detalles.{$index}",
                            "La factura {$numeroCompleto} tiene Notas de Crédito asociadas. No se puede anular con comunicación de baja."
                        );
                        continue;
                    }

                    $tieneNotasDebito = DebitNote::where('company_id', $this->input('company_id'))
                        ->where('num_doc_afectado', $numeroCompleto)
                        ->where('estado_sunat', 'ACEPTADO')
                        ->exists();

                    if ($tieneNotasDebito) {
                        $validator->errors()->add(
                            "detalles.{$index}",
                            "La factura {$numeroCompleto} tiene Notas de Débito asociadas. No se puede anular con comunicación de baja."
                        );
                        continue;
                    }
                }

                // VALIDACIÓN 6: Verificar plazo de 7 días desde la EMISIÓN del documento
                $fechaEmision = \Carbon\Carbon::parse($documento->fecha_emision);
                $ahora = \Carbon\Carbon::now();
                $diasTranscurridos = $fechaEmision->diffInDays($ahora, false);

                if ($diasTranscurridos > 7) {
                    $validator->errors()->add(
                        "detalles.{$index}",
                        "El documento {$numeroCompleto} fue emitido hace {$diasTranscurridos} días. Solo se pueden anular documentos emitidos en los últimos 7 días calendario."
                    );
                    continue;
                }
            }

            // Validar plazo de 7 días para la fecha de referencia
            $fechaReferencia = $this->input('fecha_referencia');
            if ($fechaReferencia) {
                $fechaRef = \Carbon\Carbon::parse($fechaReferencia);
                $hoy = \Carbon\Carbon::now();

                if ($fechaRef->diffInDays($hoy, false) > 7) {
                    $validator->errors()->add('fecha_referencia', 'Solo se pueden anular documentos emitidos en los últimos 7 días calendario.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'La empresa es requerida.',
            'company_id.exists' => 'La empresa seleccionada no existe.',
            'branch_id.required' => 'La sucursal es requerida.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
            'fecha_referencia.required' => 'La fecha de referencia es requerida.',
            'fecha_referencia.date' => 'La fecha de referencia debe ser una fecha válida.',
            'fecha_referencia.before_or_equal' => 'La fecha de referencia no puede ser mayor a hoy.',
            'fecha_referencia.after_or_equal' => 'Solo se pueden anular documentos de los últimos 7 días.',
            'motivo_baja.required' => 'El motivo de baja es requerido.',
            'motivo_baja.max' => 'El motivo de baja no puede exceder 500 caracteres.',
            
            'detalles.required' => 'Se requiere al menos un documento para anular.',
            'detalles.array' => 'Los detalles deben ser un array.',
            'detalles.min' => 'Se requiere al menos un documento para anular.',
            'detalles.max' => 'No se pueden anular más de 100 documentos por comunicación.',
            
            'detalles.*.tipo_documento.required' => 'El tipo de documento es requerido.',
            'detalles.*.tipo_documento.in' => 'Tipo de documento inválido. Solo se permiten Facturas (01), Notas de Crédito (07) y Notas de Débito (08). Para anular Boletas use Resúmenes Diarios.',
            'detalles.*.serie.required' => 'La serie es requerida.',
            'detalles.*.serie.max' => 'La serie no puede exceder 4 caracteres.',
            'detalles.*.correlativo.required' => 'El correlativo es requerido.',
            'detalles.*.correlativo.max' => 'El correlativo no puede exceder 8 caracteres.',
            'detalles.*.motivo_especifico.required' => 'El motivo específico es requerido.',
            'detalles.*.motivo_especifico.max' => 'El motivo específico no puede exceder 250 caracteres.',
        ];
    }
}
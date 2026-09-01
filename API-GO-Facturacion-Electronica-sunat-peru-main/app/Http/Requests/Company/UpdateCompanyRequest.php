<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->route('company')->id ?? null;

        return [
            // ==================== DATOS BÁSICOS ====================
            'ruc' => [
                'required',
                'string',
                'size:11',
                Rule::unique('companies', 'ruc')->ignore($companyId),
            ],
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',

            // ==================== UBICACIÓN ====================
            'direccion' => 'required|string|max:255',
            'ubigeo' => 'required|string|size:6',
            'distrito' => 'required|string|max:100',
            'provincia' => 'required|string|max:100',
            'departamento' => 'required|string|max:100',

            // ==================== CONTACTO PRINCIPAL ====================
            'telefono' => 'nullable|string|max:50',
            'email' => 'required|email|max:100',
            'web' => 'nullable|url|max:255',

            // ==================== CONTACTOS ADICIONALES ====================
            'telefono_2' => 'nullable|string|max:50',
            'telefono_3' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'email_ventas' => 'nullable|email|max:100',
            'email_soporte' => 'nullable|email|max:100',

            // ==================== REDES SOCIALES ====================
            'facebook' => 'nullable|url|max:200',
            'instagram' => 'nullable|url|max:200',
            'twitter' => 'nullable|url|max:200',
            'linkedin' => 'nullable|url|max:200',
            'tiktok' => 'nullable|url|max:200',

            // ==================== CUENTAS BANCARIAS ====================
            'cuentas_bancarias' => 'nullable|array',
            'cuentas_bancarias.*.banco' => 'required_with:cuentas_bancarias|string|max:100',
            'cuentas_bancarias.*.moneda' => 'required_with:cuentas_bancarias|string|in:PEN,USD',
            'cuentas_bancarias.*.tipo_cuenta' => 'required_with:cuentas_bancarias|string|in:AHORROS,CORRIENTE',
            'cuentas_bancarias.*.numero' => 'required_with:cuentas_bancarias|string|max:50',
            'cuentas_bancarias.*.cci' => 'nullable|string|max:50',
            'cuentas_bancarias.*.titular' => 'nullable|string|max:200',
            'cuentas_bancarias.*.activo' => 'nullable|boolean',

            // ==================== BILLETERAS DIGITALES ====================
            'billeteras_digitales' => 'nullable|array',
            'billeteras_digitales.*.tipo' => 'required_with:billeteras_digitales|string|in:YAPE,PLIN,TUNKI,LUKITA,AGORA,BIM',
            'billeteras_digitales.*.numero' => 'required_with:billeteras_digitales|string|max:50',
            'billeteras_digitales.*.titular' => 'nullable|string|max:200',
            'billeteras_digitales.*.activo' => 'nullable|boolean',

            // ==================== INFORMACIÓN PDF ====================
            'mensaje_pdf' => 'nullable|string|max:500',
            'terminos_condiciones_pdf' => 'nullable|string|max:2000',
            'politica_garantia' => 'nullable|string|max:2000',

            // ==================== CONFIGURACIÓN PDF ====================
            'mostrar_cuentas_en_pdf' => 'nullable|boolean',
            'mostrar_billeteras_en_pdf' => 'nullable|boolean',
            'mostrar_redes_sociales_en_pdf' => 'nullable|boolean',
            'mostrar_contactos_adicionales_en_pdf' => 'nullable|boolean',

            // ==================== CREDENCIALES SUNAT ====================
            'usuario_sol' => 'required|string|max:50',
            'clave_sol' => 'required|string|max:100',
            'certificado_pem' => [
                'nullable',
                'file',
                'max:2048',
                function ($attribute, $value, $fail) {
                    $allowedExtensions = ['pfx', 'p12', 'pem', 'crt', 'cer'];
                    $extension = strtolower($value->getClientOriginalExtension());
                    
                    if (!in_array($extension, $allowedExtensions)) {
                        $fail('El certificado debe ser un archivo .pfx, .p12, .pem, .crt o .cer');
                    }
                },
            ],
            'certificado_password' => 'nullable|string|max:100',

            // ==================== CREDENCIALES GRE ====================
            'gre_client_id_beta' => 'nullable|string|max:100',
            'gre_client_secret_beta' => 'nullable|string|max:100',
            'gre_client_id_produccion' => 'nullable|string|max:100',
            'gre_client_secret_produccion' => 'nullable|string|max:100',
            'gre_ruc_proveedor' => 'nullable|string|size:11',
            'gre_usuario_sol' => 'nullable|string|max:50',
            'gre_clave_sol' => 'nullable|string|max:100',

            // ==================== ENDPOINTS ====================
            'endpoint_beta' => 'nullable|url|max:255',
            'endpoint_produccion' => 'nullable|url|max:255',

            // ==================== CONFIGURACIÓN GENERAL ====================
            'modo_produccion' => 'nullable|boolean',
            'activo' => 'nullable|boolean',
            'logo_path' => 'nullable|file|mimes:png,jpeg,jpg|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            // ==================== DATOS BÁSICOS ====================
            'ruc.required' => 'El RUC es obligatorio',
            'ruc.size' => 'El RUC debe tener exactamente 11 dígitos',
            'ruc.unique' => 'El RUC ya está registrado en el sistema',
            'razon_social.required' => 'La razón social es obligatoria',
            'razon_social.max' => 'La razón social no puede exceder 255 caracteres',
            'nombre_comercial.max' => 'El nombre comercial no puede exceder 255 caracteres',

            // ==================== UBICACIÓN ====================
            'direccion.required' => 'La dirección es obligatoria',
            'direccion.max' => 'La dirección no puede exceder 255 caracteres',
            'ubigeo.required' => 'El ubigeo es obligatorio',
            'ubigeo.size' => 'El ubigeo debe tener exactamente 6 dígitos',
            'distrito.required' => 'El distrito es obligatorio',
            'distrito.max' => 'El distrito no puede exceder 100 caracteres',
            'provincia.required' => 'La provincia es obligatoria',
            'provincia.max' => 'La provincia no puede exceder 100 caracteres',
            'departamento.required' => 'El departamento es obligatorio',
            'departamento.max' => 'El departamento no puede exceder 100 caracteres',

            // ==================== CONTACTO PRINCIPAL ====================
            'telefono.max' => 'El teléfono no puede exceder 50 caracteres',
            'email.required' => 'El email principal es obligatorio',
            'email.email' => 'El email principal debe ser una dirección válida',
            'email.max' => 'El email principal no puede exceder 100 caracteres',
            'web.url' => 'La URL del sitio web debe ser válida',
            'web.max' => 'La URL del sitio web no puede exceder 255 caracteres',

            // ==================== CONTACTOS ADICIONALES ====================
            'telefono_2.max' => 'El teléfono 2 no puede exceder 50 caracteres',
            'telefono_3.max' => 'El teléfono 3 no puede exceder 50 caracteres',
            'whatsapp.max' => 'El WhatsApp no puede exceder 50 caracteres',
            'email_ventas.email' => 'El email de ventas debe ser una dirección válida',
            'email_ventas.max' => 'El email de ventas no puede exceder 100 caracteres',
            'email_soporte.email' => 'El email de soporte debe ser una dirección válida',
            'email_soporte.max' => 'El email de soporte no puede exceder 100 caracteres',

            // ==================== REDES SOCIALES ====================
            'facebook.url' => 'La URL de Facebook debe ser válida',
            'facebook.max' => 'La URL de Facebook no puede exceder 200 caracteres',
            'instagram.url' => 'La URL de Instagram debe ser válida',
            'instagram.max' => 'La URL de Instagram no puede exceder 200 caracteres',
            'twitter.url' => 'La URL de Twitter debe ser válida',
            'twitter.max' => 'La URL de Twitter no puede exceder 200 caracteres',
            'linkedin.url' => 'La URL de LinkedIn debe ser válida',
            'linkedin.max' => 'La URL de LinkedIn no puede exceder 200 caracteres',
            'tiktok.url' => 'La URL de TikTok debe ser válida',
            'tiktok.max' => 'La URL de TikTok no puede exceder 200 caracteres',

            // ==================== CUENTAS BANCARIAS ====================
            'cuentas_bancarias.*.banco.required_with' => 'El nombre del banco es requerido',
            'cuentas_bancarias.*.banco.max' => 'El nombre del banco no puede exceder 100 caracteres',
            'cuentas_bancarias.*.moneda.required_with' => 'La moneda es requerida',
            'cuentas_bancarias.*.moneda.in' => 'La moneda debe ser PEN o USD',
            'cuentas_bancarias.*.tipo_cuenta.required_with' => 'El tipo de cuenta es requerido',
            'cuentas_bancarias.*.tipo_cuenta.in' => 'El tipo de cuenta debe ser AHORROS o CORRIENTE',
            'cuentas_bancarias.*.numero.required_with' => 'El número de cuenta es requerido',
            'cuentas_bancarias.*.numero.max' => 'El número de cuenta no puede exceder 50 caracteres',
            'cuentas_bancarias.*.cci.max' => 'El CCI no puede exceder 50 caracteres',
            'cuentas_bancarias.*.titular.max' => 'El titular no puede exceder 200 caracteres',
            'cuentas_bancarias.*.activo.boolean' => 'El campo activo debe ser verdadero o falso',

            // ==================== BILLETERAS DIGITALES ====================
            'billeteras_digitales.*.tipo.required_with' => 'El tipo de billetera es requerido',
            'billeteras_digitales.*.tipo.in' => 'El tipo de billetera debe ser YAPE, PLIN, TUNKI, LUKITA, AGORA o BIM',
            'billeteras_digitales.*.numero.required_with' => 'El número de la billetera es requerido',
            'billeteras_digitales.*.numero.max' => 'El número de la billetera no puede exceder 50 caracteres',
            'billeteras_digitales.*.titular.max' => 'El titular no puede exceder 200 caracteres',
            'billeteras_digitales.*.activo.boolean' => 'El campo activo debe ser verdadero o falso',

            // ==================== INFORMACIÓN PDF ====================
            'mensaje_pdf.max' => 'El mensaje PDF no puede exceder 500 caracteres',
            'terminos_condiciones_pdf.max' => 'Los términos y condiciones no pueden exceder 2000 caracteres',
            'politica_garantia.max' => 'La política de garantía no puede exceder 2000 caracteres',

            // ==================== CONFIGURACIÓN PDF ====================
            'mostrar_cuentas_en_pdf.boolean' => 'El campo mostrar cuentas en PDF debe ser verdadero o falso',
            'mostrar_billeteras_en_pdf.boolean' => 'El campo mostrar billeteras en PDF debe ser verdadero o falso',
            'mostrar_redes_sociales_en_pdf.boolean' => 'El campo mostrar redes sociales en PDF debe ser verdadero o falso',
            'mostrar_contactos_adicionales_en_pdf.boolean' => 'El campo mostrar contactos adicionales en PDF debe ser verdadero o falso',

            // ==================== CREDENCIALES SUNAT ====================
            'usuario_sol.required' => 'El usuario SOL es obligatorio',
            'usuario_sol.max' => 'El usuario SOL no puede exceder 50 caracteres',
            'clave_sol.required' => 'La clave SOL es obligatoria',
            'clave_sol.max' => 'La clave SOL no puede exceder 100 caracteres',
            'certificado_pem.file' => 'El certificado debe ser un archivo válido',
            'certificado_pem.max' => 'El certificado no debe exceder 2MB',
            'certificado_password.max' => 'La contraseña del certificado no puede exceder 100 caracteres',

            // ==================== CREDENCIALES GRE ====================
            'gre_client_id_beta.max' => 'El Client ID beta GRE no puede exceder 100 caracteres',
            'gre_client_secret_beta.max' => 'El Client Secret beta GRE no puede exceder 100 caracteres',
            'gre_client_id_produccion.max' => 'El Client ID producción GRE no puede exceder 100 caracteres',
            'gre_client_secret_produccion.max' => 'El Client Secret producción GRE no puede exceder 100 caracteres',
            'gre_ruc_proveedor.size' => 'El RUC del proveedor GRE debe tener exactamente 11 dígitos',
            'gre_usuario_sol.max' => 'El usuario SOL GRE no puede exceder 50 caracteres',
            'gre_clave_sol.max' => 'La clave SOL GRE no puede exceder 100 caracteres',

            // ==================== ENDPOINTS ====================
            'endpoint_beta.url' => 'El endpoint beta debe ser una URL válida',
            'endpoint_beta.max' => 'El endpoint beta no puede exceder 255 caracteres',
            'endpoint_produccion.url' => 'El endpoint producción debe ser una URL válida',
            'endpoint_produccion.max' => 'El endpoint producción no puede exceder 255 caracteres',

            // ==================== CONFIGURACIÓN GENERAL ====================
            'modo_produccion.boolean' => 'El modo producción debe ser verdadero o falso',
            'activo.boolean' => 'El campo activo debe ser verdadero o falso',
            'logo_path.file' => 'El logo debe ser un archivo válido',
            'logo_path.mimes' => 'El logo debe estar en formato PNG, JPEG o JPG',
            'logo_path.max' => 'El logo no debe exceder 2MB',
        ];
    }

    public function attributes(): array
    {
        return [
            // ==================== DATOS BÁSICOS ====================
            'ruc' => 'RUC',
            'razon_social' => 'razón social',
            'nombre_comercial' => 'nombre comercial',

            // ==================== UBICACIÓN ====================
            'direccion' => 'dirección',
            'ubigeo' => 'ubigeo',
            'distrito' => 'distrito',
            'provincia' => 'provincia',
            'departamento' => 'departamento',

            // ==================== CONTACTO PRINCIPAL ====================
            'telefono' => 'teléfono',
            'email' => 'email',
            'web' => 'sitio web',

            // ==================== CONTACTOS ADICIONALES ====================
            'telefono_2' => 'teléfono 2',
            'telefono_3' => 'teléfono 3',
            'whatsapp' => 'WhatsApp',
            'email_ventas' => 'email de ventas',
            'email_soporte' => 'email de soporte',

            // ==================== REDES SOCIALES ====================
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'twitter' => 'Twitter',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',

            // ==================== CUENTAS BANCARIAS ====================
            'cuentas_bancarias.*.banco' => 'banco',
            'cuentas_bancarias.*.moneda' => 'moneda',
            'cuentas_bancarias.*.tipo_cuenta' => 'tipo de cuenta',
            'cuentas_bancarias.*.numero' => 'número de cuenta',
            'cuentas_bancarias.*.cci' => 'CCI',
            'cuentas_bancarias.*.titular' => 'titular',
            'cuentas_bancarias.*.activo' => 'activo',

            // ==================== BILLETERAS DIGITALES ====================
            'billeteras_digitales.*.tipo' => 'tipo de billetera',
            'billeteras_digitales.*.numero' => 'número de billetera',
            'billeteras_digitales.*.titular' => 'titular',
            'billeteras_digitales.*.activo' => 'activo',

            // ==================== INFORMACIÓN PDF ====================
            'mensaje_pdf' => 'mensaje PDF',
            'terminos_condiciones_pdf' => 'términos y condiciones PDF',
            'politica_garantia' => 'política de garantía',

            // ==================== CONFIGURACIÓN PDF ====================
            'mostrar_cuentas_en_pdf' => 'mostrar cuentas en PDF',
            'mostrar_billeteras_en_pdf' => 'mostrar billeteras en PDF',
            'mostrar_redes_sociales_en_pdf' => 'mostrar redes sociales en PDF',
            'mostrar_contactos_adicionales_en_pdf' => 'mostrar contactos adicionales en PDF',

            // ==================== CREDENCIALES SUNAT ====================
            'usuario_sol' => 'usuario SOL',
            'clave_sol' => 'clave SOL',
            'certificado_pem' => 'certificado',
            'certificado_password' => 'contraseña del certificado',

            // ==================== CREDENCIALES GRE ====================
            'gre_client_id_beta' => 'Client ID beta GRE',
            'gre_client_secret_beta' => 'Client Secret beta GRE',
            'gre_client_id_produccion' => 'Client ID producción GRE',
            'gre_client_secret_produccion' => 'Client Secret producción GRE',
            'gre_ruc_proveedor' => 'RUC proveedor GRE',
            'gre_usuario_sol' => 'usuario SOL GRE',
            'gre_clave_sol' => 'clave SOL GRE',

            // ==================== ENDPOINTS ====================
            'endpoint_beta' => 'endpoint beta',
            'endpoint_produccion' => 'endpoint producción',

            // ==================== CONFIGURACIÓN GENERAL ====================
            'modo_produccion' => 'modo producción',
            'activo' => 'activo',
            'logo_path' => 'logo',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Convertir valores booleanos de string a boolean
        $booleanFields = [
            'modo_produccion',
            'activo',
            'mostrar_cuentas_en_pdf',
            'mostrar_billeteras_en_pdf',
            'mostrar_redes_sociales_en_pdf',
            'mostrar_contactos_adicionales_en_pdf',
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN)
                ]);
            }
        }

        // Procesar cuentas bancarias para asegurar booleanos
        if ($this->has('cuentas_bancarias')) {
            $cuentas = $this->input('cuentas_bancarias', []);
            foreach ($cuentas as $key => $cuenta) {
                if (isset($cuenta['activo'])) {
                    $cuentas[$key]['activo'] = filter_var($cuenta['activo'], FILTER_VALIDATE_BOOLEAN);
                }
            }
            $this->merge(['cuentas_bancarias' => $cuentas]);
        }

        // Procesar billeteras digitales para asegurar booleanos
        if ($this->has('billeteras_digitales')) {
            $billeteras = $this->input('billeteras_digitales', []);
            foreach ($billeteras as $key => $billetera) {
                if (isset($billetera['activo'])) {
                    $billeteras[$key]['activo'] = filter_var($billetera['activo'], FILTER_VALIDATE_BOOLEAN);
                }
            }
            $this->merge(['billeteras_digitales' => $billeteras]);
        }
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation()
    {
        // Asegurar que los campos opcionales sean null si están vacíos
        $nullableFields = [
            'nombre_comercial', 'telefono', 'web', 'telefono_2', 'telefono_3', 
            'whatsapp', 'email_ventas', 'email_soporte', 'facebook', 'instagram',
            'twitter', 'linkedin', 'tiktok', 'mensaje_pdf', 'terminos_condiciones_pdf',
            'politica_garantia', 'gre_client_id_beta', 'gre_client_secret_beta',
            'gre_client_id_produccion', 'gre_client_secret_produccion',
            'gre_ruc_proveedor', 'gre_usuario_sol', 'gre_clave_sol',
            'endpoint_beta', 'endpoint_produccion', 'certificado_password'
        ];

        foreach ($nullableFields as $field) {
            if ($this->has($field) && empty($this->input($field))) {
                $this->merge([$field => null]);
            }
        }

        // Procesar arrays para asegurar formato correcto
        if ($this->has('cuentas_bancarias') && empty($this->input('cuentas_bancarias'))) {
            $this->merge(['cuentas_bancarias' => []]);
        }

        if ($this->has('billeteras_digitales') && empty($this->input('billeteras_digitales'))) {
            $this->merge(['billeteras_digitales' => []]);
        }
    }
}
{{--
    Multi-empresa: color de marca propio (ver config/empresas.php > color).
    Se incluye después de cargar el CSS del layout a propósito, para que
    gane por orden de cascada sin tocar ningún archivo de estilos existente.
    Compartido por todos los layouts (admin, pos, rol, login) — si alguno
    no lo incluye, esa pantalla se queda con el amarillo de Casa Drywall
    sin importar qué empresa esté activa.
--}}
@if ($colorEmpresa = config('empresas.activa.color'))
    <style>
        :root {
            --brand: {{ $colorEmpresa }};
            --brand-2: color-mix(in srgb, {{ $colorEmpresa }} 80%, black);
            --brand-bg: color-mix(in srgb, {{ $colorEmpresa }} 16%, transparent);
            --on-brand: #ffffff;
        }
    </style>
@endif

# Rental Tech SAC — Sistema de gestión (Laravel 12)

Migración a Laravel del sistema originalmente escrito en PHP plano
(`htdocs/RENTAL TECH`). Conserva el mismo diseño, los mismos módulos y las
mismas reglas de negocio.

## Requisitos

- PHP 8.2+
- MySQL 8
- Node 20+

## Puesta en marcha

```bash
composer install
npm install
cp .env.example .env          # ajusta las credenciales de la base de datos
php artisan key:generate
php artisan migrate --seed
npm run build                 # o `npm run dev` en desarrollo
php artisan serve
```

## Usuarios de ejemplo

Los crea el seeder; cámbialos antes de usar el sistema en producción.

| Usuario      | Contraseña      | Rol        | Panel          |
| ------------ | --------------- | ---------- | -------------- |
| `admin`      | `admin123`      | admin      | `/admin`       |
| `secretaria` | `secretaria123` | secretaria | `/secretaria`  |
| `contador`   | `contador123`   | contador   | `/contador`    |

El registro de usuarios (`/register`) exige una contraseña maestra, igual que en
el proyecto original. Se configura con `RT_MASTER_PASSWORD` (por defecto `654321`).

## Módulos

| Área                | Módulos                                                              |
| ------------------- | -------------------------------------------------------------------- |
| Principal           | Dashboard                                                            |
| Gestión comercial   | Clientes (+ destacados), Proveedores, Cotizaciones, Ventas           |
| Compras & documentos| Órdenes de Compra, Facturas (+ análisis de PDF con IA)               |
| Finanzas            | Cobranzas, Historial de Pagos, Ingresos, Egresos, Utilidad           |
| Inventario          | Productos, Categorías, Marcas, Almacenes, Merch                      |
| Logística           | Transporte, Guías de Remisión                                        |
| Recursos humanos    | Personal (con alta de accesos al sistema)                            |
| Análisis            | Reportes, Estadísticas, Rendimiento, Costeo, Galonaje                |

## Detalles de la migración

**Numeración de documentos.** El formato es el mismo (`COT-AAAAMMDD-NNNN`,
`V-…`, `OC-…`, `GR-…`), pero los cuatro dígitos finales son un correlativo
diario en lugar de un número aleatorio, para evitar colisiones.
Ver `app/Services/GeneradorCorrelativo.php`.

**Egresos automáticos.** `app/Services/SincronizadorEgresos.php` genera los
egresos derivados de ventas (flete, gasolina, promociones), órdenes de compra
recibidas y planilla mensual. Cada uno queda identificado por el par
`(origen, origen_id)`, así que la sincronización es idempotente.

**Galonaje.** La matriz de productos Kendall, sus categorías, presentaciones y
metas viven en `storage/app/galonaje/*.json`, igual que en el proyecto original.
Ver `app/Services/MatrizGalonaje.php`.

**Análisis de facturas con IA.** `app/Services/AnalizadorFacturaIA.php` usa el
SDK oficial de Anthropic con `claude-opus-5`. Requiere `ANTHROPIC_API_KEY`.

**Lectura de Excel.** `app/Services/LectorExcel.php` lee archivos `.xlsx` sin
dependencias externas, descomprimiendo el archivo y parseando el XML —
el mismo enfoque del original, pero genérico y reutilizable.

## Variables de entorno propias

```dotenv
RT_MASTER_PASSWORD=654321      # contraseña maestra para crear usuarios
RT_RAZON_SOCIAL="RENTAL TECH SAC"
RT_RUC=
RT_DIRECCION=
RT_IGV=0.18
ANTHROPIC_API_KEY=             # análisis de facturas en PDF
```

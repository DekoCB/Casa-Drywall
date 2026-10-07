<?php

namespace Tests\Unit;

use App\Services\LostConnectionDetector;
use Illuminate\Contracts\Database\LostConnectionDetector as LostConnectionDetectorContract;
use PDOException;
use Tests\TestCase;

/**
 * Caso real de producción (7 de octubre): guardar una Orden de Compra se
 * quedó "cargando" sin terminar — el log mostró una `PDOException:
 * SQLSTATE[HY000] [2002] Operation not permitted` al verificar la sesión
 * (un hipo breve de MySQL en el hosting compartido). Laravel ya reconecta
 * y reintenta solo en estos casos, pero solo reconoce una lista fija de
 * mensajes — ese en particular no estaba, así que el error se mostraba en
 * vez de recuperarse.
 */
class LostConnectionDetectorTest extends TestCase
{
    public function test_reconoce_el_error_real_de_produccion(): void
    {
        $detector = new LostConnectionDetector();

        $excepcion = new PDOException(
            "SQLSTATE[HY000] [2002] Operation not permitted"
        );

        $this->assertTrue($detector->causedByLostConnection($excepcion));
    }

    public function test_sigue_reconociendo_los_patrones_que_ya_trae_laravel(): void
    {
        $detector = new LostConnectionDetector();

        $excepcion = new PDOException('SQLSTATE[HY000] [2002] Connection refused');

        $this->assertTrue($detector->causedByLostConnection($excepcion));
    }

    public function test_no_marca_como_perdida_una_excepcion_sin_relacion(): void
    {
        $detector = new LostConnectionDetector();

        $excepcion = new PDOException('SQLSTATE[23000]: Integrity constraint violation');

        $this->assertFalse($detector->causedByLostConnection($excepcion));
    }

    public function test_el_contenedor_resuelve_el_detector_personalizado(): void
    {
        $this->assertInstanceOf(LostConnectionDetector::class, app(LostConnectionDetectorContract::class));
    }
}

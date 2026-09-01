<?php
/**
 * Procesador de Cola Asíncrona para Hosting Compartido
 *
 * Este archivo debe ser ejecutado por un Cron Job cada minuto en Hostinger:
 * * * * * * /usr/local/bin/php /home/uXXXXX/domains/tudominio.com/cron-jobs.php >> /dev/null 2>&1
 *
 * Procesa los jobs pendientes en la cola 'sunat-send' y termina cuando no hay más trabajos.
 * Incluye protección contra ejecuciones concurrentes.
 */

// Configuración
$basePath = __DIR__;
$lockFile = $basePath . '/storage/framework/queue-worker.lock';
$maxExecutionTime = 50; // segundos (deja margen antes del siguiente cron)

// Verificar que no esté corriendo otra instancia
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    $timeSinceLock = time() - $lockTime;

    // Si el lock tiene más de 60 segundos, considerarlo obsoleto
    if ($timeSinceLock < 60) {
        // Ya hay un proceso corriendo, salir silenciosamente
        exit(0);
    }

    // Lock obsoleto, eliminarlo
    @unlink($lockFile);
}

// Crear archivo de lock
file_put_contents($lockFile, getmypid());

// Función para limpiar al terminar
register_shutdown_function(function() use ($lockFile) {
    @unlink($lockFile);
});

try {
    // Cargar el autoloader de Composer
    require $basePath . '/vendor/autoload.php';

    // Cargar la aplicación Laravel
    $app = require_once $basePath . '/bootstrap/app.php';

    // Crear kernel de consola
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    // Configurar tiempo máximo de ejecución
    set_time_limit($maxExecutionTime);

    // Preparar argumentos para el comando
    $input = new Symfony\Component\Console\Input\ArrayInput([
        'command' => 'queue:work',
        '--queue' => 'sunat-send',
        '--stop-when-empty' => true,
        '--tries' => 3,
        '--max-time' => $maxExecutionTime - 5, // Margen de seguridad
        '--sleep' => 3,
        '--timeout' => 30,
    ]);

    // Crear salida
    $output = new Symfony\Component\Console\Output\BufferedOutput();

    // Ejecutar el comando
    $exitCode = $kernel->handle($input, $output);

    // Obtener la salida
    $outputText = $output->fetch();

    // Registrar en log solo si hubo actividad
    if (!empty(trim($outputText)) && $exitCode !== 0) {
        $logFile = $basePath . '/storage/logs/queue-worker.log';
        $logMessage = sprintf(
            "[%s] Exit Code: %d\n%s\n",
            date('Y-m-d H:i:s'),
            $exitCode,
            $outputText
        );
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    // Terminar la aplicación
    $kernel->terminate($input, $exitCode);

    exit($exitCode);

} catch (Throwable $e) {
    // Registrar error crítico
    $errorLog = $basePath . '/storage/logs/queue-worker-error.log';
    $errorMessage = sprintf(
        "[%s] ERROR: %s\nFile: %s\nLine: %s\nTrace:\n%s\n\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    file_put_contents($errorLog, $errorMessage, FILE_APPEND);

    exit(1);
}

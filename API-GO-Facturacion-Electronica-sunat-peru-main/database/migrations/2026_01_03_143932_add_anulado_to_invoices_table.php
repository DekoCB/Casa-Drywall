<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['invoices', 'boletas', 'credit_notes', 'debit_notes'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    // Agregar campo anulado si no existe
                    if (!Schema::hasColumn($tableName, 'anulado')) {
                        $table->boolean('anulado')->default(false)->after('estado_sunat');
                    }

                    // Agregar campo voided_document_id si no existe
                    if (!Schema::hasColumn($tableName, 'voided_document_id')) {
                        $table->unsignedBigInteger('voided_document_id')->nullable()->after('anulado');
                    }

                    // Agregar campo fecha_anulacion si no existe (boletas ya lo tiene como fecha_anulacion_local)
                    if (!Schema::hasColumn($tableName, 'fecha_anulacion') && !Schema::hasColumn($tableName, 'fecha_anulacion_local')) {
                        $table->timestamp('fecha_anulacion')->nullable()->after('voided_document_id');
                    }

                    // Agregar campo motivo_anulacion si no existe (boletas ya lo tiene como motivo_anulacion_local)
                    if (!Schema::hasColumn($tableName, 'motivo_anulacion') && !Schema::hasColumn($tableName, 'motivo_anulacion_local')) {
                        $table->text('motivo_anulacion')->nullable()->after('fecha_anulacion');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['invoices', 'boletas', 'credit_notes', 'debit_notes'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $columnsToRemove = [];

                    if (Schema::hasColumn($tableName, 'anulado')) {
                        $columnsToRemove[] = 'anulado';
                    }

                    if (Schema::hasColumn($tableName, 'voided_document_id')) {
                        $columnsToRemove[] = 'voided_document_id';
                    }

                    if (Schema::hasColumn($tableName, 'fecha_anulacion')) {
                        $columnsToRemove[] = 'fecha_anulacion';
                    }

                    if (Schema::hasColumn($tableName, 'motivo_anulacion')) {
                        $columnsToRemove[] = 'motivo_anulacion';
                    }

                    if (!empty($columnsToRemove)) {
                        $table->dropColumn($columnsToRemove);
                    }
                });
            }
        }
    }
};

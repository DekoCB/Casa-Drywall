<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cambiar fecha_emision de DATE a DATETIME en todas las tablas de documentos
        $tables = [
            'invoices',
            'boletas',
            'credit_notes',
            'debit_notes',
            'nota_ventas',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dateTime('fecha_emision')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a DATE (sin hora)
        $tables = [
            'invoices',
            'boletas',
            'credit_notes',
            'debit_notes',
            'nota_ventas',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->date('fecha_emision')->nullable(false)->change();
            });
        }
    }
};

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

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'mto_descuentos')) {
                Schema::table($table, function (Blueprint $table) {
                    // Agregar campo mto_descuentos
                    $table->decimal('mto_descuentos', 12, 2)->default(0);
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

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('mto_descuentos');
                });
            }
        }
    }
};

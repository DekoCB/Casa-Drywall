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
        Schema::table('voided_documents', function (Blueprint $table) {
            $table->string('serie', 4)->nullable()->after('tipo_documento')->comment('Serie para comunicación de baja (RA)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voided_documents', function (Blueprint $table) {
            $table->dropColumn('serie');
        });
    }
};

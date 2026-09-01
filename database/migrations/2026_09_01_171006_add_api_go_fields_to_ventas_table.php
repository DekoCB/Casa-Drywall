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
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedInteger('api_go_document_id')->nullable()->after('numero_sunat');
            $table->string('api_go_document_type', 20)->nullable()->after('api_go_document_id');
            $table->string('api_go_pdf_path')->nullable()->after('api_go_document_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['api_go_document_id', 'api_go_document_type', 'api_go_pdf_path']);
        });
    }
};

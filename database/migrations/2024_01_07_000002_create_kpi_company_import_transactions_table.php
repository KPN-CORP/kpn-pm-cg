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
        Schema::create('kpi_company_import_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('file_uploads')->nullable();
            $table->integer('success')->default(0);
            $table->integer('error')->default(0);
            $table->json('detail_error')->nullable();
            $table->string('import_type')->default('achievement');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_company_import_transactions');
    }
};

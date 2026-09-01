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
        Schema::create('failed_file_meta_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('failed_file_id')->constrained('failed_files')->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->float('confidence_level')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_file_meta_data');
    }
};

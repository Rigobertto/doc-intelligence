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
        Schema::table('file_meta_data', function (Blueprint $table) {
            $table->float('confidence_level')->nullable()->after('data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('file_meta_data', function (Blueprint $table) {
            $table->dropColumn('confidence_level');
        });
    }
};

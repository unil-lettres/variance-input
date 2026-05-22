<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comparisons', function (Blueprint $table) {
            $table->integer('medite_runtime_ms')->nullable();
            $table->integer('medite_peak_rss_kb')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comparisons', function (Blueprint $table) {
            $table->dropColumn(['medite_runtime_ms', 'medite_peak_rss_kb']);
        });
    }
};

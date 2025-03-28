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
        Schema::table('comparisons', function (Blueprint $table) {
            $table->integer('lg_pivot')->nullable();
            $table->integer('ratio')->nullable();
            $table->integer('seuil')->nullable();
            $table->boolean('case_sensitive')->default(false);
            $table->boolean('diacri_sensitive')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comparisons', function (Blueprint $table) {
            $table->dropColumn([
                'lg_pivot',
                'ratio',
                'seuil',
                'case_sensitive',
                'diacri_sensitive'
            ]);
        });
    }
};

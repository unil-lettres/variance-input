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
            // make these columns accept NULL
            $table->double('number')->nullable()->change();
            $table->string('prefix_label')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comparisons', function (Blueprint $table) {
            // revert them back to NOT NULL
            $table->double('number')->nullable(false)->change();
            $table->string('prefix_label')->nullable(false)->change();
        });
    }
};

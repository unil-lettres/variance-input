<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->nullable()->constrained('versions')->onDelete('set null');
            $table->foreignId('target_id')->nullable()->constrained('versions')->onDelete('set null');
            $table->string('folder', 45);
            $table->float('number');
            $table->string('prefix_label', 250);
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('comparisons');
    }
};

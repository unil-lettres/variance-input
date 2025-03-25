<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparisons_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparisons_source_id')->constrained('comparisons')->onDelete('cascade');
            $table->foreignId('comparisons_target_id')->constrained('comparisons')->onDelete('cascade');
            $table->tinyInteger('order')->default(0);
            $table->string('medite', 250)->nullable();
            $table->tinyInteger('status')->default(0);
            $table->tinyInteger('validation')->default(0);
            $table->tinyInteger('publication')->default(0);
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('comparisons_status');
    }
};

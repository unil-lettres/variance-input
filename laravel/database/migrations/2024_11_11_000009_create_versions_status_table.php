<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versions_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('versions')->onDelete('cascade');
            $table->tinyInteger('order')->default(0);
            $table->year('date')->nullable();
            $table->tinyInteger('typo_status')->default(0);
            $table->tinyInteger('metadata_status')->default(0);
            $table->tinyInteger('chapters_status')->default(0);
            $table->tinyInteger('facsimile_status')->default(0);
            $table->timestamp('last_modif')->useCurrent();
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('versions_status');
    }
};

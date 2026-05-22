<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('works_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->constrained('works')->onDelete('cascade');
            $table->tinyInteger('global_status')->default(0);
            $table->tinyInteger('desc_status')->default(0);
            $table->tinyInteger('notice_status')->default(0);
            $table->tinyInteger('image_status')->default(0);
            $table->tinyInteger('comparison_status')->default(0);
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('works_status');
    }
};

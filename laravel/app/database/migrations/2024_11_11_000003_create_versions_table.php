<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->nullable()->constrained('works')->onDelete('set null');
            $table->string('name', 45);
            $table->string('folder', 45);
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('versions');
    }
};

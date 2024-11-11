<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('authors')->onDelete('set null');
            $table->string('title', 80);
            $table->string('folder', 45);
            $table->text('desc');
            $table->text('image_url');
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('works');
    }
};

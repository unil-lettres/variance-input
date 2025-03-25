<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->string('folder', 45);
            $table->string('level', 120);
            $table->string('label_source', 250);
            $table->string('label_target', 250);
            $table->integer('chapter_parent')->nullable();
            $table->string('start_line_source', 6);
            $table->string('start_line_target', 6);
            $table->tinyInteger('id_tome_source');
            $table->tinyInteger('id_tome_target');
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};

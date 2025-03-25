<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('author_id')->nullable()->constrained('authors')->onDelete('cascade');
            $table->foreignId('work_id')->nullable()->constrained('works')->onDelete('cascade');
            $table->string('permission_type')->default('edit');
            $table->timestamps();
    
            $table->unique(['user_id', 'author_id', 'work_id', 'permission_type']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};

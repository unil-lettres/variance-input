<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Align DB column with UI/back-end validation (was VARCHAR(45))
        DB::statement('ALTER TABLE versions MODIFY name VARCHAR(100)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE versions MODIFY name VARCHAR(45)');
    }
};

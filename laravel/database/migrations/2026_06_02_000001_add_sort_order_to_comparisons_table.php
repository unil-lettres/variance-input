<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comparisons', function (Blueprint $table) {
            $table->double('sort_order')->nullable()->after('number');
        });

        DB::table('comparisons')->update([
            'sort_order' => DB::raw('number'),
        ]);
    }

    public function down(): void
    {
        Schema::table('comparisons', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->boolean('pagination_done')
                ->default(false)
                ->after('folder');

            $table->timestamp('pagination_done_at')
                ->nullable()
                ->after('pagination_done');

            $table->foreignId('pagination_done_by')
                ->nullable()
                ->after('pagination_done_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->dropForeign(['pagination_done_by']);
            $table->dropColumn([
                'pagination_done',
                'pagination_done_at',
                'pagination_done_by',
            ]);
        });
    }
};

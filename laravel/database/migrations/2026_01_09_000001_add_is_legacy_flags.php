<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->boolean('is_legacy')->default(false)->after('order');
        });

        Schema::table('works', function (Blueprint $table) {
            $table->boolean('is_legacy')->default(false)->after('pdf_url');
        });

        Schema::table('versions', function (Blueprint $table) {
            $table->boolean('is_legacy')->default(false)->after('folder');
        });

        Schema::table('comparisons', function (Blueprint $table) {
            $table->boolean('is_legacy')->default(false)->after('prefix_label');
        });
    }

    public function down(): void
    {
        Schema::table('comparisons', function (Blueprint $table) {
            $table->dropColumn('is_legacy');
        });

        Schema::table('versions', function (Blueprint $table) {
            $table->dropColumn('is_legacy');
        });

        Schema::table('works', function (Blueprint $table) {
            $table->dropColumn('is_legacy');
        });

        Schema::table('authors', function (Blueprint $table) {
            $table->dropColumn('is_legacy');
        });
    }
};

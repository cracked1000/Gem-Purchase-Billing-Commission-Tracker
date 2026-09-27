<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an EXPORT-ONLY category override to each gem row.
 *
 * This does NOT touch the gem's name, receipt, pricing or the bill in any
 * way. It is purely a hint the Gem Export screen uses to place a gem into a
 * chosen bucket when the auto-classification didn't get it right (spelling,
 * unusual names, etc.). Leaving it null means "use auto-classification."
 *
 * Safe: adds a nullable column, never deletes/changes existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('export_category', 40)->nullable()->after('stone_notes');
            $table->index('export_category');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex(['export_category']);
            $table->dropColumn('export_category');
        });
    }
};

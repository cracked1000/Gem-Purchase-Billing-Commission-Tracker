<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks every payment made to a seller, so we can compute an accurate running
 * outstanding balance per seller — to the exact decimal, never rounded or
 * abbreviated.
 *
 * Additive & safe: creates a brand-new table, never touches existing data.
 * Payments are recorded (not deleted) to keep a full audit trail; the restore
 * flow and this table are protected by a backup snapshot before any write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 16, 2);
            $table->dateTime('paid_at');
            $table->string('method', 40)->nullable()->comment('cash | bank | transfer | cheque | other');
            $table->string('reference', 120)->nullable()->comment('cheque/bank reference or note');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['seller_id']);
            $table->index(['paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 20)->nullable()->unique();
            $table->foreignId('seller_id')->constrained()->restrictOnDelete();
            $table->date('sold_at');
            $table->decimal('commission_percent', 5, 2)->default(10);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->decimal('net_payable', 14, 2)->default(0);
            $table->unsignedInteger('total_pieces')->default(0);
            $table->string('status', 10)->default('pending')->comment('pending|paid - payout to seller');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sold_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

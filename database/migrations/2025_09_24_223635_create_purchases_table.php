<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_price_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_method_id')->constrained()->onDelete('cascade');

            $table->string('ticket_number')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10);
            $table->enum('status', ['processing', 'pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('transaction_id')->nullable();

            // Nuevos campos para comprobante
            $table->string('payment_reference')->nullable(); // Referencia del pago (número de transferencia, etc)
            $table->text('payment_proof_url')->nullable(); // URL del comprobante en S3
            $table->string('qr_code_url')->nullable(); // ✅ URL del código QR de la compra
            $table->integer('quantity')->default(1); // Cantidad de tickets en esta compra
            $table->decimal('total_amount', 10, 2)->nullable(); // Total de la compra

            $table->timestamps();

            // Constraints
            $table->unique(['event_id', 'ticket_number']);
            $table->index(['event_id', 'status']);
            $table->index('user_id');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};

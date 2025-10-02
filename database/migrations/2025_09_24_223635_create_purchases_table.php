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

            // ✅ user_id ahora es nullable y sin cascade delete
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // ✅ Campos obligatorios para identificar al comprador
            $table->string('email');
            $table->string('whatsapp', 20);

            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_price_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_method_id')->constrained()->onDelete('cascade');

            $table->string('ticket_number')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10);
            $table->enum('status', ['processing', 'pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('transaction_id')->nullable();

            // Campos para comprobante
            $table->string('payment_reference')->nullable();
            $table->text('payment_proof_url')->nullable();
            $table->string('qr_code_url')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('total_amount', 10, 2)->nullable();

            $table->timestamps();

            // Constraints e índices
            $table->unique(['event_id', 'ticket_number']);
            $table->index(['event_id', 'status']);
            $table->index('user_id');
            $table->index('transaction_id');
            $table->index('email');      // ✅ Índice para búsquedas por email
            $table->index('whatsapp');   // ✅ Índice para búsquedas por whatsapp
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};

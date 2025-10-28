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
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('email')->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('identificacion', 20)->nullable();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_price_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_method_id')->constrained()->onDelete('cascade');
            $table->string('ticket_number')->nullable();
            $table->string('currency', 10);
            $table->decimal('amount', 10, 2)->default(0); // ✅ Sin ->change()
            $table->decimal('total_amount', 10, 2)->default(0); // ✅ Sin ->change()
            $table->enum('status', ['processing', 'pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->boolean('is_admin_purchase')->default(false); // ✅ Sin ->after()
            $table->string('transaction_id')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('payment_proof_url')->nullable();
            $table->string('qr_code_url')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamps();

            // Índices
            $table->unique(['event_id', 'ticket_number']);
            $table->index(['event_id', 'status']);
            $table->index('user_id');
            $table->index('transaction_id');
            $table->index('email');
            $table->index('whatsapp');
            $table->index('identificacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};

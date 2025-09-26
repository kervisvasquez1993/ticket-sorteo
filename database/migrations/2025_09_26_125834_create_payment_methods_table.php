<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Zelle, Pago Móvil, Zinli, Binance
            $table->string('type'); // zelle, pago_movil, zinli, binance
            $table->text('description')->nullable();
            $table->json('configuration')->nullable(); // Para guardar datos específicos de cada método
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0); // Para ordenar los métodos de pago
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};

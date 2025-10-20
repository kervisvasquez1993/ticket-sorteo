<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url');
            $table->boolean('is_main')->default(false);
            $table->timestamps();

            $table->index(['event_id', 'is_main']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_prizes');
    }
};

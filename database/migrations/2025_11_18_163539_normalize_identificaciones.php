<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Purchase;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Procesar en lotes para mejor rendimiento
        Purchase::whereNotNull('identificacion')
            ->chunkById(100, function ($purchases) {
                foreach ($purchases as $purchase) {
                    // Obtener el valor original sin pasar por el accessor
                    $originalIdentificacion = $purchase->getRawOriginal('identificacion');

                    // Normalizar la identificación
                    $normalized = Purchase::normalizeIdentificacion($originalIdentificacion);

                    // Solo actualizar si cambió
                    if ($normalized !== $originalIdentificacion) {
                        // Usar updateQuietly para evitar eventos y timestamps
                        DB::table('purchases')
                            ->where('id', $purchase->id)
                            ->update(['identificacion' => $normalized]);
                    }
                }
            });

        // Log del proceso
        $totalNormalized = Purchase::whereNotNull('identificacion')->count();
        Log::info("Normalizadas {$totalNormalized} identificaciones en la tabla purchases");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No hay forma de revertir esto de manera confiable
        // ya que perdimos la información de los formatos originales
        Log::warning('No se puede revertir la normalización de identificaciones');
    }
};

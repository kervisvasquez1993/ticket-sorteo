<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'configuration',
        'is_active',
        'order'
    ];

    protected $casts = [
        'configuration' => 'array',
        'is_active' => 'boolean',
        'order' => 'integer'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function getConfigValue($key, $default = null)
    {
        return data_get($this->configuration, $key, $default);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    const TYPE_ZELLE = 'zelle';
    const TYPE_PAGO_MOVIL = 'pago_movil';
    const TYPE_ZINLI = 'zinli';
    const TYPE_BINANCE = 'binance';


    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_ZELLE => 'Zelle',
            self::TYPE_PAGO_MOVIL => 'Pago Móvil',
            self::TYPE_ZINLI => 'Zinli',
            self::TYPE_BINANCE => 'Binance',
        ];
    }
}

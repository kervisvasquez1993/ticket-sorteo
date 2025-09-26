<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => 'Zelle',
                'type' => PaymentMethod::TYPE_ZELLE,
                'description' => 'Transferencia mediante Zelle',
                'configuration' => [
                    'email' => 'admin@example.com',
                    'phone' => '+1234567890',
                    'account_holder' => 'Nombre del titular'
                ],
                'is_active' => true,
                'order' => 1
            ],
            [
                'name' => 'Pago Móvil',
                'type' => PaymentMethod::TYPE_PAGO_MOVIL,
                'description' => 'Pago móvil de bancos venezolanos',
                'configuration' => [
                    'bank' => '0102',
                    'phone' => '04121234567',
                    'cedula' => 'V12345678',
                    'account_holder' => 'Nombre del titular'
                ],
                'is_active' => true,
                'order' => 2
            ],
            [
                'name' => 'Zinli',
                'type' => PaymentMethod::TYPE_ZINLI,
                'description' => 'Transferencia mediante Zinli',
                'configuration' => [
                    'username' => '@zinliuser',
                    'phone' => '+584121234567',
                    'account_holder' => 'Nombre del titular'
                ],
                'is_active' => true,
                'order' => 3
            ],
            [
                'name' => 'Binance',
                'type' => PaymentMethod::TYPE_BINANCE,
                'description' => 'Pago mediante Binance Pay',
                'configuration' => [
                    'binance_id' => '123456789',
                    'email' => 'binance@example.com',
                    'account_holder' => 'Nombre del titular',
                    'accepted_cryptocurrencies' => ['USDT', 'BUSD', 'BTC']
                ],
                'is_active' => true,
                'order' => 4
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::create($method);
        }
    }
}

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
                    'email' => 'Gnztiendapagos@gmail.com',
                    'account_holder' => 'Gnz Tienda 1995 INC'
                ],
                'is_active' => true,
                'order' => 1
            ],
            [
                'name' => 'Pago Móvil',
                'type' => PaymentMethod::TYPE_PAGO_MOVIL,
                'description' => 'Pago móvil de bancos venezolanos',
                'configuration' => [
                    'bank' => 'Banesco',
                    'phone' => '04164679698',
                    'cedula' => '19689724',
                    'account_holder' => 'Gnz Tienda 1995 INC'
                ],
                'is_active' => true,
                'order' => 2
            ],
            [
                'name' => 'Binance',
                'type' => PaymentMethod::TYPE_BINANCE,
                'description' => 'Pago mediante Binance Pay',
                'configuration' => [
                    'email' => 'Daneiljose42@gmail.com',
                    'account_holder' => 'Daniel Gnz',
                    'accepted_cryptocurrencies' => ['USDT', 'BUSD', 'BTC']
                ],
                'is_active' => true,
                'order' => 3
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::updateOrCreate(
                ['type' => $method['type']],
                $method
            );
        }
    }
}

<?php

return [
    /**
     * Configuración para compras administrativas masivas
     */
    'admin_massive' => [
        /**
         * Identificación por defecto para compras masivas administrativas
         * cuando no se especifica una en el request
         */
        'default_identificacion' => env('ADMIN_MASSIVE_DEFAULT_ID', 'V-24466476'),

        /**
         * Prefijo para transaction_id de compras masivas admin
         */
        'transaction_prefix' => 'ADMIN-MASSIVE',

        /**
         * Límite máximo de tickets por compra masiva
         */
        'max_quantity' => env('ADMIN_MASSIVE_MAX_QUANTITY', 10000),

        /**
         * Auto-aprobar compras masivas por defecto
         */
        'auto_approve_default' => true,
    ],

    /**
     * Métodos de pago configurados
     */
    'payment_methods' => [
        'admin_massive_default_type' => 'pago_movil', // Tipo de pago para admin masivo
    ],

    /**
     * Moneda por defecto para compras admin
     */
    'admin_massive_currency' => 'VES',
];

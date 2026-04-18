<?php

/**
 * RABBITMQ KONFİQURASİYASI
 * =========================
 * RabbitMQ bağlantı parametrləri.
 * .env faylından oxunur, default dəyərlər burada təyin olunur.
 */
return [
    'host' => env('RABBITMQ_HOST', 'localhost'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'exchange' => env('RABBITMQ_EXCHANGE', 'domain_events'),
];

<?php

return [

    /*
    | Data admin awal. Dipakai oleh AdminUserSeeder.
    | Nilai diambil dari .env (jangan commit password asli ke repository).
    */
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'username' => env('ADMIN_USERNAME', 'admin'),
        'email' => env('ADMIN_EMAIL', 'admin@broseven.test'),
        'phone' => env('ADMIN_PHONE'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    | Integrasi WhatsApp.
    |   driver : click_to_chat (membuka WhatsApp dengan pesan terisi, perlu konfirmasi pengguna)
    | Driver lain (mis. WhatsApp Business API) ditambahkan di AppServiceProvider.
    */
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'click_to_chat'),

        'click_to_chat' => [
            'base_url' => env('WHATSAPP_BASE_URL', 'https://wa.me'),
        ],
    ],

];
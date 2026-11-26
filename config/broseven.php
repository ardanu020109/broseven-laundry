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
    | Integrasi WhatsApp. driver: click_to_chat (membuka WhatsApp dengan pesan terisi).
    | Driver lain (mis. WhatsApp Business API) ditambahkan di AppServiceProvider.
    */
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'click_to_chat'),

        'click_to_chat' => [
            'base_url' => env('WHATSAPP_BASE_URL', 'https://wa.me'),
        ],
    ],

    /* Export laporan Excel: batas baris rincian transaksi (total tetap mencakup semua). */
    'reports' => [
        'export_limit' => (int) env('REPORT_EXPORT_LIMIT', 10000),
    ],

    /*
    | Keamanan.
    |   csp             : off | report-only | enforce
    |   trusted_proxies : "*" atau daftar IP dipisah koma (isi jika di belakang proxy/CDN)
    */
    'security' => [
        'csp' => env('CSP_MODE', 'report-only'),
        'trusted_proxies' => env('TRUSTED_PROXIES'),
    ],

    /* Retensi data (hari). Log aktivitas minimal 30 hari. */
    'retention' => [
        'activity_log_days' => (int) env('ACTIVITY_LOG_RETENTION_DAYS', 365),
        'backup_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    ],

    /* Backup database. mysqldump: nama perintah atau path lengkap. */
    'backup' => [
        'enabled' => (bool) env('BACKUP_ENABLED', true),
        'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
    ],

];
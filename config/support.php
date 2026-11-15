<?php

return [
    'email' => env('SUPPORT_EMAIL', 'gta@qla.dev'),
    // The production shared host supplies sendmail; SMTP can be selected instead.
    'mailer' => env('SUPPORT_MAILER', 'sendmail'),
    'from' => env('SUPPORT_FROM_ADDRESS', 'gta@qla.dev'),
    'terms_version' => '2026-11-15',
];

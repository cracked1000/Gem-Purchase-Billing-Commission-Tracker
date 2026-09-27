<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GemDesk settings
    |--------------------------------------------------------------------------
    | Your company details printed on every bill, plus the default commission
    | percentage used when a new bill is created (it can be changed per bill).
    */

    'company_name'    => env('GEMDESK_COMPANY_NAME', 'GemDesk Traders (Pvt) Ltd'),
    'company_address' => env('GEMDESK_COMPANY_ADDRESS', 'No. 00, Galle Road, Colombo 03, Sri Lanka'),
    'company_phone'   => env('GEMDESK_COMPANY_PHONE', '+94 77 000 0000'),
    'company_email'   => env('GEMDESK_COMPANY_EMAIL', ''),

    // Default commission % taken from the seller (editable on every bill).
    'default_commission' => (float) env('GEMDESK_DEFAULT_COMMISSION', 10),

    // Country code used to normalise local numbers (07x xxx xxxx) for wa.me links.
    'whatsapp_country_code' => env('GEMDESK_WA_COUNTRY_CODE', '94'),

    /*
    |--------------------------------------------------------------------------
    | Receipts folder (drag-&-drop WhatsApp flow)
    |--------------------------------------------------------------------------
    | Every finalised bill saves its receipt PDF here, named after the seller
    | (e.g. Ravi-Perera-0777123456-0608202601.pdf). Relative paths resolve
    | against the project root, so the default "receipts" sits right next to
    | run.bat / run.sh. You can also use an absolute path
    | (e.g. D:\GemDesk Receipts on Windows).
    */
    'receipts_folder' => env('GEMDESK_RECEIPTS_FOLDER', 'receipts'),

    // Auto-open the seller's WhatsApp chat + the receipts folder (with the
    // PDF selected) when a bill is finalised — just drag & drop to send.
    'auto_open_chat_on_finalize' => filter_var(env('GEMDESK_WA_AUTO_OPEN', true), FILTER_VALIDATE_BOOL),

    // Number of timestamped JSON snapshots retained in storage/app/backups.
    'backup_keep' => (int) env('GEMDESK_BACKUP_KEEP', 30),

    'currency' => 'LKR',
];

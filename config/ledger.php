<?php

declare(strict_types=1);

return [

    /*
    | The single user, created by the UserSeeder.
    */
    'user' => [
        'name' => env('LEDGER_USER_NAME', 'Ledger user'),
        'email' => env('LEDGER_USER_EMAIL', 'me@ledger.test'),
        'password' => env('LEDGER_USER_PASSWORD', 'password'),
    ],

    /*
    | Defaults for the two settings stored in the `settings` table.
    */
    'defaults' => [
        'week_start' => 'monday',
        'stale_days' => 3,
    ],

    /*
    | Seconds after logging during which Undo is allowed (SPEC §4.4).
    */
    'undo_seconds' => 60,

    /*
    | Shown under the brand in the sidebar.
    */
    'host_label' => env('LEDGER_HOST_LABEL', 'localhost:8080'),

    /*
    | Force a file step to fail, to exercise the partial-failure state.
    | Leave false outside of manual testing.
    */
    'force_failure' => [
        'pdf' => (bool) env('LEDGER_FORCE_PDF_FAILURE', false),
        'xlsx' => (bool) env('LEDGER_FORCE_XLSX_FAILURE', false),
    ],

];

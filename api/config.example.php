<?php
// Copy this file to config.php and fill in your values.
// config.php is gitignored and never uploaded by the deploy: on the server it is edited by hand.
return [
    'db_host'     => '127.0.0.1',   // not 'localhost': PHP would look for a Unix socket instead of Docker's port
    'db_port'     => 3306,
    'db_user'     => 'interapt_user',
    'db_password' => 'interapt_password',
    'db_name'     => 'interapt',

    // One course key per semester. The key a student registers with decides their cohort, so next semester's
    // key starts new accounts (repeating students). Add the new semester's key; keep old ones only while their
    // students should still be able to log in on a new device.
    'cohort_keys' => [
        'ws2627' => 'your_course_key',
    ],

    // Signs the login cookie. At least 32 characters; changing it logs everyone out.
    // Generate one with: php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
    'auth_secret' => '',

    // The modules that may save progress: the name each module sends (MODULE_NAME in its storage-prefix.ts).
    'modules' => ['pohl', 'precession'],
];

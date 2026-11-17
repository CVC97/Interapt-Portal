<?php
require_once __DIR__ . '/../lib.php';

// Who is logged in: the modules ask on startup, the landing and login pages to show the state. 401 = guest.
requireMethod('GET');

$user = requireUser();
respond(200, ['username' => $user['username'], 'cohort' => $user['cohort']]);

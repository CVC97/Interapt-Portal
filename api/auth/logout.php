<?php
require_once __DIR__ . '/../lib.php';

requireMethod('POST');
clearAuthCookie();
respond(200, ['ok' => true]);

<?php
require_once __DIR__ . '/../lib.php';

// Step 1 of the login page: is the key valid, and does this username exist in the key's cohort?
requireMethod('POST');

$body   = jsonBody();
$cohort = cohortForKey($body['key'] ?? null);

$username = validUsername($body['username'] ?? null);
if ($username === null) respond(400, ['error' => 'invalid username']);

respond(200, ['exists' => findUser($cohort, $username) !== null]);

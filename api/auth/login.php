<?php
require_once __DIR__ . '/../lib.php';

// An existing account on a new device: key, username and the codename it was created with. No password: the
// codename is the check, so the answer never tells which of the two was wrong and the stored code never leaves
// the server.
requireMethod('POST');

$body   = jsonBody();
$cohort = cohortForKey($body['key'] ?? null);

$username = validUsername($body['username'] ?? null);
$codename = validCodename($body['codename'] ?? null);
$user     = $username === null ? null : findUser($cohort, $username);

if ($user === null || $codename === null || !hash_equals($user['codename'], $codename)) {
    respond(401, ['error' => 'no match']);
}

getDb()->prepare('UPDATE users SET last_active = UTC_TIMESTAMP(3) WHERE id = ?')->execute([$user['id']]);
issueAuthCookie((int) $user['id']);
respond(200, ['username' => $user['username'], 'cohort' => $cohort]);

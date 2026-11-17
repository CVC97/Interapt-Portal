<?php
require_once __DIR__ . '/../lib.php';

// An existing account on a new device: key, username and the codename it was created with. No password: the
// codename is the check, so the answer never tells which of the two was wrong and the stored code never leaves
// the server. After MAX_FAILED_LOGIN wrong codes the account is locked until reset in the database.
requireMethod('POST');

$body   = jsonBody();
$cohort = cohortForKey($body['key'] ?? null);

$username = validUsername($body['username'] ?? null);
$codename = validCodename($body['codename'] ?? null);
$user     = $username === null ? null : findUser($cohort, $username);

if ($user === null) respond(401, ['error' => 'no match']);
if ((int) $user['failed_logins'] >= MAX_FAILED_LOGIN) respond(423, ['error' => 'locked']);

$pdo = getDb();
if ($codename === null || !hash_equals($user['codename'], $codename)) {
    $pdo->prepare('UPDATE users SET failed_logins = failed_logins + 1 WHERE id = ?')->execute([$user['id']]);
    respond(401, ['error' => 'no match']);
}

$pdo->prepare('UPDATE users SET failed_logins = 0, last_active = UTC_TIMESTAMP(3) WHERE id = ?')->execute([$user['id']]);
issueAuthCookie((int) $user['id']);
respond(200, ['username' => $user['username'], 'cohort' => $cohort]);

<?php
require_once __DIR__ . '/../lib.php';

// A new account: key, username, codename and the consent to the research use. Logs the new user in.
requireMethod('POST');

$body   = jsonBody();
$cohort = cohortForKey($body['key'] ?? null);

$username = validUsername($body['username'] ?? null);
if ($username === null) respond(400, ['error' => 'invalid username']);

$codename = validCodename($body['codename'] ?? null);
if ($codename === null) respond(400, ['error' => 'invalid codename']);

if (($body['consent'] ?? null) !== true) respond(400, ['error' => 'consent required']);

// No ON DUPLICATE KEY here: a username taken in the meantime must never log anyone into someone else's account.
try {
    getDb()->prepare(
        'INSERT INTO users (cohort, username, codename, consent_at) VALUES (?, ?, ?, UTC_TIMESTAMP(3))'
    )->execute([$cohort, $username, $codename]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') respond(409, ['error' => 'username taken']);
    throw $e;
}

issueAuthCookie((int) getDb()->lastInsertId());
respond(201, ['username' => $username, 'cohort' => $cohort]);

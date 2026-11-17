<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Anything unexpected ends as a JSON 500; the details only go to the server's error log.
set_exception_handler(function (Throwable $e) {
    error_log('API error: ' . $e->getMessage());
    respond(500, ['error' => 'Server error']);
});

const AUTH_COOKIE      = 'interapt_auth';
const AUTH_MAX_AGE     = 200 * 24 * 3600;   // a semester and a bit: one login lasts the course
const MAX_FAILED_LOGIN = 10;                // the codename has little entropy: stop guessing early

function config(): array {
    static $cfg = null;
    return $cfg ??= require __DIR__ . '/config.php';
}

function respond(int $status, array $data): void {
    http_response_code($status);
    exit(json_encode($data));
}

function requireMethod(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        respond(405, ['error' => 'Method not allowed']);
    }
}

function jsonBody(): array {
    $body = json_decode(file_get_contents('php://input'), true);
    return is_array($body) ? $body : [];
}

// Letters, digits and . _ - @ only. The rogue_user_ prefix marks guests in the modules.
function validUsername($username): ?string {
    if (!is_string($username)) return null;
    $username = trim($username);
    if (!preg_match('/^[\p{L}\p{N}._@-]{1,100}$/u', $username)) return null;
    if (str_starts_with(strtolower($username), 'rogue_user_')) return null;
    return $username;
}

// The browser builds the code (login/codename.js) and sends it normalised: upper-case letters and digits.
// Deliberately loose, so changing the scheme only touches codename.js.
function validCodename($codename): ?string {
    if (!is_string($codename) || !preg_match('/^[A-Z0-9]{2,32}$/', $codename)) return null;
    return $codename;
}

// One key per cohort (semester) in config.php; the key a student enters decides their cohort.
function cohortForKey($key): string {
    if (is_string($key)) {
        $key = trim($key);
        foreach (config()['cohort_keys'] ?? [] as $cohort => $expected) {
            if ((string) $expected !== '' && hash_equals((string) $expected, $key)) return (string) $cohort;
        }
    }
    respond(401, ['error' => 'invalid key']);
}

// Only the modules listed in config.php may store progress; returns the module's row id.
function requireModule($name): int {
    if (!is_string($name) || !in_array($name, config()['modules'] ?? [], true)) {
        respond(400, ['error' => 'unknown module']);
    }
    $pdo    = getDb();
    $select = $pdo->prepare('SELECT id FROM modules WHERE name = ?');
    $select->execute([$name]);
    $id = $select->fetchColumn();
    if ($id === false) {
        $pdo->prepare('INSERT IGNORE INTO modules (name) VALUES (?)')->execute([$name]);
        $select->execute([$name]);
        $id = $select->fetchColumn();
    }
    return (int) $id;
}


// ── Login cookie ─────────────────────────────────────────────────────────────────────────────────────────────
// "<user id>.<issued at>.<HMAC>", signed with auth_secret: no session table, and no password stored anywhere.
// Path=/ so every module below the server root sees it; HttpOnly so page scripts can't read it.

function authSecret(): string {
    $secret = (string) (config()['auth_secret'] ?? '');
    if (strlen($secret) < 32) throw new RuntimeException('auth_secret in api/config.php is missing or too short');
    return $secret;
}

function isHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function setAuthCookie(string $value, int $expires): void {
    setcookie(AUTH_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function issueAuthCookie(int $userId): void {
    $payload = $userId . '.' . time();
    setAuthCookie($payload . '.' . hash_hmac('sha256', $payload, authSecret()), time() + AUTH_MAX_AGE);
}

function clearAuthCookie(): void {
    setAuthCookie('', time() - 3600);
}

// The logged-in user (id, username, cohort), or null without a valid cookie.
function currentUser(): ?array {
    $parts = explode('.', (string) ($_COOKIE[AUTH_COOKIE] ?? ''));
    if (count($parts) !== 3 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) return null;
    [$userId, $issued, $signature] = $parts;
    if (!hash_equals(hash_hmac('sha256', "$userId.$issued", authSecret()), $signature)) return null;
    if ((int) $issued + AUTH_MAX_AGE < time()) return null;

    $stmt = getDb()->prepare('SELECT id, username, cohort FROM users WHERE id = ?');
    $stmt->execute([(int) $userId]);
    $user = $stmt->fetch();
    return $user === false ? null : ['id' => (int) $user['id'], 'username' => $user['username'], 'cohort' => $user['cohort']];
}

function requireUser(): array {
    $user = currentUser();
    if ($user === null) respond(401, ['error' => 'not logged in']);
    return $user;
}

function findUser(string $cohort, string $username): ?array {
    $stmt = getDb()->prepare('SELECT id, username, codename, failed_logins FROM users WHERE cohort = ? AND username = ?');
    $stmt->execute([$cohort, $username]);
    $user = $stmt->fetch();
    return $user === false ? null : $user;
}

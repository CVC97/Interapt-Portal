<?php
// Local development router, used only with (run from the repository root):
//   php -S localhost:8000 api/router.php
// The production routes are in .htaccess; keep both lists in step.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$routes = [
    '/api/auth/check'    => __DIR__ . '/auth/check.php',
    '/api/auth/register' => __DIR__ . '/auth/register.php',
    '/api/auth/login'    => __DIR__ . '/auth/login.php',
    '/api/auth/logout'   => __DIR__ . '/auth/logout.php',
    '/api/auth/me'       => __DIR__ . '/auth/me.php',
    '/api/progress'      => __DIR__ . '/progress/load.php',
    '/api/progress/save' => __DIR__ . '/progress/save.php',
];

if (isset($routes[$path])) {
    require $routes[$path];
    return true;
}

// Never serve the config, nor run anything else below api/ directly.
if (str_starts_with($path, '/api/')) {
    http_response_code(404);
    return true;
}

// Everything else (index.html, login/, assets/) as a plain file.
return false;

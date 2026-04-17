<?php
// Normalize proxy headers so that code which inspects
// $_SERVER['HTTPS'] / $_SERVER['SERVER_PORT'] sees the real
// client-facing values — not the internal HTTP:80 Apache receives
// from Traefik / Caddy / nginx in front.
//
// Loaded via php.ini `auto_prepend_file` so it runs before any
// application code, on every request.

if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
}

if (!empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
    $_SERVER['SERVER_PORT'] = (int) $_SERVER['HTTP_X_FORWARDED_PORT'];
}

if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
}

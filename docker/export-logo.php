<?php
/*
 * export-logo.php
 *
 * Regenerates ./themes/new/images/logo_cache.png from the DB
 * (firmendaten.firmenlogo, stored as base64) on every container start.
 *
 * Why:
 *   The OpenXE login page references ?module=welcome&action=logo,
 *   which bounces through the framework dispatcher and (when the
 *   request is unauthenticated) ends up rendering the login page
 *   HTML instead of the raw PNG. eproosystem.php has a static
 *   fallback that uses ./themes/new/images/logo_cache.png directly
 *   if the file exists — so we simply make sure it always exists.
 *
 * This script is invoked from docker/entrypoint.sh on container
 * start. Failures are non-fatal — a missing logo is not worth
 * bringing the app down for.
 */

$host = getenv('WFDB_HOST') ?: 'mysql';
$port = (int)(getenv('WFDB_PORT') ?: 3306);
$name = getenv('WFDB_NAME');
$user = getenv('WFDB_USER');
$pass = getenv('WFDB_PASSWORD');
$out  = '/var/www/html/www/themes/new/images/logo_cache.png';

if (!$name || !$user || $pass === false) {
    fwrite(STDERR, "[logo] WFDB_* env vars missing, skipping logo export\n");
    exit(0);
}

// Retry a few times in case the DB health check raced ahead of the
// connection being truly accept()-ing — mariadb sometimes rejects
// the first connect briefly after the healthcheck goes green.
$mysqli = null;
for ($i = 0; $i < 5; $i++) {
    $mysqli = @new mysqli($host, $user, $pass, $name, $port);
    if ($mysqli && !$mysqli->connect_errno) {
        break;
    }
    $mysqli = null;
    sleep(1);
}
if (!$mysqli) {
    fwrite(STDERR, "[logo] DB connect failed, skipping logo export\n");
    exit(0);
}

$row = null;
if ($res = @$mysqli->query("SELECT firmenlogo, firmenlogoaktiv FROM firmendaten WHERE id=1 LIMIT 1")) {
    $row = $res->fetch_assoc();
    $res->free();
}
$mysqli->close();

if (!$row || empty($row['firmenlogo']) || $row['firmenlogoaktiv'] !== '1') {
    fwrite(STDERR, "[logo] no active firmenlogo in DB, skipping logo export\n");
    exit(0);
}

$bin = base64_decode($row['firmenlogo'], true);
if ($bin === false || strlen($bin) < 64) {
    fwrite(STDERR, "[logo] firmenlogo base64 decode failed or too small\n");
    exit(0);
}

$dir = dirname($out);
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
if (file_put_contents($out, $bin) === false) {
    fwrite(STDERR, "[logo] failed to write $out\n");
    exit(0);
}
@chmod($out, 0644);
fwrite(STDERR, "[logo] wrote " . strlen($bin) . " bytes to $out\n");
exit(0);

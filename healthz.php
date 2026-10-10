<?php
/**
 * Container health check (Docker HEALTHCHECK / Coolify): Apache + PHP are serving requests.
 * Returns plain "ok" and reveals nothing about the app.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo 'ok';

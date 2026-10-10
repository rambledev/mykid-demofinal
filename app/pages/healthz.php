<?php
/** Container health check (Docker HEALTHCHECK / Coolify): plain "ok", reveals nothing about the app. */
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo 'ok';

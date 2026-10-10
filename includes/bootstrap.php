<?php
/** Boots the shared Mykid core engine for the app (project root = app root). */
declare(strict_types=1);

define('MK_PKG_ROOT', realpath(__DIR__ . '/..'));
require_once __DIR__ . '/../core/bootstrap.php';

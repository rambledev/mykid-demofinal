<?php
/**
 * Mykid core engine.
 *
 * Booted by includes/bootstrap.php after defining MK_PKG_ROOT (the project root).
 * Data lives in MariaDB (core/db.php + core/store.php, schema in database/schema.sql).
 * App-specific parts live in includes/: config.php (features, roles), base-data.php
 * (shared source data: classrooms, students, statuses), mock-data.php (schools, people,
 * accounts) and permissions.php (role → scope → read/write).
 */
declare(strict_types=1);

defined('MK_PKG_ROOT') || exit('Direct access not allowed');

date_default_timezone_set('Asia/Bangkok');

define('MK_ROOT', realpath(__DIR__ . '/..'));
define('MK_CORE', __DIR__);
require_once MK_CORE . '/db.php'; // mk_env() reads env vars / .env
define('MYKID_ENV', mk_env('MYKID_ENV', 'development'));
define('MYKID_DEBUG', MYKID_ENV === 'development');

require_once MK_CORE . '/helpers.php';

/** App configuration (includes/config.php). */
function pkg(): array
{
    static $config = null;
    return $config ??= require MK_PKG_ROOT . '/includes/config.php';
}

function has_feature(string $feature): bool
{
    return in_array($feature, pkg()['modules'], true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(pkg()['session']);
    session_start();
}

require_once MK_PKG_ROOT . '/includes/base-data.php'; // shared source data (read-only)
require_once MK_CORE . '/catalog.php';
require_once MK_CORE . '/schema.php';
require_once MK_CORE . '/seed.php';
require_once MK_PKG_ROOT . '/includes/mock-data.php';
require_once MK_PKG_ROOT . '/includes/cctv-data.php';
require_once MK_CORE . '/store.php';
require_once MK_CORE . '/permissions.php';
require_once MK_CORE . '/cctv.php';
require_once MK_CORE . '/media.php';
require_once MK_CORE . '/notifications.php';
require_once MK_CORE . '/pickup.php';
require_once MK_CORE . '/auth.php';
require_once MK_CORE . '/data-filter.php';
require_once MK_CORE . '/components.php';
require_once MK_CORE . '/charts.php';
require_once MK_CORE . '/layout.php';

/** Render a core page as the given role (used by the small page files in each role folder). */
function mk_render(string $page, ?string $role = null): void
{
    if ($role !== null) {
        require_role($role);
    }
    $file = MK_CORE . '/pages/' . $page . '.php';
    mk_log('Core', 'mk_render', ['page' => $page, 'role' => $role]);
    require $file;
}

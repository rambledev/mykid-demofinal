<?php
/**
 * Mykid — application bootstrap (loaded by public/index.php and CLI scripts).
 *
 * Layout
 *   app/config/     app.php (features, roles, navigation), permissions.php, cctv-permissions.php
 *   app/lib/        engine: db, store, security, router, auth, permissions, components, ...
 *   app/pages/      one file per screen (rendered by the router, see app/routes.php)
 *   database/       schema.sql, install.php, seeds/ (demo source data)
 *   public/         web root — only index.php (front controller) and assets/ are served
 *   storage/        uploads/ (private image files, streamed by /media/{token})
 */
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

define('MK_ROOT', dirname(__DIR__));
define('MK_APP', __DIR__);
define('MK_LIB', __DIR__ . '/lib');
define('MK_PUBLIC', MK_ROOT . '/public');
define('MK_STORAGE', MK_ROOT . '/storage');

require_once MK_LIB . '/db.php'; // mk_env() reads env vars / .env
define('MYKID_ENV', mk_env('MYKID_ENV', 'production'));
define('MYKID_DEBUG', MYKID_ENV === 'development');

ini_set('display_errors', MYKID_DEBUG && PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once MK_LIB . '/helpers.php';
require_once MK_LIB . '/security.php';

/** App configuration (app/config/app.php). */
function pkg(): array
{
    static $config = null;
    return $config ??= require MK_APP . '/config/app.php';
}

function has_feature(string $feature): bool
{
    return in_array($feature, pkg()['modules'], true);
}

require_once MK_ROOT . '/database/seeds/base-data.php'; // shared source data (read-only)
require_once MK_LIB . '/catalog.php';
require_once MK_LIB . '/schema.php';
require_once MK_LIB . '/seed.php';
require_once MK_ROOT . '/database/seeds/mock-data.php';
require_once MK_ROOT . '/database/seeds/cctv-data.php';
require_once MK_LIB . '/store.php';
require_once MK_LIB . '/id-token.php';
require_once MK_LIB . '/permissions.php';
require_once MK_LIB . '/cctv.php';
require_once MK_LIB . '/media.php';
require_once MK_LIB . '/notifications.php';
require_once MK_LIB . '/pickup.php';
require_once MK_LIB . '/auth.php';
require_once MK_LIB . '/data-filter.php';
require_once MK_LIB . '/components.php';
require_once MK_LIB . '/charts.php';
require_once MK_LIB . '/layout.php';
require_once MK_LIB . '/router.php';

/** Render a page of app/pages as the given role (called by the router). */
function mk_render(string $page, ?string $role = null): void
{
    if ($role !== null) {
        require_role($role);
    }
    mk_log('Core', 'mk_render', ['page' => $page, 'role' => $role]);
    require MK_APP . '/pages/' . $page . '.php';
}

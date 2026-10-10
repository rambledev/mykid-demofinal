<?php
/**
 * Core layout — page header/footer, sidebar (Admin / Executive on desktop)
 * and bottom navigation (mobile, and Teacher / Parent everywhere).
 *
 * Stylesheets: assets/css/base.css is the base design language, core.css adds the
 * console layout / charts / extra roles, and assets/css/style.css adds tier accents.
 */
declare(strict_types=1);

/**
 * $page = ['id' => 'admin-home', 'title' => 'หน้าหลัก', 'nav' => 'home', 'layout' => 'app'|'console'|'auth'|'landing', 'theme' => optional]
 */
function mk_header(array $page): void
{
    $page += ['id' => '', 'title' => '', 'nav' => '', 'layout' => null, 'theme' => null];
    $user = current_user();
    $isApp = $user && !in_array($page['layout'], ['auth', 'landing'], true);
    $layout = $page['layout'] ?? ($user ? $user['meta']['layout'] : 'auth');
    $theme = $page['theme'] ?? ($user ? $user['meta']['theme'] : 'landing');
    $GLOBALS['mk_page'] = $page + ['resolvedLayout' => $layout, 'isApp' => $isApp];
    $themeColor = ['teacher' => '#DFF4FF', 'parent' => '#FFEAF2', 'admin' => '#FFF4CC', 'executive' => '#FFE1E1', 'super' => '#EFE7FF'][$theme] ?? '#FFF6E9';
    ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= e($themeColor) ?>">
    <meta name="robots" content="noindex">
    <title><?= e(($page['title'] ? $page['title'] . ' | ' : '') . pkg()['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/core.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
</head>
<body class="layout-<?= e($layout) ?> theme-<?= e($theme) ?> tier-<?= e(pkg()['tier']) ?>" data-page="<?= e($page['id']) ?>">
<div class="bg-decor" aria-hidden="true">
    <span class="bg-decor__cloud bg-decor__cloud--1"><?= decor_svg('cloud') ?></span>
    <span class="bg-decor__cloud bg-decor__cloud--2"><?= decor_svg('cloud') ?></span>
    <span class="bg-decor__star bg-decor__star--1"><?= decor_svg('star') ?></span>
    <span class="bg-decor__star bg-decor__star--2"><?= decor_svg('star') ?></span>
</div>
<?php
    if ($isApp) {
        if ($layout === 'console') {
            render_sidebar($user, $page['nav']);
        }
        render_appbar($user);
    }
    echo '<main class="page page--' . e($layout === 'console' ? 'console page--app' : ($isApp ? 'app' : $layout)) . '" id="main">';
}

function render_appbar(array $user): void
{
    $role = $user['meta'];
    ?>
    <header class="appbar">
        <a class="appbar__brand" href="<?= e(url(home_path($user['role']))) ?>">
            <span class="appbar__logo"><?= logo_svg() ?></span>
            <span class="appbar__text">
                <small><?= e(pkg()['name']) ?> · <?= e($role['th']) ?> <span class="tier-badge"><?= e(pkg()['tierLabel']) ?></span></small>
                <strong><?= e($user['school']['name'] ?? 'Mykid Platform · ทุกโรงเรียน') ?></strong>
            </span>
        </a>
        <?php if (has_notification_menu($user)): ?>
            <a class="appbar__bell" href="<?= e(url($role['dir'] . '/notifications')) ?>" aria-label="แจ้งเตือน"><span aria-hidden="true">🔔</span><?= notif_badge() ?></a>
        <?php endif; ?>
        <a class="appbar__user" href="<?= e(url($role['dir'] . '/account')) ?>" aria-label="บัญชีของฉัน"><span aria-hidden="true"><?= $user['emoji'] ?></span></a>

        <!-- Current user + permission scope (always from the session) -->
        <div class="appbar__scope">
            <span class="appbar__who">
                <span class="appbar__who-emoji" aria-hidden="true"><?= $user['emoji'] ?></span>
                <span class="appbar__who-text"><strong><?= e($user['name']) ?></strong><small><?= e($user['scopeLabel']) ?></small></span>
            </span>
            <span class="scope-lock" title="บัญชีนี้เข้าถึงได้<?= e($user['lockLabel']) ?>">🔒 <?= e($user['lockLabel']) ?></span>
            <form class="logout-form" method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit" class="appbar__logout" aria-label="ออกจากระบบ" title="ออกจากระบบ"><?= icon('logout') ?></button></form>
        </div>
    </header>
    <?php
}

/** Nav items for a role: [key, label, page, icon, mobile]. */
function nav_items(string $role): array
{
    return pkg()['nav'][$role] ?? [];
}

/**
 * Red unread badge for the "แจ้งเตือน" menu (in-app notifications). Always rendered (hidden at 0) so
 * core.js can update every badge on the page from the polling / mark-as-read responses.
 */
function notif_badge(): string
{
    static $count = null;
    $count ??= notifications_unread_count(current_user());
    $text = $count > 99 ? '99+' : (string) $count;
    return '<span class="notif-badge" data-notif-badge' . ($count ? '' : ' hidden') . ' aria-label="ยังไม่ได้อ่าน ' . $count . ' รายการ">' . $text . '</span>';
}

/** Does this role have the "แจ้งเตือน" menu? */
function has_notification_menu(array $user): bool
{
    return has_feature('inAppNotifications') && in_array('notifications', array_column(nav_items($user['role']), 0), true);
}

function render_sidebar(array $user, string $active): void
{
    ?>
    <aside class="sidebar" aria-label="เมนูหลัก">
        <a class="sidebar__brand" href="<?= e(url(home_path($user['role']))) ?>">
            <span class="appbar__logo"><?= logo_svg() ?></span>
            <span><strong>Mykid</strong><small><?= e(pkg()['tierLabel']) ?> · <?= e($user['meta']['th']) ?></small></span>
        </a>
        <nav class="sidebar__nav">
            <?php foreach (nav_items($user['role']) as [$key, $label, $href, $iconName]): ?>
                <a class="sidebar__item<?= $key === $active ? ' is-active' : '' ?>" href="<?= e(url($href)) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                    <?= icon($iconName) ?><span><?= e($label) ?></span><?= $key === 'notifications' && has_notification_menu($user) ? notif_badge() : '' ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <form class="logout-form" method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit" class="sidebar__item sidebar__logout"><?= icon('logout') ?><span>ออกจากระบบ</span></button></form>
    </aside>
    <?php
}

function render_bottom_nav(array $user, string $active): void
{
    $items = array_values(array_filter(nav_items($user['role']), fn ($i) => $i[4]));
    $hasMore = count($items) < count(nav_items($user['role']));
    if ($hasMore) {
        $items[] = ['menu', 'เมนู', $user['meta']['dir'] . '/menu', 'grid', true];
    }
    $mobileKeys = array_column($items, 0);
    // "แจ้งเตือน" inside the overflow menu → show its badge on "เมนู" instead
    $badgeKey = has_notification_menu($user) ? (in_array('notifications', $mobileKeys, true) ? 'notifications' : 'menu') : null;
    if ($hasMore && !in_array($active, $mobileKeys, true)) {
        $active = 'menu'; // pages reached through the menu highlight "เมนู"
    }
    ?>
    <nav class="bottom-nav" aria-label="เมนูหลัก" style="grid-template-columns: repeat(<?= count($items) ?>, 1fr)">
        <?php foreach ($items as [$key, $label, $href, $iconName]): ?>
            <a class="bottom-nav__item<?= $key === $active ? ' is-active' : '' ?>" href="<?= e(url($href)) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                <?= icon($iconName) ?><span><?= e($label) ?></span><?= $key === $badgeKey ? notif_badge() : '' ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}

function mk_footer(): void
{
    $page = $GLOBALS['mk_page'];
    $user = current_user();
    $config = [
        'debug'   => MYKID_DEBUG,
        'csrf'    => csrf_token(),
        'apiUrl'  => url('api'),
        'role'    => $user['role'] ?? null,
        'page'    => $page['id'],
        'version' => $page['isApp'] ? store_state()['version'] : null,
        'poll'    => $page['isApp'],
        'toast'   => flash('toast'),
    ];
    echo '</main>';
    if ($page['isApp']) {
        render_bottom_nav($user, $page['nav']);
        render_confirm_sheet();
        if (has_feature('cctv')) {
            render_cctv_viewer();
        }
        if (has_feature('media')) {
            render_media_sheets();
        }
    }
    ?>
<div class="chart-tip" data-chart-tip role="tooltip" hidden></div>
<div class="toast-stack" aria-live="polite" aria-atomic="true" data-toast-stack></div>
<script nonce="<?= e(csp_nonce()) ?>">window.MYKID = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="<?= e(asset_url('assets/js/core.js')) ?>"></script>
</body>
</html>
    <?php
}

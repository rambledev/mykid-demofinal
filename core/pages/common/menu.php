<?php
/** "เมนู" page — every feature of the role as a grid (mobile bottom nav shows only 4). */
declare(strict_types=1);

$user = current_user();
mk_header(['id' => $user['role'] . '-menu', 'title' => 'เมนูทั้งหมด', 'nav' => 'menu']);

page_title('🧩', 'เมนูทั้งหมด', $user['scopeLabel']);
?>
<nav class="menu-grid" aria-label="เมนูทั้งหมด">
    <?php foreach (nav_items($user['role']) as [$key, $label, $href, $iconName]): ?>
        <a class="menu-grid__item" href="<?= e(url($href)) ?>">
            <span class="menu-grid__icon"><?= icon($iconName) ?></span>
            <span><?= e($label) ?></span><?= $key === 'notifications' && has_notification_menu($user) ? notif_badge() : '' ?>
        </a>
    <?php endforeach; ?>
</nav>
<?php mk_footer(); ?>

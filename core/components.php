<?php
/**
 * Core UI components for Package A & B.
 *
 * Markup deliberately reuses Package C's class names (status-card, timeline, meal, sheet,
 * bottom-nav, ...) so Package C's stylesheet gives every package the same design language;
 * core/assets/css/core.css only adds what C does not have (console layout, tables, charts,
 * chat, CCTV, new role themes).
 *
 * Components render only the rows handed to them — data always comes from data-filter.php.
 */
declare(strict_types=1);

/* ============================================================================
 * Icons & artwork (inline SVG — no external images)
 * ========================================================================= */

function icon(string $name, string $class = ''): string
{
    static $paths = [
        'home'     => '<path d="M3 10.5 12 3l9 7.5V20a1.5 1.5 0 0 1-1.5 1.5H15v-6H9v6H4.5A1.5 1.5 0 0 1 3 20z"/>',
        'calendar' => '<rect x="3" y="4.5" width="18" height="17" rx="3"/><path d="M8 2.5v4M16 2.5v4M3 10h18"/>',
        'list'     => '<path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4.5" cy="6" r="1.5"/><circle cx="4.5" cy="12" r="1.5"/><circle cx="4.5" cy="18" r="1.5"/>',
        'food'     => '<path d="M3 12h18a9 9 0 0 1-18 0z"/><path d="M8 8c0-1.5 1-2 1-3.5M12 8c0-1.5 1-2 1-3.5M16 8c0-1.5 1-2 1-3.5"/>',
        'status'   => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5" fill="currentColor"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
        'users'    => '<circle cx="9" cy="8" r="4"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1"/><path d="M16 4.1a4 4 0 0 1 0 7.8M19 14.5a6 6 0 0 1 3 5.5v1"/>',
        'child'    => '<circle cx="12" cy="12" r="9"/><path d="M8.5 14.5s1.3 1.8 3.5 1.8 3.5-1.8 3.5-1.8"/><path d="M9 10h.01M15 10h.01" stroke-width="3"/>',
        'school'   => '<path d="M3 21h18M5 21V10l7-5 7 5v11"/><path d="M10 21v-5h4v5M12 5V2l3 1.5-3 1.5"/>',
        'chart'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'report'   => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
        'chat'     => '<path d="M21 12a8 8 0 0 1-11.6 7.2L4 21l1.8-5.2A8 8 0 1 1 21 12z"/>',
        'bell'     => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'star'     => '<path d="m12 2.5 2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4l-5.9 3.1 1.2-6.5-4.8-4.6 6.6-.9z"/>',
        'heart'    => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21.2l8.8-8.8a5.5 5.5 0 0 0 0-7.8z"/>',
        'moon'     => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'camera'   => '<path d="M15.5 10 21 7v10l-5.5-3"/><rect x="3" y="6" width="12.5" height="12" rx="2.5"/>',
        'image'    => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.8"/><path d="m21 15-5-5L5 21"/>',
        'sprout'   => '<path d="M12 21v-9"/><path d="M12 12C12 7 8 4 3 4c0 5 4 8 9 8zM12 12c0-4 3-7 8-7 0 4-3 7-8 7z"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'car'      => '<path d="M5 17h14M5 17a2 2 0 1 0 4 0M15 17a2 2 0 1 0 4 0M3 17v-5l2-5h14l2 5v5"/><path d="M3 12h18"/>',
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash'    => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'back'     => '<path d="M15 18l-6-6 6-6"/>',
        'next'     => '<path d="M9 18l6-6-6-6"/>',
        'refresh'  => '<path d="M23 4v6h-6"/><path d="M20.5 15a9 9 0 1 1-2.1-9.4L23 10"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'phone'    => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>',
        'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'close'    => '<path d="M18 6 6 18M6 6l12 12"/>',
        'send'     => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
        'print'    => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
    ];
    return '<svg class="' . e(trim('icon ' . $class)) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

function logo_svg(): string
{
    return '<svg viewBox="0 0 48 48" aria-hidden="true"><rect width="48" height="48" rx="14" fill="#FFE9A8"/>'
        . '<g stroke="#FFB547" stroke-width="2.5" stroke-linecap="round"><line x1="24" y1="5" x2="24" y2="9"/><line x1="24" y1="39" x2="24" y2="43"/><line x1="5" y1="24" x2="9" y2="24"/><line x1="39" y1="24" x2="43" y2="24"/><line x1="10.6" y1="10.6" x2="13.4" y2="13.4"/><line x1="34.6" y1="34.6" x2="37.4" y2="37.4"/><line x1="10.6" y1="37.4" x2="13.4" y2="34.6"/><line x1="34.6" y1="13.4" x2="37.4" y2="10.6"/></g>'
        . '<circle cx="24" cy="24" r="10.5" fill="#FFC94D"/><circle cx="20.5" cy="23" r="1.5" fill="#5A3A26"/><circle cx="27.5" cy="23" r="1.5" fill="#5A3A26"/>'
        . '<path d="M20.5 27q3.5 3 7 0" stroke="#5A3A26" stroke-width="1.6" fill="none" stroke-linecap="round"/>'
        . '<circle cx="18" cy="26.5" r="1.5" fill="#FF8FB1" opacity=".6"/><circle cx="30" cy="26.5" r="1.5" fill="#FF8FB1" opacity=".6"/></svg>';
}

function decor_svg(string $name): string
{
    return match ($name) {
        'cloud'   => '<svg viewBox="0 0 112 68" aria-hidden="true"><path fill="currentColor" d="M28 64C14 64 4 55 4 43s10-21 23-21c3-12 14-20 27-20 15 0 27 10 29 24h3c12 0 22 9 22 20s-10 18-22 18z"/></svg>',
        'star'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 1.5l3.1 6.6 7.2.9-5.3 5 1.4 7.1L12 17.6l-6.4 3.5L7 14 1.7 9l7.2-.9z"/></svg>',
        'sun'     => '<svg viewBox="0 0 48 48" aria-hidden="true"><g stroke="#FFC94D" stroke-width="3" stroke-linecap="round"><path d="M24 3v5M24 40v5M3 24h5M40 24h5M9 9l3.5 3.5M35.5 35.5 39 39M9 39l3.5-3.5M35.5 12.5 39 9"/></g><circle cx="24" cy="24" r="10" fill="#FFD66B"/></svg>',
        'rainbow' => '<svg viewBox="0 0 120 64" aria-hidden="true"><g fill="none" stroke-width="8" stroke-linecap="round"><path d="M12 60a48 48 0 0 1 96 0" stroke="#FFC4C4"/><path d="M22 60a38 38 0 0 1 76 0" stroke="#FFE9A8"/><path d="M32 60a28 28 0 0 1 56 0" stroke="#D4F5DF"/><path d="M42 60a18 18 0 0 1 36 0" stroke="#BFE7FF"/></g></svg>',
        'flower'  => '<svg viewBox="0 0 40 40" aria-hidden="true"><g fill="#FFB3CB"><circle cx="20" cy="9" r="7"/><circle cx="30.5" cy="16.6" r="7"/><circle cx="26.5" cy="29" r="7"/><circle cx="13.5" cy="29" r="7"/><circle cx="9.5" cy="16.6" r="7"/></g><circle cx="20" cy="20" r="6" fill="#FFD66B"/></svg>',
        default   => '',
    };
}

/** Cartoon child avatar (same drawing as Package C). */
function avatar_svg(array $a): string
{
    $skin = e($a['skin']);
    $hair = e($a['hair']);
    $back = $front = $accessory = '';
    switch ($a['style']) {
        case 'pigtails':
            $back = "<circle cx=\"13\" cy=\"36\" r=\"7\" fill=\"$hair\"/><circle cx=\"51\" cy=\"36\" r=\"7\" fill=\"$hair\"/><circle cx=\"32\" cy=\"34\" r=\"17\" fill=\"$hair\"/>";
            $front = "<path d=\"M17 35Q17 19 32 19Q47 19 47 35Q41 27 32 27Q23 27 17 35Z\" fill=\"$hair\"/>";
            $accessory = '<path d="M43 20l-6-4v8zM43 20l6-4v8z" fill="#FF7AA8"/><circle cx="43" cy="20" r="2" fill="#FF5C93"/>';
            break;
        case 'bob':
            $back = "<path d=\"M14 37Q14 16 32 16Q50 16 50 37L50 49Q32 53 14 49Z\" fill=\"$hair\"/>";
            $front = "<path d=\"M17 34Q18 20 32 20Q46 20 47 34Q43 27 37 28Q34 24 30 27Q23 27 17 34Z\" fill=\"$hair\"/>";
            $accessory = '<path d="M22 22.5l1.2 2.4 2.6.4-1.9 1.8.5 2.6-2.4-1.3-2.3 1.3.4-2.6-1.9-1.8 2.6-.4z" fill="#FFD36E"/>';
            break;
        case 'spiky':
            $front = "<path d=\"M16 35Q15 22 22 19L23 13L28 17L32 11L36 17L41 13L42 19Q49 22 48 35Q42 27 32 28Q22 27 16 35Z\" fill=\"$hair\"/>";
            break;
        default:
            $back = "<circle cx=\"32\" cy=\"34\" r=\"16\" fill=\"$hair\"/>";
            $front = "<path d=\"M16 36Q15 19 32 18Q49 19 48 36Q46 28 40 26Q33 30 24 27Q19 29 16 36Z\" fill=\"$hair\"/>";
    }
    return '<svg viewBox="0 0 64 64" aria-hidden="true"><rect width="64" height="64" fill="' . e($a['bg']) . '"/>' . $back
        . '<path d="M11 64Q13 51 32 50Q51 51 53 64Z" fill="' . e($a['shirt']) . '"/>'
        . "<circle cx=\"17\" cy=\"38\" r=\"3.2\" fill=\"$skin\"/><circle cx=\"47\" cy=\"38\" r=\"3.2\" fill=\"$skin\"/><circle cx=\"32\" cy=\"37\" r=\"15\" fill=\"$skin\"/>"
        . $front
        . '<ellipse cx="26.5" cy="38" rx="1.8" ry="2.2" fill="#3B2A20"/><ellipse cx="37.5" cy="38" rx="1.8" ry="2.2" fill="#3B2A20"/>'
        . '<circle cx="27.1" cy="37.2" r=".6" fill="#fff"/><circle cx="38.1" cy="37.2" r=".6" fill="#fff"/>'
        . '<circle cx="22.5" cy="42.5" r="2.4" fill="#FF8FB1" opacity=".45"/><circle cx="41.5" cy="42.5" r="2.4" fill="#FF8FB1" opacity=".45"/>'
        . '<path d="M28.5 43.5Q32 47 35.5 43.5" stroke="#3B2A20" stroke-width="1.6" fill="none" stroke-linecap="round"/>'
        . $accessory . '</svg>';
}

function student_avatar(array $student, string $size = 'md'): string
{
    return '<span class="avatar avatar--' . e($size) . '" role="img" aria-label="' . e($student['nickname']) . '">' . avatar_svg($student['avatar']) . '</span>';
}

/** Placeholder "child artwork" / activity photo — 6 cartoon scenes drawn in SVG. */
function art_svg(int $variant, string $label = ''): string
{
    $scenes = [
        ['#DFF4FF', '<circle cx="78" cy="22" r="10" fill="#FFD66B"/><path d="M0 62q25-14 50 0t50 0V80H0z" fill="#8FD9A8"/><path d="M30 62V44l12-10 12 10v18z" fill="#FF9EC0"/><rect x="38" y="50" width="8" height="12" fill="#fff"/>'],
        ['#FFF4CC', '<g fill="none" stroke-width="6" stroke-linecap="round"><path d="M14 66a36 36 0 0 1 72 0" stroke="#FFC4C4"/><path d="M24 66a26 26 0 0 1 52 0" stroke="#BFE7FF"/><path d="M34 66a16 16 0 0 1 32 0" stroke="#D4F5DF"/></g><circle cx="20" cy="20" r="7" fill="#fff"/><circle cx="28" cy="18" r="9" fill="#fff"/>'],
        ['#FFEAF2', '<g fill="#FF9EC0"><circle cx="50" cy="30" r="9"/><circle cx="62" cy="40" r="9"/><circle cx="56" cy="54" r="9"/><circle cx="44" cy="54" r="9"/><circle cx="38" cy="40" r="9"/></g><circle cx="50" cy="44" r="7" fill="#FFD66B"/><path d="M50 60v18" stroke="#6CC08A" stroke-width="4"/>'],
        ['#E6F7EE', '<ellipse cx="48" cy="48" rx="24" ry="14" fill="#7CC6F2"/><path d="M72 48l14-10v20z" fill="#7CC6F2"/><circle cx="38" cy="44" r="3" fill="#2F3A56"/><circle cx="18" cy="20" r="4" fill="#BFE7FF"/><circle cx="26" cy="12" r="3" fill="#BFE7FF"/>'],
        ['#F1EAFF', '<path d="M50 14l9 18 20 3-14 14 3 20-18-9-18 9 3-20-14-14 20-3z" fill="#FFD66B"/><circle cx="20" cy="66" r="4" fill="#B79CFF"/><circle cx="84" cy="16" r="3" fill="#B79CFF"/>'],
        ['#FFE9D6', '<rect x="22" y="40" width="56" height="26" rx="6" fill="#FF9F7A"/><circle cx="34" cy="68" r="7" fill="#2F3A56"/><circle cx="66" cy="68" r="7" fill="#2F3A56"/><rect x="30" y="28" width="30" height="16" rx="4" fill="#BFE7FF"/>'],
    ];
    [$bg, $shapes] = $scenes[abs($variant) % 6];
    return '<svg class="art" viewBox="0 0 100 80" role="img" aria-label="' . e($label) . '" preserveAspectRatio="xMidYMid slice"><rect width="100" height="80" fill="' . $bg . '"/>' . $shapes . '</svg>';
}

/* ============================================================================
 * Small building blocks
 * ========================================================================= */

function page_title(string $emoji, string $title, string $subtitle = '', string $actions = ''): void
{
    ?>
    <header class="page-title">
        <span class="page-title__emoji" aria-hidden="true"><?= $emoji ?></span>
        <div class="page-title__text">
            <h1><?= e($title) ?></h1>
            <?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif; ?>
        </div>
        <?php if ($actions !== ''): ?><div class="page-title__actions"><?= $actions ?></div><?php endif; ?>
    </header>
    <?php
}

function section_head(string $emoji, string $title, string $actions = '', string $subtitle = ''): void
{
    ?>
    <div class="section-head">
        <div>
            <h2 class="section-head__title"><span class="section-head__emoji" aria-hidden="true"><?= $emoji ?></span><?= e($title) ?></h2>
            <?php if ($subtitle !== ''): ?><p class="section-head__sub"><?= e($subtitle) ?></p><?php endif; ?>
        </div>
        <?php if ($actions !== ''): ?><div class="section-head__actions"><?= $actions ?></div><?php endif; ?>
    </div>
    <?php
}

function stat_card(string $emoji, string $label, string|int $value, string $sub = '', string $tone = ''): string
{
    return '<div class="stat-card' . ($tone ? ' stat-card--' . e($tone) : '') . '">'
        . '<span class="stat-card__emoji" aria-hidden="true">' . $emoji . '</span>'
        . '<span class="stat-card__label">' . e($label) . '</span>'
        . '<strong class="stat-card__value">' . e((string) $value) . '</strong>'
        . ($sub !== '' ? '<span class="stat-card__sub">' . e($sub) . '</span>' : '')
        . '</div>';
}

/** Status pill: icon + label (never colour alone). */
function tone_badge(?array $item, string $fallback = '-'): string
{
    if (!$item) {
        return '<span class="tone tone--neutral">' . e($fallback) . '</span>';
    }
    return '<span class="tone tone--' . e($item['tone'] ?? 'neutral') . '">' . ($item['emoji'] ?? '') . ' ' . e($item['label']) . '</span>';
}

function empty_state(string $emoji, string $text): string
{
    return '<div class="empty empty--box"><span>' . $emoji . '</span><p>' . e($text) . '</p></div>';
}

function btn_add(string $table, string $label, array $defaults = []): string
{
    return '<button type="button" class="btn btn--soft btn--sm" data-action="open-form" data-table="' . e($table) . '"'
        . ($defaults ? ' data-defaults="' . json_attr($defaults) . '"' : '') . '>' . icon('plus') . ' ' . e($label) . '</button>';
}

function btn_edit(string $table, array $row): string
{
    return '<button type="button" class="icon-btn" data-action="open-form" data-table="' . e($table) . '" data-record="' . json_attr($row) . '" aria-label="แก้ไข">' . icon('edit') . '</button>';
}

function btn_delete(string $table, int $id, string $label): string
{
    return '<button type="button" class="icon-btn icon-btn--danger" data-action="delete" data-table="' . e($table) . '" data-id="' . $id . '" data-label="' . e($label) . '" aria-label="ลบ ' . e($label) . '">' . icon('trash') . '</button>';
}

/** Search box + chips; JS filters [data-filter-item] elements client-side. */
function filter_bar(string $target, array $chips = [], string $placeholder = 'ค้นหา...', string $chipKey = 'group', string $initial = ''): void
{
    ?>
    <div class="filter-bar" data-filter-bar="<?= e($target) ?>" data-chip-key="<?= e($chipKey) ?>" data-initial-chip="<?= e($initial) ?>">
        <label class="filter-bar__search">
            <?= icon('search') ?>
            <input class="input" type="search" placeholder="<?= e($placeholder) ?>" data-filter-search aria-label="ค้นหา">
        </label>
        <?php if ($chips): ?>
            <div class="chip-row" role="group" aria-label="ตัวกรอง">
                <button type="button" class="chip-btn is-active" data-filter-chip="">ทั้งหมด</button>
                <?php foreach ($chips as $value => $label): ?>
                    <button type="button" class="chip-btn" data-filter-chip="<?= e((string) $value) ?>"><?= e($label) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <p class="filter-bar__count" data-filter-count aria-live="polite"></p>
    </div>
    <?php
}

/** Client-side tabs (e.g. one panel per classroom). */
function tab_bar(string $group, array $tabs): void
{
    echo '<div class="chip-row tab-bar" role="tablist" data-tabs="' . e($group) . '">';
    $first = true;
    foreach ($tabs as $key => $label) {
        echo '<button type="button" role="tab" class="chip-btn' . ($first ? ' is-active' : '') . '" data-tab="' . e((string) $key) . '" aria-selected="' . ($first ? 'true' : 'false') . '">' . e($label) . '</button>';
        $first = false;
    }
    echo '</div>';
}

function classroom_label(array $room): string
{
    $label = $room['emoji'] . ' ' . $room['name'];
    if (pkg()['multiSchool'] && current_user()['role'] === 'super_admin') {
        $label .= ' · ' . (find_row('schools', $room['school_id'])['shortName'] ?? '');
    }
    return $label;
}

/* ============================================================================
 * Status / activities / food (Package C markup)
 * ========================================================================= */

function render_status_card(?array $status, array $room, string $lead, bool $canChange): void
{
    ?>
    <div class="status-card" style="--status-bg: <?= e($status['color'] ?? '#F4F6FB') ?>">
        <span class="status-card__decor status-card__decor--cloud"><?= decor_svg('cloud') ?></span>
        <span class="status-card__decor status-card__decor--star"><?= decor_svg('star') ?></span>
        <p class="status-card__room"><span class="live-dot" aria-hidden="true"></span>สถานะห้อง <?= e($room['name']) ?></p>
        <p class="status-card__lead"><?= e($lead) ?></p>
        <?php if ($status): ?>
            <div class="status-card__main">
                <span class="status-card__emoji" aria-hidden="true"><?= $status['emoji'] ?></span>
                <strong class="status-card__label"><?= e($status['text']) ?></strong>
            </div>
            <p class="status-card__time"><?= icon('clock') ?> อัปเดตล่าสุด <?= e(thai_time($status['at'])) ?> · โดย <?= e($status['byName']) ?></p>
        <?php else: ?>
            <p class="status-card__empty">ยังไม่มีการอัปเดตสถานะวันนี้</p>
        <?php endif; ?>
        <?php if ($canChange): ?>
            <button type="button" class="btn btn--primary btn--lg btn--block" data-action="open-sheet" data-sheet="sheet-status"><?= icon('refresh') ?> เปลี่ยนสถานะ</button>
        <?php endif; ?>
    </div>
    <?php
}

function render_status_picker(?array $current): void
{
    ?>
    <div class="status-controls" data-status-scope>
        <label class="field">
            <span class="field__label">รายละเอียดเพิ่มเติม <small>(ไม่บังคับ)</small></span>
            <input class="input" type="text" maxlength="40" data-status-note placeholder="เช่น ศิลปะ → แสดงเป็น “ทำกิจกรรมศิลปะ”">
        </label>
        <div class="status-grid">
            <?php foreach (mk_catalog()['statuses'] as $s): ?>
                <?php $on = $current && $current['code'] === $s['code']; ?>
                <button type="button" class="status-btn<?= $on ? ' is-current' : '' ?>" style="--status-bg: <?= e($s['color']) ?>" data-action="set-status" data-code="<?= e($s['code']) ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>">
                    <span class="status-btn__emoji" aria-hidden="true"><?= $s['emoji'] ?></span>
                    <span class="status-btn__label"><?= e($s['label']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function render_status_history(array $history): void
{
    if (!$history) {
        echo '<p class="empty">ยังไม่มีการอัปเดตสถานะวันนี้</p>';
        return;
    }
    echo '<ol class="history">';
    foreach ($history as $i => $h) {
        echo '<li class="history__item' . ($i === 0 ? ' is-current' : '') . '"><span class="history__emoji" style="--status-bg: ' . e($h['color']) . '" aria-hidden="true">' . $h['emoji'] . '</span>'
            . '<span class="history__text">' . e($h['text']) . ($i === 0 ? ' <span class="tag">ล่าสุด</span>' : '') . '</span>'
            . '<span class="history__time">' . e(date('H:i', strtotime($h['at']))) . ' น.</span></li>';
    }
    echo '</ol>';
}

function render_activities(array $activities, bool $manage, bool $compact = false): void
{
    if (!$activities) {
        echo empty_state('📋', $manage ? 'ยังไม่มีกิจกรรมวันนี้ กด “เพิ่ม” เพื่อเริ่มต้น' : 'คุณครูยังไม่ได้เพิ่มกิจกรรมของวันนี้');
        return;
    }
    $withMedia = has_feature('media');
    $canUpload = $withMedia && $manage && can_write_table(current_user(), 'mediaFiles');
    $mediaOf = fn (array $a) => $withMedia ? capture(fn () => render_media_strip(media_for('activity', $a['id']), $a['title'])) : '';
    if ($manage) {
        echo '<ol class="activity-list">';
        foreach ($activities as $a) {
            echo '<li class="activity"><span class="activity__time">' . e($a['time']) . '</span><span class="activity__icon" aria-hidden="true">' . $a['icon'] . '</span>'
                . '<div class="activity__body"><strong>' . e($a['title']) . '</strong>' . (($a['detail'] ?? '') !== '' ? '<p>' . e($a['detail']) . '</p>' : '') . $mediaOf($a) . '</div>'
                . '<div class="activity__actions">' . ($canUpload ? btn_upload('activity', $a['id'], $a['time'] . ' ' . $a['title']) : '') . btn_edit('activities', $a) . btn_delete('activities', $a['id'], $a['time'] . ' ' . $a['title']) . '</div></li>';
        }
        echo '</ol>';
        return;
    }
    echo '<ol class="timeline' . ($compact ? ' timeline--compact' : '') . '">';
    foreach ($activities as $a) {
        echo '<li class="timeline__item"><span class="timeline__time">' . e($a['time']) . '</span><span class="timeline__dot" aria-hidden="true">' . $a['icon'] . '</span>'
            . '<div class="timeline__body"><strong>' . e($a['title']) . '</strong>' . (!$compact && ($a['detail'] ?? '') !== '' ? '<p>' . e($a['detail']) . '</p>' : '') . ($compact ? '' : $mediaOf($a)) . '</div></li>';
    }
    echo '</ol>';
}

function render_food_summary(array $meals, ?array $intake = null): void
{
    echo '<ul class="food-summary">';
    foreach ($meals as $m) {
        $level = $intake ? cat_find('intake', $intake['levels'][$m['meal']] ?? null) : null;
        echo '<li style="--meal-bg: ' . e($m['color']) . '"><span class="food-summary__icon" aria-hidden="true">' . $m['icon'] . '</span>'
            . '<div><small>' . e($m['label']) . '</small><strong>' . e($m['items'] ? implode(' + ', $m['items']) : 'ยังไม่ได้กำหนดเมนู') . '</strong></div>'
            . ($level ? tone_badge($level) : '') . '</li>';
    }
    echo '</ul>';
}

/** Meal cards; $classroomId set → editable (save_meal), null → read only. */
function render_food_cards(array $meals, ?int $classroomId, ?array $intake = null, ?array $menuRow = null): void
{
    $withPhotos = has_feature('media') && $menuRow !== null;
    $canAttach = $classroomId && has_feature('media') && can_write_table(current_user(), 'mediaFiles');
    $photosByMeal = $withPhotos ? group_by(array_values(array_filter(media_for('food', $menuRow['id']), fn ($p) => !empty($p['meal']))), 'meal') : [];
    echo '<div class="meal-grid">';
    foreach ($meals as $m) {
        $level = $intake ? cat_find('intake', $intake['levels'][$m['meal']] ?? null) : null;
        ?>
        <article class="meal<?= $classroomId ? '' : ' meal--view' ?>" style="--meal-bg: <?= e($m['color']) ?>" data-meal="<?= e($m['meal']) ?>" data-classroom="<?= (int) $classroomId ?>">
            <header class="meal__head">
                <span class="meal__icon" aria-hidden="true"><?= $m['icon'] ?></span>
                <div class="meal__title"><h3><?= e($m['label']) ?></h3><small><?= icon('clock') ?> <?= e($m['time']) ?> น.</small></div>
                <?php if ($classroomId): ?>
                    <button type="button" class="btn btn--soft btn--sm meal__edit" data-action="edit-meal"><?= icon('edit') ?> แก้ไข</button>
                <?php endif; ?>
            </header>
            <ul class="meal__items">
                <?php foreach ($m['items'] ?: ['ยังไม่ได้กำหนดเมนู'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
            </ul>
            <?php if (!empty($photosByMeal[$m['meal']])): ?>
                <div class="meal__photos"><?php render_media_strip($photosByMeal[$m['meal']], $m['label']); ?></div>
            <?php endif; ?>
            <?php if ($level): ?><p class="meal__intake">วันนี้: <?= tone_badge($level) ?></p><?php endif; ?>
            <?php if ($classroomId): ?>
                <form class="meal__form" data-meal-form hidden>
                    <label class="field"><span class="field__label">รายการอาหาร <small>(1 บรรทัด ต่อ 1 รายการ)</small></span>
                        <textarea class="input" name="items" rows="3" required><?= e(implode("\n", $m['items'])) ?></textarea></label>
                    <?php if ($canAttach): ?>
                        <div class="field"><span class="field__label">รูป<?= e($m['label']) ?> <small>(ไม่บังคับ — ถ่ายภาพหรือเลือกรูปได้)</small></span><?= image_picker() ?></div>
                    <?php endif; ?>
                    <div class="row-actions">
                        <button type="button" class="btn btn--ghost" data-action="cancel-meal">ยกเลิก</button>
                        <button type="submit" class="btn btn--primary"><?= icon('check') ?> บันทึก</button>
                    </div>
                </form>
            <?php endif; ?>
        </article>
        <?php
    }
    echo '</div>';
}

/* ============================================================================
 * People
 * ========================================================================= */

/**
 * Student grid. With $statuses (today's latest per child) the child's status is shown under the name;
 * with $editable each child is a button that opens the "สถานะรายบุคคล" sheet (teacher home).
 */
function render_student_grid(array $students, array $statuses = [], bool $editable = false): void
{
    echo '<ul class="student-grid">';
    foreach ($students as $s) {
        $st = $statuses[$s['id']] ?? null;
        $badge = $st ? '<span class="student__status" title="' . e($st['note'] ?? '') . '">📝 ' . e($st['status']) . '</span>' : '';
        $inner = student_avatar($s) . '<span class="student__name">' . e($s['nickname']) . '</span>' . $badge;
        if ($editable) {
            echo '<li><button type="button" class="student student--btn' . ($st ? ' has-status' : '') . '" data-action="student-status-open"'
                . ' data-student="' . $s['id'] . '" data-name="' . e($s['nickname']) . '"'
                . ' data-status="' . e($st['status'] ?? '') . '" data-note="' . e($st['note'] ?? '') . '"'
                . ' data-since="' . e($st ? substr($st['at'], 11, 5) : '') . '"'
                . ' aria-label="กำหนดสถานะของ' . e($s['nickname']) . '">' . $inner . '</button></li>';
        } else {
            echo '<li class="student" title="' . e($s['name']) . '">' . $inner . '</li>';
        }
    }
    echo '</ul>';
}

/** Sheet: teacher types an individual status + note for one child (e.g. เด็กป่วย + symptoms). */
function render_student_status_sheet(): void
{
    render_sheet_open('sheet-student-status', 'สถานะรายบุคคล', 'ผู้ปกครองของเด็กคนนี้จะเห็นสถานะและหมายเหตุ');
    ?>
    <form class="form" data-student-status-form novalidate>
        <input type="hidden" name="student_id" value="">
        <p class="hint" data-student-status-current hidden></p>
        <label class="field"><span class="field__label">สถานะ <small>(พิมพ์เองได้)</small></span>
            <input class="input" type="text" name="status" maxlength="60" required list="student-status-suggestions" placeholder="เช่น เด็กป่วย" autocomplete="off"></label>
        <datalist id="student-status-suggestions">
            <?php foreach (['เด็กป่วย', 'มีไข้', 'ไม่สบายท้อง', 'ได้รับบาดเจ็บเล็กน้อย', 'งอแง / ร้องไห้', 'กลับบ้านก่อนเวลา', 'หายดีแล้ว'] as $suggestion): ?>
                <option value="<?= e($suggestion) ?>">
            <?php endforeach; ?>
        </datalist>
        <label class="field"><span class="field__label">หมายเหตุ <small>(ไม่บังคับ)</small></span>
            <textarea class="input" name="note" rows="3" maxlength="200" placeholder="เช่น ตัวร้อน 37.8°C ให้ยาลดไข้แล้ว แจ้งผู้ปกครองมารับ"></textarea></label>
        <p class="form__error" data-form-error hidden></p>
        <div class="row-actions">
            <button type="button" class="btn btn--ghost" data-action="close-sheet">ยกเลิก</button>
            <button type="submit" class="btn btn--primary"><?= icon('check') ?> บันทึกสถานะ</button>
        </div>
    </form>
    <?php
    render_sheet_close();
}

/** Parent view of today's individual status from the teacher (nothing when there is none). */
function render_student_status_card(?array $status, string $nickname): void
{
    if (!$status) {
        return;
    }
    ?>
    <section class="card student-status-card" role="status">
        <?php section_head('📝', 'สถานะของ' . $nickname . 'จากคุณครู'); ?>
        <p class="student-status-card__status"><?= e($status['status']) ?></p>
        <?php if (($status['note'] ?? '') !== ''): ?><p class="student-status-card__note"><?= nl2br(e($status['note'])) ?></p><?php endif; ?>
        <p class="hint">🕘 <?= e(substr($status['at'], 11, 5)) ?> น. · <?= e($status['by'] ?? 'คุณครู') ?></p>
    </section>
    <?php
}

function render_child_card(array $child, array $room, ?array $school, bool $detailed = false): void
{
    ?>
    <div class="child-card">
        <?= student_avatar($child, 'xl') ?>
        <div class="child-card__info">
            <h3 class="child-card__name"><?= e($child['nickname']) ?></h3>
            <?php if ($detailed): ?><p class="child-card__full"><?= e($child['name']) ?></p><?php endif; ?>
            <p class="child-card__meta"><span class="chip"><?= $room['emoji'] ?> <?= e($room['name']) ?></span></p>
            <p class="child-card__school">🏫 <?= e($school['name'] ?? '') ?></p>
        </div>
    </div>
    <?php
}

/** Teacher's own classroom (scope card, same as Package C). */
function render_scope_card(array $room, int $students, array $teachers): void
{
    ?>
    <section class="scope-card" aria-label="ห้องเรียนของฉัน" style="--room-bg: <?= e($room['color']) ?>">
        <span class="scope-card__emoji" aria-hidden="true"><?= $room['emoji'] ?></span>
        <div class="scope-card__info">
            <p class="scope-card__label">ห้องเรียนของฉัน</p>
            <h2 class="scope-card__room">ห้อง<?= e($room['name']) ?></h2>
            <p class="scope-card__meta">🧒 นักเรียน <?= $students ?> คน · 👩‍🏫 <?= e(implode(', ', array_column($teachers, 'nickname'))) ?></p>
        </div>
        <span class="scope-lock">🔒 เฉพาะห้องนี้</span>
    </section>
    <?php
}

/* ============================================================================
 * Generic form sheet (driven by core/schema.php)
 * ========================================================================= */

function field_options(array $field): array
{
    return match ($field['type']) {
        'classroom' => array_column(array_map(fn ($r) => ['id' => $r['id'], 'label' => classroom_label($r)], my_classrooms()), 'label', 'id'),
        'school'    => array_column(scoped('schools'), 'name', 'id'),
        'student'   => (function () {
            $options = [];
            foreach (scoped('students') as $s) {
                $room = find_row('classrooms', $s['classroom_id']);
                $options[$s['id']] = $s['nickname'] . ' · ' . ($room['name'] ?? '');
            }
            return $options;
        })(),
        default => is_string($field['options'] ?? null) ? cat_options($field['options']) : ($field['options'] ?? []),
    };
}

function render_field(string $key, array $field): void
{
    $required = !empty($field['required']);
    $label = '<span class="field__label">' . e($field['label']) . ($required ? '' : ' <small>(ไม่บังคับ)</small>') . '</span>';
    $attrs = 'name="' . e($key) . '"' . ($required ? ' required' : '');

    if ($field['type'] === 'emoji') {
        echo '<fieldset class="field icon-picker"><legend class="field__label">' . e($field['label']) . '</legend><div class="icon-picker__grid">';
        foreach (mk_catalog()[$field['options']] as $i => $emoji) {
            echo '<label class="icon-picker__item"><input type="radio" name="' . e($key) . '" value="' . e($emoji) . '"' . ($i === 0 ? ' checked' : '') . '><span>' . $emoji . '</span></label>';
        }
        echo '</div></fieldset>';
        return;
    }

    echo '<label class="field">' . $label;
    switch ($field['type']) {
        case 'textarea':
            echo '<textarea class="input" rows="2" ' . $attrs . ' maxlength="' . (int) ($field['max'] ?? 200) . '" placeholder="' . e($field['placeholder'] ?? '') . '"></textarea>';
            break;
        case 'select':
        case 'classroom':
        case 'school':
        case 'student':
            echo '<select class="input" ' . $attrs . '>';
            if (!$required) {
                echo '<option value="">— ไม่ระบุ —</option>';
            }
            foreach (field_options($field) as $value => $text) {
                echo '<option value="' . e((string) $value) . '">' . e($text) . '</option>';
            }
            echo '</select>';
            break;
        case 'number':
            echo '<input class="input" type="number" inputmode="decimal" ' . $attrs . ' min="' . e((string) ($field['min'] ?? '')) . '" max="' . e((string) ($field['max'] ?? '')) . '" step="' . e($field['step'] ?? '1') . '">';
            break;
        case 'time':
        case 'date':
            echo '<input class="input" type="' . $field['type'] . '" ' . $attrs . '>';
            break;
        case 'url':
            echo '<input class="input" type="url" inputmode="url" ' . $attrs . ' maxlength="' . (int) ($field['max'] ?? 300) . '" placeholder="' . e($field['placeholder'] ?? '') . '" autocomplete="off">';
            break;
        case 'secret': // write-only: the stored value is never rendered back
            echo '<input class="input" type="password" ' . $attrs . ' maxlength="' . (int) ($field['max'] ?? 300) . '" placeholder="' . e($field['placeholder'] ?? '') . '" autocomplete="off" data-secret>';
            echo '<small class="field__hint" data-secret-hint></small>';
            break;
        case 'image':
            echo '<input class="input" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple data-image-input>'
                . '<small class="field__hint">JPG / PNG / WebP · สูงสุด ' . MEDIA_MAX_FILES . ' รูป · ระบบย่อรูปให้อัตโนมัติ · เก็บไฟล์ 6 เดือน</small>'
                . '<span class="upload-preview" data-upload-preview></span>';
            break;
        case 'phone':
            echo '<input class="input" type="tel" inputmode="numeric" maxlength="10" ' . $attrs . ' placeholder="08xxxxxxxx">';
            break;
        case 'pin':
            echo '<input class="input" type="text" inputmode="numeric" maxlength="6" ' . $attrs . ' placeholder="123456">';
            break;
        default:
            echo '<input class="input" type="text" ' . $attrs . ' maxlength="' . (int) ($field['max'] ?? 120) . '" placeholder="' . e($field['placeholder'] ?? '') . '">';
    }
    echo '</label>';
}

/** Bottom-sheet form for a table. $hide = fields not shown (forced by scope, e.g. classroom for teachers). */
function render_form_sheet(string $table, string $title, array $hide = []): void
{
    $def = table_def($table);
    render_sheet_open('sheet-form-' . $table, $title, 'ข้อมูลจะถูกบันทึกตามสิทธิ์ของบัญชีนี้');
    echo '<form class="form" data-form-table="' . e($table) . '" data-title-add="' . e('เพิ่ม' . $def['label']) . '" data-title-edit="' . e('แก้ไข' . $def['label']) . '" novalidate><input type="hidden" name="id" value="">';
    foreach ($def['fields'] as $key => $field) {
        if (!in_array($key, $hide, true)) {
            render_field($key, $field);
        }
    }
    echo '<p class="form__error" data-form-error hidden></p><div class="row-actions">'
        . '<button type="button" class="btn btn--ghost" data-action="close-sheet">ยกเลิก</button>'
        . '<button type="submit" class="btn btn--primary">' . icon('check') . ' บันทึก</button></div></form>';
    render_sheet_close();
}

function render_sheet_open(string $id, string $title, string $subtitle = ''): void
{
    ?>
    <div class="sheet" id="<?= e($id) ?>" aria-hidden="true">
        <div class="sheet__backdrop" data-action="close-sheet"></div>
        <div class="sheet__panel" role="dialog" aria-modal="true" aria-labelledby="<?= e($id) ?>-title">
            <span class="sheet__handle" aria-hidden="true"></span>
            <div class="sheet__head">
                <div>
                    <h2 class="sheet__title" id="<?= e($id) ?>-title"><?= e($title) ?></h2>
                    <?php if ($subtitle !== ''): ?><p class="sheet__sub"><?= e($subtitle) ?></p><?php endif; ?>
                </div>
                <button type="button" class="icon-btn" data-action="close-sheet" aria-label="ปิด"><?= icon('close') ?></button>
            </div>
    <?php
}

function render_sheet_close(): void
{
    echo '</div></div>';
}

function render_status_sheet(?array $current): void
{
    render_sheet_open('sheet-status', 'เปลี่ยนสถานะห้องเรียน', 'เลือกสิ่งที่เด็ก ๆ กำลังทำอยู่ตอนนี้');
    render_status_picker($current);
    render_sheet_close();
}

function render_confirm_sheet(): void
{
    ?>
    <div class="sheet sheet--confirm" id="sheet-confirm" aria-hidden="true">
        <div class="sheet__backdrop" data-action="close-sheet"></div>
        <div class="sheet__panel" role="alertdialog" aria-modal="true" aria-labelledby="sheet-confirm-title">
            <span class="confirm__icon" aria-hidden="true" data-confirm-icon>🗑️</span>
            <h2 class="sheet__title" id="sheet-confirm-title" data-confirm-title>ยืนยัน</h2>
            <p class="sheet__sub" data-confirm-message></p>
            <div class="row-actions">
                <button type="button" class="btn btn--ghost" data-action="close-sheet">ยกเลิก</button>
                <button type="button" class="btn btn--danger" data-confirm-ok>ยืนยัน</button>
            </div>
        </div>
    </div>
    <?php
}

/* ============================================================================
 * Chat
 * ========================================================================= */

/** One conversation. $me = 'parent' | 'teacher' (whose bubbles sit on the right). */
function render_chat_thread(array $messages, string $me, int $studentId): void
{
    ?>
    <div class="chat" data-chat>
        <ol class="chat__messages" data-chat-scroll>
            <?php if (!$messages): ?><li class="chat__empty">ยังไม่มีข้อความ เริ่มพูดคุยได้เลย 💬</li><?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <li class="bubble<?= $m['from'] === $me ? ' bubble--me' : '' ?>">
                    <span class="bubble__sender"><?= e($m['sender']) ?></span>
                    <p class="bubble__text"><?= e($m['text']) ?></p>
                    <time class="bubble__time"><?= e(thai_time($m['at'])) ?></time>
                </li>
            <?php endforeach; ?>
        </ol>
        <form class="chat__composer" data-chat-form data-student="<?= $studentId ?>">
            <input class="input" type="text" name="text" maxlength="300" placeholder="พิมพ์ข้อความ..." aria-label="พิมพ์ข้อความ" autocomplete="off" required>
            <button type="submit" class="btn btn--primary chat__send" aria-label="ส่งข้อความ"><?= icon('send') ?></button>
        </form>
    </div>
    <?php
}

/* ============================================================================
 * Shared feature blocks (teacher + parent)
 * ========================================================================= */

function render_sleep_card(?array $sleep): void
{
    $quality = cat_find('sleep', $sleep['quality'] ?? 'pending');
    $done = $sleep && $sleep['start'] && $sleep['end'];
    ?>
    <div class="sleep-card">
        <div class="sleep-card__times">
            <div><small>เริ่มนอน</small><strong><?= e($sleep['start'] ?: '--:--') ?></strong></div>
            <span class="sleep-card__arrow" aria-hidden="true">🌙 → ☀️</span>
            <div><small>ตื่น</small><strong><?= e($sleep['end'] ?: '--:--') ?></strong></div>
        </div>
        <p class="sleep-card__duration"><?= $done ? 'นอน ' . e(thai_duration(minutes_between($sleep['start'], $sleep['end']))) : 'ยังไม่มีข้อมูลการนอนวันนี้' ?></p>
        <p><?= tone_badge($quality) ?></p>
        <?php if (!empty($sleep['note'])): ?><p class="hint">📝 <?= e($sleep['note']) ?></p><?php endif; ?>
    </div>
    <?php
}

function render_health_card(?array $health): void
{
    $condition = cat_find('health', $health['condition'] ?? null);
    ?>
    <div class="health-card">
        <div class="health-card__temp"><small>อุณหภูมิ</small><strong><?= $health ? e(number_format((float) $health['temperature'], 1)) . '°C' : '-' ?></strong></div>
        <ul class="menu-list">
            <li><span>สุขภาพวันนี้</span><?= tone_badge($condition, 'ยังไม่ได้ตรวจ') ?></li>
            <li><span>อาการ</span><strong><?= e($health['symptoms'] ?? '-') ?></strong></li>
            <li><span>หมายเหตุครู</span><strong><?= e(($health['note'] ?? '') ?: '-') ?></strong></li>
        </ul>
    </div>
    <?php
}

function render_dev_bars(array $dev): void
{
    echo '<ul class="dev-bars">';
    foreach (mk_catalog()['devDomains'] as $d) {
        $value = (int) ($dev[$d['code']] ?? 0);
        echo '<li><span class="dev-bars__label">' . $d['emoji'] . ' ' . e($d['label']) . '</span>'
            . '<span class="progress" role="progressbar" aria-valuenow="' . $value . '" aria-valuemin="0" aria-valuemax="100" aria-label="' . e($d['label']) . '"><span style="width: ' . $value . '%"></span></span>'
            . '<strong>' . $value . '</strong></li>';
    }
    echo '</ul>';
}

function render_star_history(array $rows, int $limit = 20): void
{
    if (!$rows) {
        echo '<p class="empty">ยังไม่มีดาวสะสม</p>';
        return;
    }
    echo '<ol class="star-history">';
    foreach (array_slice($rows, 0, $limit) as $r) {
        echo '<li><span class="star-history__points">+' . (int) $r['points'] . ' ⭐</span><span>' . e($r['reason']) . '</span><time>' . e(thai_short_date($r['date'])) . '</time></li>';
    }
    echo '</ol>';
}

function render_portfolio_grid(array $works, bool $withStudent = false, bool $manage = false): void
{
    if (!$works) {
        echo empty_state('🖼️', 'ยังไม่มีผลงาน');
        return;
    }
    echo '<div class="gallery">';
    foreach ($works as $w) {
        $cat = cat_find('portfolioCategories', $w['category'] ?? null);
        $student = $withStudent ? find_row('students', $w['student_id']) : null;
        $media = has_feature('media') ? media_for('portfolio', $w['id']) : [];
        echo '<figure class="gallery__item" data-filter-item data-group="' . e($w['category'] ?? '') . '" data-search="' . e($w['title'] . ' ' . ($student['nickname'] ?? '')) . '">'
            . ($media ? capture(fn () => render_media_cover($media, $w['title'], $w['date'])) : art_svg((int) ($w['art'] ?? 0), $w['title']))
            . '<figcaption><strong>' . e($w['title']) . '</strong>'
            . '<small>' . ($cat ? $cat['emoji'] . ' ' . e($cat['label']) . ' · ' : '') . e(thai_short_date($w['date'])) . ($student ? ' · ' . e($student['nickname']) : '') . '</small>'
            . (!empty($w['comment']) ? '<p>💬 ' . e($w['comment']) . '</p>' : '')
            . ($manage ? '<span class="gallery__actions">' . btn_delete('portfolio', $w['id'], $w['title']) . '</span>' : '')
            . '</figcaption></figure>';
    }
    echo '</div>';
}

function render_photo_grid(array $photos, bool $manage = false): void
{
    if (!$photos) {
        echo empty_state('📷', 'ยังไม่มีภาพกิจกรรม');
        return;
    }
    echo '<div class="gallery gallery--photos">';
    foreach ($photos as $p) {
        echo '<figure class="gallery__item">' . art_svg((int) $p['art'], $p['caption'])
            . '<span class="gallery__badge">' . ($p['emoji'] ?? '📷') . '</span>'
            . '<figcaption><strong>' . e($p['caption']) . '</strong><small>' . e(thai_short_date($p['date'])) . ' · ' . e($p['activity'] ?? '') . '</small>'
            . ($manage ? '<span class="gallery__actions">' . btn_delete('photos', $p['id'], $p['caption']) . '</span>' : '')
            . '</figcaption></figure>';
    }
    echo '</div>';
}

function render_calendar_list(array $events, bool $manage = false): void
{
    usort($events, fn ($a, $b) => strcmp($a['date'] . $a['time'], $b['date'] . $b['time']));
    if (!$events) {
        echo empty_state('📅', 'ยังไม่มีกิจกรรมในปฏิทิน');
        return;
    }
    echo '<ol class="event-list">';
    foreach ($events as $ev) {
        $ts = strtotime($ev['date']);
        echo '<li class="event"><span class="event__date"><small>' . THAI_DAYS_SHORT[(int) date('w', $ts)] . '</small><strong>' . date('j', $ts) . '</strong><small>' . THAI_MONTHS_SHORT[(int) date('n', $ts)] . '</small></span>'
            . '<div class="event__body"><strong>' . ($ev['emoji'] ?? '🎉') . ' ' . e($ev['title']) . '</strong>'
            . '<ul class="event__meta"><li>🕘 ' . e($ev['time'] ?: '-') . ' น.</li><li>👕 ' . e($ev['dress'] ?: '-') . '</li><li>🎒 ' . e($ev['bring'] ?: '-') . '</li><li>🏠 เลิก ' . e($ev['dismiss'] ?: '-') . ' น.</li></ul></div>'
            . ($manage ? '<div class="activity__actions">' . btn_edit('calendarEvents', $ev) . btn_delete('calendarEvents', $ev['id'], $ev['title']) . '</div>' : '')
            . '</li>';
    }
    echo '</ol>';
}

function render_notification_list(array $rows): void
{
    usort($rows, fn ($a, $b) => strcmp($b['at'], $a['at']));
    if (!$rows) {
        echo empty_state('🔔', 'ยังไม่มีการแจ้งเตือน');
        return;
    }
    echo '<ol class="notify-list">';
    foreach ($rows as $n) {
        $type = cat_find('notifyTypes', $n['type']);
        echo '<li class="notify notify--' . e($n['type']) . '"><span class="notify__icon" aria-hidden="true">' . ($type['emoji'] ?? '🔔') . '</span>'
            . '<div><small>' . e($type['label'] ?? '') . ' · ' . e(thai_time($n['at'])) . '</small><strong>' . e($n['title']) . '</strong>'
            . ($n['body'] ? '<p>' . e($n['body']) . '</p>' : '') . '</div></li>';
    }
    echo '</ol>';
}

/** Attendance quick-mark row for teachers / admins. */
function render_attendance_row(array $student, ?array $record, bool $canEdit): void
{
    $status = cat_find('attendance', $record['status'] ?? null);
    echo '<li class="att-row" data-filter-item data-group="' . e($record['status'] ?? '') . '" data-search="' . e($student['nickname'] . ' ' . $student['name']) . '">'
        . student_avatar($student, 'sm') . '<div class="att-row__name"><strong>' . e($student['nickname']) . '</strong><small>'
        . e(($record['checkIn'] ?? '') ? 'มาถึง ' . $record['checkIn'] . ' น.' : (($record['note'] ?? '') ?: '-')) . '</small></div>';
    if ($canEdit && $record) {
        echo '<div class="att-row__chips" role="group" aria-label="สถานะการมาเรียนของ' . e($student['nickname']) . '">';
        foreach (mk_catalog()['attendance'] as $a) {
            $on = $a['code'] === $record['status'];
            echo '<button type="button" class="att-chip att-chip--' . e($a['tone']) . ($on ? ' is-on' : '') . '" aria-pressed="' . ($on ? 'true' : 'false') . '" data-action="quick-save" data-table="attendance" data-id="' . $record['id'] . '" data-field="status" data-value="' . e($a['code']) . '" title="' . e($a['label']) . '">' . $a['emoji'] . '<span>' . e($a['label']) . '</span></button>';
        }
        echo '</div>';
    } else {
        echo tone_badge($status, 'ไม่มีข้อมูล');
    }
    echo '</li>';
}


/* ============================================================================
 * CCTV (core/cctv.php decides visibility; these only render authorised cameras)
 * ========================================================================= */

function camera_status_badge(array $camera): string
{
    $st = CAMERA_STATUSES[$camera['status']] ?? CAMERA_STATUSES['offline'];
    $html = '<span class="tone tone--' . $st['tone'] . '">' . $st['emoji'] . ' ' . e($st['label']) . '</span>';
    return empty($camera['is_active']) ? $html . ' <span class="tone tone--neutral">⏸️ ปิดใช้งาน</span>' : $html;
}

/** Summary tiles (total / online / offline / maintenance) computed from the given cameras. */
function render_cctv_summary(array $cameras): void
{
    $n = camera_counts($cameras);
    echo '<div class="kpi-grid cctv-summary">'
        . stat_card('📹', 'กล้องทั้งหมด', $n['total'] . ' ตัว')
        . stat_card('🟢', 'Online', $n['online'], '', 'good')
        . stat_card('🔴', 'Offline', $n['offline'], '', $n['offline'] ? 'critical' : '')
        . ($n['maintenance'] ? stat_card('🛠️', 'Maintenance', $n['maintenance'], '', 'warning') : '')
        . '</div>';
}

/** One camera tile with a live player slot. No stream URL is ever printed into the page. */
function render_cctv_card(array $camera, bool $showSchool = false): void
{
    $pub = camera_public($camera);
    $json = json_attr($pub);
    ?>
    <article class="cctv-card is-<?= e($camera['status']) ?><?= $pub['active'] ? '' : ' is-inactive' ?>" data-filter-item data-group="<?= (int) $camera['school_id'] ?>" data-search="<?= e($pub['code'] . ' ' . $pub['name'] . ' ' . $pub['location']) ?>">
        <button type="button" class="cctv-card__screen" data-action="cctv-open" data-camera="<?= $json ?>" aria-label="ดู <?= e($pub['name']) ?> แบบเต็มจอ">
            <span class="cctv-screen" data-cctv-player data-camera="<?= $json ?>"></span>
        </button>
        <div class="cctv-card__info">
            <div class="cctv-card__title">
                <strong>📹 <?= e($pub['name']) ?></strong>
                <small><?= e($pub['code']) ?> · <?= e($pub['location']) ?></small>
                <small><?= e($pub['area']) ?><?= $showSchool ? ' · ' . e($pub['school']) : '' ?></small>
            </div>
            <div class="cctv-card__badges"><?= camera_status_badge($camera) ?></div>
        </div>
        <button type="button" class="btn btn--primary btn--block cctv-card__cta" data-action="cctv-open" data-camera="<?= $json ?>">⛶ ดูแบบเต็มจอ</button>
    </article>
    <?php
}

/** Compact camera list for dashboards ("ดู Live" opens the viewer). */
function render_cctv_mini(array $cameras, int $limit = 5): void
{
    if (!$cameras) {
        echo '<p class="empty">ไม่มีกล้องที่บัญชีนี้ได้รับสิทธิ์ดู</p>';
        return;
    }
    echo '<ul class="cctv-mini">';
    foreach (array_slice($cameras, 0, $limit) as $camera) {
        $pub = camera_public($camera);
        $st = CAMERA_STATUSES[$camera['status']] ?? CAMERA_STATUSES['offline'];
        echo '<li><span class="cctv-mini__dot" aria-hidden="true">' . $st['emoji'] . '</span>'
            . '<div><strong>' . e($pub['name']) . '</strong><small>' . e($pub['code'] . ' · ' . $pub['area'] . ' · ' . $st['label']) . '</small></div>'
            . '<button type="button" class="btn btn--soft btn--sm" data-action="cctv-open" data-camera="' . json_attr($pub) . '">▶ ดู Live</button></li>';
    }
    echo '</ul>';
}

/** Full-screen / modal viewer (one per page). Filled by core.js after a permission-checked API call. */
function render_cctv_viewer(): void
{
    ?>
    <div class="cctv-viewer" id="cctv-viewer" hidden>
        <div class="cctv-viewer__backdrop" data-action="cctv-close"></div>
        <div class="cctv-viewer__panel" role="dialog" aria-modal="true" aria-labelledby="cctv-viewer-title" data-cctv-fullscreen tabindex="-1">
            <header class="cctv-viewer__head">
                <span class="cctv-live-pill" data-cctv-live>● LIVE</span>
                <div class="cctv-viewer__title"><h2 id="cctv-viewer-title" data-cctv-field="name">กล้อง</h2><small data-cctv-field="location"></small></div>
                <div class="cctv-viewer__tools">
                    <button type="button" class="cctv-tool" data-action="cctv-mute" aria-pressed="true" aria-label="เปิด/ปิดเสียง">🔇</button>
                    <button type="button" class="cctv-tool" data-action="cctv-fullscreen" aria-label="เต็มจอ">⛶</button>
                    <button type="button" class="cctv-tool" data-action="cctv-close" aria-label="ปิด">✕</button>
                </div>
            </header>
            <div class="cctv-viewer__screen" data-cctv-viewer-screen></div>
            <dl class="cctv-detail">
                <div><dt>รหัสกล้อง</dt><dd data-cctv-field="code">-</dd></div>
                <div><dt>รุ่นกล้อง</dt><dd data-cctv-field="model">-</dd></div>
                <div><dt>ตำแหน่ง</dt><dd data-cctv-field="location">-</dd></div>
                <div><dt>พื้นที่</dt><dd data-cctv-field="area">-</dd></div>
                <div><dt>สถานะ</dt><dd data-cctv-field="statusLabel">-</dd></div>
                <div><dt>Stream</dt><dd data-cctv-field="streamLabel">-</dd></div>
            </dl>
            <p class="cctv-viewer__note">🔒 ดูแบบ Realtime เท่านั้น · ไม่มีการบันทึก ดาวน์โหลด หรือย้อนดูภาพ</p>
        </div>
    </div>
    <?php
}

/* ============================================================================
 * Images (Portfolio / Activity / Food) — rendered only from scoped media rows
 * ========================================================================= */

/** Thumbnail strip. Expired / deleted images show a placeholder — never the old URL. */
function render_media_strip(array $media, string $label): void
{
    if (!$media) {
        return;
    }
    usort($media, fn ($a, $b) => strcmp($a['uploaded_at'], $b['uploaded_at']));
    echo '<div class="media-strip">';
    foreach ($media as $m) {
        echo media_tile($m, $label);
    }
    echo '</div>';
}

function media_tile(array $m, string $label): string
{
    if (media_status($m) !== 'available') {
        return '<div class="media-expired" role="img" aria-label="รูปภาพหมดเวลาเก็บไฟล์"><span aria-hidden="true">📷</span><small>รูปภาพหมดเวลาเก็บไฟล์</small></div>';
    }
    $src = url('media.php?id=' . $m['id']);
    $caption = $label . ' · ' . thai_short_date(substr($m['uploaded_at'], 0, 10));
    return '<button type="button" class="media-thumb" data-action="media-open" data-src="' . e($src) . '" data-caption="' . e($caption) . '">'
        . '<img src="' . e($src) . '" alt="' . e($label) . '" loading="lazy" width="' . (int) $m['width'] . '" height="' . (int) $m['height'] . '"></button>';
}

/** Large cover (portfolio card): first image + "+N" when there are more. */
function render_media_cover(array $media, string $label, string $date): void
{
    usort($media, fn ($a, $b) => strcmp($a['uploaded_at'], $b['uploaded_at']));
    echo '<div class="media-cover">' . media_tile($media[0], $label) . (count($media) > 1 ? '<span class="media-cover__more">+' . (count($media) - 1) . ' รูป</span>' : '') . '</div>';
}

function btn_upload(string $ownerType, int $ownerId, string $title): string
{
    return '<button type="button" class="icon-btn" data-action="upload-open" data-owner-type="' . e($ownerType) . '" data-owner-id="' . $ownerId . '" data-title="' . e($title) . '" aria-label="เพิ่มรูป ' . e($title) . '">📷</button>';
}

/** Food photos of one menu row (one day of a classroom) with an upload button for staff. */
function render_food_photos(?array $menuRow, string $label): void
{
    if (!$menuRow) {
        return;
    }
    $canUpload = can_write_table(current_user(), 'foodMenus') && can_write_table(current_user(), 'mediaFiles');
    $media = array_values(array_filter(media_for('food', $menuRow['id']), fn ($m) => empty($m['meal']))); // per-meal photos sit on their meal card
    echo '<div class="food-photos">';
    echo '<div class="food-photos__head"><strong>📸 รูปอาหารรวม · ' . e(thai_short_date($menuRow['date'])) . '</strong>'
        . ($canUpload ? '<button type="button" class="btn btn--soft btn--sm" data-action="upload-open" data-owner-type="food" data-owner-id="' . $menuRow['id'] . '" data-title="' . e($label) . '">📷 เพิ่มรูปอาหาร</button>' : '') . '</div>';
    if ($media) {
        render_media_strip($media, $label);
    } else {
        echo '<p class="hint">' . ($canUpload ? 'ถ่ายรูปรายมื้อได้ที่ปุ่ม “แก้ไข” ของแต่ละมื้อ หรือเพิ่มรูปรวมที่ปุ่มด้านบน' : 'ดูรูปอาหารแต่ละมื้อได้ที่การ์ดของมื้อนั้น') . '</p>';
    }
    echo '</div>';
}

/** Activity images grouped by day/activity (newest first) for the given classroom ids. */
function render_activity_media_history(array $classroomIds): void
{
    $groups = [];
    foreach (scoped('mediaFiles') as $m) {
        if ($m['owner_type'] === 'activity' && in_array($m['classroom_id'], $classroomIds, true)) {
            $groups[$m['owner_id']][] = $m;
        }
    }
    $items = [];
    foreach ($groups as $activityId => $media) {
        $a = find_row('activities', (int) $activityId);
        if ($a) {
            $items[] = ['activity' => $a, 'media' => $media];
        }
    }
    usort($items, fn ($x, $y) => strcmp($y['activity']['date'] . $y['activity']['time'], $x['activity']['date'] . $x['activity']['time']));
    if (!$items) {
        echo empty_state('📷', 'ยังไม่มีรูปกิจกรรม');
        return;
    }
    echo '<ol class="media-history">';
    foreach ($items as ['activity' => $a, 'media' => $media]) {
        echo '<li><p class="media-history__title"><span aria-hidden="true">' . $a['icon'] . '</span> <strong>' . e($a['title']) . '</strong>'
            . '<small>' . e(thai_short_date($a['date'])) . ' · ' . e($a['time']) . ' น.</small></p>'
            . (($a['detail'] ?? '') !== '' ? '<p class="hint">' . e($a['detail']) . '</p>' : '');
        render_media_strip($media, $a['title']);
        echo '</li>';
    }
    echo '</ol>';
}

/**
 * Take a photo (camera on phones) or pick images. Files from both buttons are collected by
 * core.js (Images picker) and sent with the form — max MEDIA_MAX_FILES, resized before upload.
 */
function image_picker(): string
{
    return '<div class="image-picker" data-image-picker>'
        . '<div class="image-picker__buttons">'
        . '<label class="btn btn--soft btn--sm">📸 ถ่ายภาพ<input type="file" accept="image/*" capture="environment" data-image-source hidden></label>'
        . '<label class="btn btn--soft btn--sm">🖼️ เลือกรูป<input type="file" accept="image/jpeg,image/png,image/webp" multiple data-image-source hidden></label>'
        . '</div>'
        . '<span class="upload-preview" data-upload-preview></span>'
        . '<small class="field__hint">ระบบย่อรูปให้อัตโนมัติ · เก็บไฟล์ 6 เดือน</small>'
        . '</div>';
}

/** Shared sheets: image upload (activity / food) and full-size image viewer. */
function render_media_sheets(): void
{
    if (can_write_table(current_user(), 'mediaFiles')) { // upload sheet only for roles that may upload
        render_upload_sheet();
    }
    render_sheet_open('sheet-media', 'รูปภาพ');
    echo '<figure class="media-viewer"><img alt="" data-media-img><figcaption data-media-caption></figcaption></figure>';
    render_sheet_close();
}

function render_upload_sheet(): void
{
    render_sheet_open('sheet-upload', 'เพิ่มรูปภาพ', 'ระบบย่อรูปให้อัตโนมัติ · ไฟล์ภาพเก็บไว้ 6 เดือน');
    ?>
    <form class="form" data-upload-form novalidate>
        <input type="hidden" name="owner_type" value="">
        <input type="hidden" name="owner_id" value="">
        <p class="upload-target" data-upload-title></p>
        <div class="field">
            <span class="field__label">ถ่ายภาพหรือเลือกรูป <small>(JPG / PNG / WebP · สูงสุด <?= MEDIA_MAX_FILES ?> รูป)</small></span>
            <?= image_picker() ?>
        </div>
        <p class="form__error" data-form-error hidden></p>
        <div class="row-actions">
            <button type="button" class="btn btn--ghost" data-action="close-sheet">ยกเลิก</button>
            <button type="submit" class="btn btn--primary">📤 อัปโหลด</button>
        </div>
    </form>
    <?php
    render_sheet_close();
}

/** Past days of a classroom menu: lunch summary + food photos (or expired placeholders). */
function render_food_history(array $menuRows): void
{
    if (!$menuRows) {
        echo '<p class="empty">ยังไม่มีเมนูย้อนหลัง</p>';
        return;
    }
    echo '<ol class="food-history">';
    foreach ($menuRows as $menu) {
        $lunch = $menu['lunch'] ?? [];
        echo '<li><p class="food-history__day"><strong>' . e(thai_short_date($menu['date'])) . '</strong>'
            . '<span>🍱 ' . e($lunch ? implode(' + ', $lunch) : '-') . ' · 🍚 ' . e(implode(' + ', $menu['breakfast'] ?? []) ?: '-') . '</span></p>';
        render_media_strip(media_for('food', $menu['id']), 'อาหาร ' . thai_short_date($menu['date']));
        echo '</li>';
    }
    echo '</ol>';
}


/* ============================================================================
 * รับ-ส่ง: status explanation · In-app notifications list
 * ========================================================================= */

/** "สถานะการรับนักเรียน" — explains each pickup status in plain Thai (both roles). */
function render_pickup_legend(): void
{
    echo '<section class="card pickup-legend">';
    section_head('ℹ️', 'สถานะการรับนักเรียน');
    echo '<ul class="pickup-legend__list">';
    foreach (mk_catalog()['pickupStatus'] as $st) {
        echo '<li><span aria-hidden="true">' . $st['emoji'] . '</span><div><strong>' . e($st['label']) . '</strong><p>' . e($st['explain']) . '</p></div></li>';
    }
    echo '</ul></section>';
}

/** Own in-app notifications: ● unread / ○ read. Opening one marks it read (core.js → notification_read). */
function render_user_notifications(array $user, array $rows): void
{
    if (!$rows) {
        echo empty_state('🔔', 'ยังไม่มีการแจ้งเตือน');
        return;
    }
    echo '<ul class="notif-list">';
    foreach ($rows as $n) {
        $unread = $n['read_at'] === null;
        $when = (substr($n['created_at'], 0, 10) === today() ? 'วันนี้ ' : thai_short_date(substr($n['created_at'], 0, 10)) . ' ') . substr($n['created_at'], 11, 5) . ' น.';
        echo '<li><button type="button" class="notif-item' . ($unread ? ' is-unread' : '') . '" data-action="notification-open" data-id="' . $n['id'] . '">'
            . '<span class="notif-item__dot" aria-label="' . ($unread ? 'ยังไม่ได้อ่าน' : 'อ่านแล้ว') . '">' . ($unread ? '●' : '○') . '</span>'
            . '<span class="notif-item__body"><strong>' . e($n['title']) . '</strong><span>' . e($n['message']) . '</span><time>' . e($when) . '</time></span>'
            . '</button></li>';
    }
    echo '</ul>';
}


/* ============================================================================
 * ผลงานนักเรียน (Student Work = portfolio rows, one per child; images = mediaFiles owner "portfolio")
 * ========================================================================= */

/** Images of the given works the session user may open (parents: every linked child — can_view_media). */
function works_media(array $workIds): array
{
    $user = current_user();
    $ids = array_flip($workIds);
    $rows = array_filter(store_rows('mediaFiles'), fn ($m) => $m['owner_type'] === 'portfolio' && isset($ids[$m['owner_id']]) && can_view_media($m, $user));
    return group_by(array_values($rows), 'owner_id');
}

/** One work card: title, category, date, description, all images (tap → full screen), manage buttons. */
function render_work_card(array $work, array $media, bool $manage): void
{
    $cat = cat_find('portfolioCategories', $work['category'] ?? null);
    ?>
    <article class="work-card">
        <header class="work-card__head">
            <div>
                <h3><?= e($work['title']) ?></h3>
                <p class="work-card__meta"><?= $cat ? $cat['emoji'] . ' ' . e($cat['label']) . ' · ' : '' ?>📅 <?= e(thai_short_date($work['date'])) ?><?= $media ? ' · 🖼️ ' . count($media) . ' รูป' : '' ?></p>
            </div>
            <?php if ($manage): ?>
                <span class="work-card__actions">
                    <?= btn_edit('portfolio', array_intersect_key($work, array_flip(['id', 'student_id', 'title', 'category', 'date', 'comment']))) ?>
                    <?= btn_delete('portfolio', $work['id'], $work['title']) ?>
                </span>
            <?php endif; ?>
        </header>
        <?php if (($work['comment'] ?? '') !== ''): ?><p class="work-card__desc"><?= nl2br(e($work['comment'])) ?></p><?php endif; ?>
        <?php if ($media): ?>
            <?php render_media_strip($media, $work['title']); ?>
        <?php else: ?>
            <div class="work-card__art"><?= art_svg((int) ($work['art'] ?? 0), $work['title']) ?></div>
        <?php endif; ?>
    </article>
    <?php
}

/** Works of one child (newest first) as cards, or the empty state. */
function render_student_works(array $works, bool $manage): void
{
    if (!$works) {
        echo empty_state('🎨', 'ยังไม่มีผลงาน');
        return;
    }
    usort($works, fn ($a, $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);
    $media = works_media(array_column($works, 'id'));
    echo '<div class="work-list">';
    foreach ($works as $w) {
        render_work_card($w, $media[$w['id']] ?? [], $manage);
    }
    echo '</div>';
}

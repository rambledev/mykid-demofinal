<?php
/**
 * Core charts — lightweight HTML/CSS charts (no external library, no external API).
 *
 * Rules followed: one axis, thin bars with rounded ends anchored to the baseline,
 * a 2px gap between stacked segments, recessive gridlines, a legend whenever there are
 * 2+ series, identity never carried by colour alone (labels / emoji), text in ink colours,
 * and a hover tooltip on every mark ([data-tip], handled in core.js).
 *
 * Categorical colours (validated, CVD-safe for 3 slots): blue, orange, aqua.
 */
declare(strict_types=1);

const CHART_SERIES = ['#2a78d6', '#eb6834', '#1baf7a'];

/**
 * Vertical bar chart.
 * $bars = [['label' => 'จ. 5', 'value' => 92, 'color' => optional], ...]
 */
function chart_bars(array $bars, string $unit = '', ?int $max = null, string $caption = ''): string
{
    if (!$bars) {
        return '<p class="empty">ยังไม่มีข้อมูล</p>';
    }
    $max ??= max(1, (int) ceil(max(array_column($bars, 'value')) * 1.15));
    $html = '<figure class="chart"><div class="chart-bars" style="--bars: ' . count($bars) . '">';
    $html .= '<div class="chart-grid" aria-hidden="true"><span></span><span></span><span></span><span></span></div>';
    foreach ($bars as $bar) {
        $pct = max(2, min(100, $bar['value'] * 100 / $max));
        $tip = $bar['label'] . ': ' . $bar['value'] . $unit;
        $html .= '<div class="chart-bars__col" data-tip="' . e($tip) . '" tabindex="0" aria-label="' . e($tip) . '">'
            . '<span class="chart-bars__value">' . e($bar['value'] . $unit) . '</span>'
            . '<span class="chart-bars__bar" style="height: ' . round($pct, 1) . '%; --bar: ' . e($bar['color'] ?? CHART_SERIES[0]) . '"></span>'
            . '<span class="chart-bars__label">' . e($bar['label']) . '</span></div>';
    }
    $html .= '</div>' . ($caption ? '<figcaption class="chart__caption">' . e($caption) . '</figcaption>' : '') . '</figure>';
    return $html;
}

/**
 * Horizontal bars (good for long Thai labels).
 * $rows = [['label' => ..., 'value' => ..., 'emoji' => optional, 'color' => optional]]
 */
function chart_hbars(array $rows, string $unit = '', ?int $max = null): string
{
    if (!$rows) {
        return '<p class="empty">ยังไม่มีข้อมูล</p>';
    }
    $max ??= max(1, max(array_column($rows, 'value')));
    $html = '<ul class="chart-hbars">';
    foreach ($rows as $i => $row) {
        $pct = $row['value'] > 0 ? max(2, $row['value'] * 100 / $max) : 0;
        $tip = $row['label'] . ': ' . $row['value'] . $unit;
        $html .= '<li data-tip="' . e($tip) . '" tabindex="0" aria-label="' . e($tip) . '"><span class="chart-hbars__label">' . ($row['emoji'] ?? '') . ' ' . e($row['label']) . '</span>'
            . '<span class="chart-hbars__track"><span style="width: ' . round($pct, 1) . '%; --bar: ' . e($row['color'] ?? CHART_SERIES[0]) . '"></span></span>'
            . '<strong>' . e($row['value'] . $unit) . '</strong></li>';
    }
    return $html . '</ul>';
}

/**
 * 100% stacked bar with legend (e.g. attendance mix). Status colours always ship with emoji + label.
 * $parts = [['label' => 'มาเรียน', 'value' => 52, 'color' => '#0ca30c', 'emoji' => '✅'], ...]
 */
function chart_stack(array $parts, string $unit = 'คน'): string
{
    $total = array_sum(array_column($parts, 'value'));
    $html = '<figure class="chart"><div class="chart-stack" role="img" aria-label="' . e(implode(', ', array_map(fn ($p) => $p['label'] . ' ' . $p['value'] . ' ' . $unit, $parts))) . '">';
    foreach ($parts as $p) {
        if ($p['value'] > 0) {
            $tip = $p['label'] . ': ' . $p['value'] . ' ' . $unit . ' (' . percent($p['value'], $total) . '%)';
            $html .= '<span style="flex: ' . $p['value'] . '; --bar: ' . e($p['color']) . '" data-tip="' . e($tip) . '"></span>';
        }
    }
    $html .= '</div><ul class="chart-legend">';
    foreach ($parts as $p) {
        $html .= '<li><span class="chart-legend__swatch" style="--bar: ' . e($p['color']) . '"></span>' . ($p['emoji'] ?? '') . ' ' . e($p['label'])
            . ' <strong>' . (int) $p['value'] . '</strong> <small>(' . percent($p['value'], $total) . '%)</small></li>';
    }
    return $html . '</ul></figure>';
}

/** Ring gauge for one headline percentage. */
function chart_ring(int $pct, string $label, string $color = CHART_SERIES[2]): string
{
    $pct = max(0, min(100, $pct));
    return '<div class="chart-ring" style="--pct: ' . $pct . '; --bar: ' . e($color) . '" role="img" aria-label="' . e($label . ' ' . $pct . '%') . '">'
        . '<div class="chart-ring__inner"><strong>' . $pct . '%</strong><small>' . e($label) . '</small></div></div>';
}

/** Attendance parts for chart_stack() from attendance_counts(). */
function attendance_parts(array $counts): array
{
    return array_map(fn ($a) => ['label' => $a['label'], 'value' => $counts[$a['code']] ?? 0, 'color' => $a['color'], 'emoji' => $a['emoji']], mk_catalog()['attendance']);
}

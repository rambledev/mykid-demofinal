<?php
/** "คู่มือการใช้งาน" — the manual of the logged-in role (content: app/config/manuals.php). */
declare(strict_types=1);

$user = current_user();
$manual = (require dirname(__DIR__, 2) . '/config/manuals.php')[$user['role']] ?? null;
if (!$manual) {
    error_page(404, 'ไม่พบคู่มือ', 'ยังไม่มีคู่มือสำหรับบทบาทนี้');
}
// Escape first, then turn "**text**" into <strong> — content is trusted config, but stays escaped.
$md = fn (string $text): string => preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($text));
$download = $manual['download'] ?? null;
if ($download && !is_file(dirname(__DIR__, 3) . '/public/' . $download)) {
    $download = null;
}

mk_header(['id' => $user['role'] . '-manual', 'title' => 'คู่มือการใช้งาน', 'nav' => 'manual']);
page_title('📖', $manual['title'], $user['scopeLabel'],
    $download ? '<a class="btn btn--soft btn--sm" href="' . e(asset_url($download)) . '" download>⬇️ ดาวน์โหลด Word</a>' : '');
?>
<section class="card">
    <p><?= $md($manual['intro']) ?></p>
    <ol class="manual-toc" aria-label="สารบัญ">
        <?php foreach ($manual['sections'] as $i => $sec): ?>
            <li><a href="#m<?= $i + 1 ?>"><?= $i + 1 ?>. <?= e($sec['h']) ?></a></li>
        <?php endforeach; ?>
    </ol>
</section>

<?php foreach ($manual['sections'] as $i => $sec): ?>
    <section class="card manual-section" id="m<?= $i + 1 ?>">
        <?php section_head((string) ($i + 1) . '.', $sec['h']); ?>
        <?php foreach ($sec['blocks'] as $b): ?>
            <?php if (isset($b['p'])): ?>
                <p><?= $md($b['p']) ?></p>
            <?php elseif (isset($b['h2'])): ?>
                <h3><?= e($b['h2']) ?></h3>
            <?php elseif (isset($b['note'])): ?>
                <p class="manual-note">💡 <?= $md($b['note']) ?></p>
            <?php elseif (isset($b['steps']) || isset($b['bullets'])): ?>
                <?php $tag = isset($b['steps']) ? 'ol' : 'ul'; ?>
                <<?= $tag ?>><?php foreach ($b['steps'] ?? $b['bullets'] as $li): ?><li><?= $md($li) ?></li><?php endforeach; ?></<?= $tag ?>>
            <?php elseif (isset($b['table'])): ?>
                <?php [$head, $rows] = $b['table']; ?>
                <div class="manual-table-wrap"><table class="manual-table">
                    <thead><tr><?php foreach ($head as $h): ?><th scope="col"><?= e($h) ?></th><?php endforeach; ?></tr></thead>
                    <tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $c): ?><td><?= $md($c) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>
<?php mk_footer(); ?>

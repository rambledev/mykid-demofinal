<?php
/**
 * Admin — ตั้งค่ากล้อง CCTV: name, view URL (/cctv/cam01/ or https://…), display order, on/off.
 *
 * No RTSP / MediaMTX / credentials here. Cameras come from accessible_cameras() (own school only, from
 * the session); every action goes through the permission-checked API: save_camera_settings (add / edit),
 * save is_active (quick-save), delete (delete_with_links). Viewer rights for teachers / parents stay on
 * the กล้องวงจรปิด → จัดการกล้อง tab.
 */
declare(strict_types=1);

$user = current_user();
$manage = can_manage_cameras($user);
$cameras = $manage ? accessible_cameras($user) : [];
mk_log('CctvSettings', 'render', ['user' => $user['id'], 'cameras' => count($cameras)]);

/** Edit-form record: only the fields of this form, the id as a token. */
$record = fn (array $c) => ['id' => tok('cameras', $c['id']), 'name' => $c['name'], 'view_url' => $c['view_url'] ?? '',
    'sort_order' => (string) ($c['sort_order'] ?? 0), 'is_active' => !empty($c['is_active']) ? '1' : '0'];

mk_header(['id' => 'admin-cctv-settings', 'title' => 'ตั้งค่ากล้อง CCTV', 'nav' => 'cctv-settings']);
page_title('📹', 'ตั้งค่ากล้อง CCTV', $user['school']['name'] ?? '',
    $manage ? btn_add('cameras', 'เพิ่มกล้อง', ['sort_order' => (string) (count($cameras) + 1), 'is_active' => '1']) : '');
?>
<div data-live id="live-cctv-settings">
    <?php if (!$manage): ?>
        <?= empty_state('🔒', 'บัญชีนี้ไม่มีสิทธิ์ตั้งค่ากล้อง') ?>
    <?php else: ?>
        <section class="card">
            <?php section_head('🎥', 'รายการกล้อง', '', count($cameras) . ' กล้อง · เรียงตามลำดับการแสดง'); ?>
            <?php if ($cameras): ?>
                <ul class="menu-list cctv-settings">
                    <?php foreach ($cameras as $c): ?>
                        <?php $active = !empty($c['is_active']); $url = (string) ($c['view_url'] ?? ''); ?>
                        <li class="cctv-settings__item">
                            <div class="cctv-settings__info">
                                <strong><span class="cctv-settings__order"><?= (int) ($c['sort_order'] ?? 0) ?></span> <?= e($c['name']) ?></strong>
                                <small class="cctv-settings__url"><?= $url !== '' ? e($url) : 'ยังไม่ได้ตั้งค่า URL' ?></small>
                                <span class="tone tone--<?= $active ? 'good' : 'neutral' ?>"><?= $active ? '✅ เปิดใช้งาน' : '⏸️ ปิดใช้งาน' ?></span>
                            </div>
                            <div class="person__actions">
                                <?php if ($url !== '' && camera_view_url_valid($url)): ?>
                                    <a class="btn btn--soft btn--sm" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="ดูภาพ <?= e($c['name']) ?> (หน้าใหม่)">▶ ดูภาพ</a>
                                <?php else: ?>
                                    <span class="btn btn--soft btn--sm is-disabled" aria-disabled="true">▶ ดูภาพ</span>
                                <?php endif; ?>
                                <button type="button" class="btn btn--soft btn--sm" data-action="quick-save" data-table="cameras" data-id="<?= e(tok('cameras', $c['id'])) ?>"
                                    data-field="is_active" data-value="<?= $active ? '0' : '1' ?>" aria-label="<?= $active ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?> <?= e($c['name']) ?>"><?= $active ? '⏸️ ปิด' : '✅ เปิด' ?></button>
                                <button type="button" class="icon-btn" data-action="open-form" data-table="cameras" data-record="<?= json_attr($record($c)) ?>" aria-label="แก้ไข <?= e($c['name']) ?>"><?= icon('edit') ?></button>
                                <?= btn_delete('cameras', $c['id'], $c['name']) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <?= empty_state('📹', 'ยังไม่มีกล้อง — กด “เพิ่มกล้อง” เพื่อเริ่มต้น') ?>
            <?php endif; ?>
        </section>
        <p class="hint hint--card">🔒 ตั้งค่าเฉพาะกล้องของโรงเรียนนี้ · URL ใช้ที่อยู่ในระบบ เช่น <code>/cctv/cam01/</code> หรือ https://… เท่านั้น
            ไม่ต้องใส่ RTSP หรือรหัสผ่านกล้อง · สิทธิ์การดูของครู/ผู้ปกครอง ตั้งได้ที่เมนู “กล้องวงจรปิด → จัดการกล้อง”</p>
    <?php endif; ?>
</div>
<?php
if ($manage) {
    render_form_sheet('cameras', 'กล้อง CCTV', [], ['fields' => camera_settings_fields(), 'action' => 'save_camera_settings']);
}
mk_footer();

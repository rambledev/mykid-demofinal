<?php
/**
 * CCTV dashboard — every role, every package with the "cctv" module.
 *
 * Cameras come ONLY from accessible_cameras() (core/cctv.php): role + school + classroom /
 * child + cameraPermissions, all from the session. The page never prints a stream URL;
 * each player asks the API ("camera_stream") which re-checks permission per camera.
 * Admin / Super Admin also get the "จัดการกล้อง" tab (camera configuration).
 */
declare(strict_types=1);

$user = current_user();
$cameras = accessible_cameras($user);
$manage = can_manage_cameras($user);
$isSuper = $user['role'] === 'super_admin';
$schools = $isSuper ? scoped('schools') : [];

$scopeNote = match ($user['role']) {
    'super_admin' => 'แสดงกล้องของทุกโรงเรียน · เลือกโรงเรียนเพื่อกรอง',
    'admin'       => 'แสดงกล้องทั้งหมดของโรงเรียน · จัดการการตั้งค่าได้ในแท็บ “จัดการกล้อง”',
    'executive'   => 'แสดงกล้องทั้งหมดของโรงเรียน · อ่านอย่างเดียว ไม่สามารถแก้ไขการตั้งค่า',
    'teacher'     => 'แสดงกล้องห้อง' . $user['classroom']['name'] . ' และพื้นที่ส่วนกลางที่โรงเรียนอนุญาต',
    'parent'      => 'แสดงเฉพาะกล้องที่เกี่ยวข้องกับ' . $user['child']['nickname'] . ' และพื้นที่ส่วนกลางที่โรงเรียนอนุญาต',
};
$actions = $manage ? btn_add('cameras', 'เพิ่มกล้อง', $isSuper ? [] : ['stream_type' => 'mock', 'status' => 'online', 'is_active' => '1', 'allow_teacher' => '1', 'allow_parent' => '0', 'camera_model' => 'Tapo C200C'])
    : ($user['role'] === 'executive' ? '<span class="readonly-badge">👁️ อ่านอย่างเดียว</span>' : '');

mk_header(['id' => $user['role'] . '-cctv', 'title' => $isSuper ? 'CCTV Management' : 'กล้องวงจรปิด', 'nav' => 'cctv']);
page_title('📹', $isSuper ? 'CCTV Management' : 'กล้องวงจรปิด', $user['school']['name'] ?? 'ทุกโรงเรียน · ' . count($schools) . ' แห่ง', $actions);
if ($manage) {
    tab_bar('cctv', ['live' => '📺 ดูกล้อง', 'manage' => '⚙️ จัดการกล้อง']);
}
?>
<div data-live id="live-cctv">
    <section class="cctv-live" data-tab-panel="cctv:live">
        <?php render_cctv_summary($cameras); ?>
        <p class="hint hint--card">🔒 <?= e($scopeNote) ?> · ดูแบบ Realtime เท่านั้น ไม่มีการบันทึกหรือดาวน์โหลดภาพ</p>
        <?php if ($isSuper): ?>
            <?php
            // ?view=<school id> only pre-selects the client-side filter (Super Admin sees every school anyway).
            $view = in_array((int) ($_GET['view'] ?? 0), array_column($schools, 'id'), true) ? (string) (int) $_GET['view'] : '';
            filter_bar('cctv', array_column(array_map(fn ($s) => ['k' => $s['id'], 'v' => $s['emoji'] . ' ' . $s['shortName']], $schools), 'v', 'k'), 'ค้นหากล้อง เช่น CAM-01 หรือ สนามเด็กเล่น', 'group', $view);
            ?>
        <?php endif; ?>
        <?php if ($cameras): ?>
            <div class="cctv-grid" data-filter-list="cctv">
                <?php foreach ($cameras as $camera): ?><?php render_cctv_card($camera, $isSuper); ?><?php endforeach; ?>
            </div>
        <?php else: ?>
            <?= empty_state('📹', 'ยังไม่มีกล้องที่บัญชีนี้ได้รับสิทธิ์ดู') ?>
        <?php endif; ?>
    </section>

    <?php if ($manage): ?>
        <section class="card" data-tab-panel="cctv:manage" hidden>
            <?php section_head('⚙️', 'Camera Configuration', '', 'แก้ไขชื่อ ตำแหน่ง ห้อง ประเภท Stream และสิทธิ์การดู'); ?>
            <div class="table-wrap">
                <table class="table cctv-table">
                    <thead><tr>
                        <th scope="col">กล้อง</th><?php if ($isSuper): ?><th scope="col">โรงเรียน</th><?php endif; ?>
                        <th scope="col">พื้นที่</th><th scope="col">สถานะ</th><th scope="col">Stream</th><th scope="col">RTSP</th>
                        <th scope="col">การใช้งาน</th><th scope="col"><span class="visually-hidden">จัดการ</span></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($cameras as $camera): ?>
                        <?php $pub = camera_public($camera); $active = !empty($camera['is_active']); ?>
                        <tr>
                            <th scope="row"><strong><?= e($camera['code']) ?></strong><br><small><?= e($camera['name']) ?> · <?= e($camera['location']) ?></small></th>
                            <?php if ($isSuper): ?><td><?= e(find_row('schools', $camera['school_id'])['shortName'] ?? '') ?></td><?php endif; ?>
                            <td><?= e($pub['area']) ?></td>
                            <td><?= camera_status_badge(['is_active' => true] + $camera) ?></td>
                            <td><?= e($pub['streamLabel']) ?><?= $camera['stream_url'] ? '' : '<br><small>ยังไม่ตั้งค่า URL</small>' ?></td>
                            <td><?= $camera['rtsp_url'] ? '<span class="tone tone--good">🔐 ตั้งค่าแล้ว</span>' : '<span class="tone tone--neutral">ยังไม่ตั้งค่า</span>' ?></td>
                            <td>
                                <button type="button" class="att-chip att-chip--<?= $active ? 'good' : 'neutral' ?> is-on" data-action="quick-save" data-table="cameras" data-id="<?= $camera['id'] ?>" data-field="is_active" data-value="<?= $active ? '0' : '1' ?>" aria-label="<?= $active ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?> <?= e($camera['code']) ?>">
                                    <?= $active ? '✅ Active' : '⏸️ ปิดอยู่' ?>
                                </button>
                            </td>
                            <td class="cctv-table__actions">
                                <button type="button" class="btn btn--soft btn--sm" data-action="cctv-open" data-camera="<?= json_attr($pub) ?>">ดู</button>
                                <button type="button" class="btn btn--soft btn--sm" data-action="open-form" data-table="cameras" data-record="<?= json_attr(camera_admin_record($camera)) ?>"><?= icon('edit') ?> แก้ไข</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="hint">🔐 RTSP URL ถูกเก็บฝั่ง Server เท่านั้น ไม่ถูกส่งกลับมาที่หน้าเว็บ · ห้ามใส่ Username/Password ใน URL (ตั้งค่าที่ Media Server) · Browser ได้รับเฉพาะ HLS / WebRTC ผ่าน API ที่ตรวจสิทธิ์</p>
        </section>
    <?php endif; ?>
</div>
<?php
if ($manage) {
    render_form_sheet('cameras', 'เพิ่มกล้อง', $isSuper ? [] : ['school_id']);
}
mk_footer();

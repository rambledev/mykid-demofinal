// CCTV Phase 2 — the real admin form in headless Chrome (tests/browser/cdp.mjs).
// Run by tests/run-local.sh after the PHP tests (fixture account: admin A = 0810000001;
// DB state = the last PHP test, 20_cctv_access_test.php).
//
// Checks: showIf toggling, multi-select checkboxes sent as an array, edit form re-ticks the saved
// rooms, an existing single-value form (mealTimes) still saves, and no JavaScript errors anywhere.
import { launch, login, ok, sleep, summary } from './cdp.mjs';

const b = await launch();
const { evaluate, goto } = b;
try {
  await b.setSession(await login('admin', '0810000001'));

  console.log('\nbrowser: camera form (/admin/cctv)');
  await goto('/admin/cctv');
  ok('page loaded as admin', await evaluate("!!document.querySelector('[data-form-table=\"cameras\"]')"));
  await evaluate(`document.querySelector('[data-action="open-form"][data-table="cameras"]:not([data-record])').click()`);
  await sleep(200);
  const form = "document.querySelector('[data-form-table=\"cameras\"]')";
  const roomsHidden = `${form}.querySelector('[data-multi="classroom_ids"]').hidden`;
  ok('add form: area defaults to classroom, room list visible', await evaluate(`${form}.elements.area_type.value === 'classroom' && !${roomsHidden}`));
  await evaluate(`(() => { const s = ${form}.elements.area_type; s.value = 'common'; s.dispatchEvent(new Event('change', { bubbles: true })); })()`);
  ok('common area hides the room list', await evaluate(roomsHidden));
  await evaluate(`(() => { const s = ${form}.elements.area_type; s.value = 'classroom'; s.dispatchEvent(new Event('change', { bubbles: true })); })()`);
  ok('classroom shows it again', !(await evaluate(roomsHidden)));

  // Capture the request body the form sends (submitForm → api 'save').
  await evaluate(`(() => { window.__sent = []; const f = window.fetch; window.fetch = (u, o) => { if (o && typeof o.body === 'string') window.__sent.push(JSON.parse(o.body)); return f(u, o); }; })()`);
  await evaluate(`(() => { const f = ${form};
    f.elements.code.value = 'CAM-77'; f.elements.name.value = 'กล้องเบราว์เซอร์'; f.elements.location.value = 'ทางเดิน';
    f.querySelectorAll('[data-multi="classroom_ids"] input').forEach((el, i) => { el.checked = i < 2; });
    f.querySelector('[type="submit"]').click(); })()`);
  await sleep(1500);
  const sent = await evaluate('window.__sent.find((b) => b.action === "save" && b.table === "cameras")');
  ok('classroom_ids sent as an array of 2 tokens', Array.isArray(sent?.data?.classroom_ids) && sent.data.classroom_ids.length === 2, JSON.stringify(sent?.data?.classroom_ids));
  ok('other fields still single values', sent?.data?.name === 'กล้องเบราว์เซอร์' && sent?.data?.area_type === 'classroom');

  await goto('/admin/cctv');
  const editBtn = `[...document.querySelectorAll('[data-action="open-form"][data-table="cameras"][data-record]')].find((b) => JSON.parse(b.dataset.record).code === 'CAM-77')`;
  ok('saved camera listed', await evaluate(`!!${editBtn}`));
  await evaluate(`${editBtn}.click()`);
  await sleep(200);
  const checked = `[...${form}.querySelectorAll('[data-multi="classroom_ids"] input')].filter((el) => el.checked).length`;
  ok('edit form re-ticks exactly the 2 saved rooms', await evaluate(checked) === 2);
  ok('edit form shows area classroom + list visible', await evaluate(`${form}.elements.area_type.value === 'classroom' && !${roomsHidden}`));
  await evaluate(`(() => { const f = ${form}; f.querySelectorAll('[data-multi="classroom_ids"] input:checked')[0].checked = false; f.querySelector('[type="submit"]').click(); })()`);
  await sleep(1500);
  await goto('/admin/cctv');
  await evaluate(`${editBtn}.click()`);
  await sleep(200);
  ok('after unticking one room, edit shows 1 room', await evaluate(checked) === 1);
  await evaluate(`(() => { const f = ${form}; f.elements.area_type.value = 'common'; f.elements.area_type.dispatchEvent(new Event('change', { bubbles: true })); f.querySelector('[type="submit"]').click(); })()`);
  await sleep(1500);
  await goto('/admin/cctv');
  const rec = await evaluate(`JSON.parse(${editBtn}.dataset.record)`);
  ok('switch to common area: saved with no rooms', rec.area_type === 'common' && rec.classroom_ids.length === 0);
  ok('edit record carries no RTSP key or value', !('rtsp_url' in rec) && !JSON.stringify(rec).includes('rtsp://'), Object.keys(rec).join(','));

  console.log('\nbrowser: existing single-value form (/admin/settings → mealTimes)');
  await goto('/admin/settings');
  await evaluate(`document.querySelector('[data-action="open-form"][data-table="mealTimes"]').click()`);
  await sleep(200);
  await evaluate(`(() => { const f = document.querySelector('[data-form-table="mealTimes"]');
    f.elements.label.value = 'มื้อเบราว์เซอร์'; f.elements.time.value = '10:15'; f.querySelector('[type="submit"]').click(); })()`);
  await sleep(1500);
  await goto('/admin/settings');
  ok('meal saved through the unchanged submitForm path', await evaluate("document.body.innerText.includes('มื้อเบราว์เซอร์') && document.body.innerText.includes('10:15')"));

  console.log('\nbrowser: ตั้งค่ากล้อง CCTV (/admin/cctv-settings)');
  await goto('/admin/cctv-settings');
  const sForm = "document.querySelector('[data-form-table=\"cameras\"][data-form-action=\"save_camera_settings\"]')";
  ok('settings form rendered with its own action', await evaluate(`!!${sForm}`));
  await evaluate(`(() => { window.__sent = []; const f = window.fetch; window.fetch = (u, o) => { if (o && typeof o.body === 'string') window.__sent.push(JSON.parse(o.body)); return f(u, o); }; })()`);
  await evaluate(`document.querySelector('[data-action="open-form"][data-table="cameras"]:not([data-record])').click()`);
  await sleep(200);
  await evaluate(`(() => { const f = ${sForm}; f.elements.name.value = 'ห้องเรียนเบราว์เซอร์'; f.elements.view_url.value = '/cctv/cam77/';
    f.elements.sort_order.value = '1'; f.querySelector('[type="submit"]').click(); })()`);
  await sleep(1500);
  const sentS = await evaluate('window.__sent.find((b) => b.action === "save_camera_settings")');
  ok('submit sends save_camera_settings with the 4 fields', !!sentS && sentS.data.view_url === '/cctv/cam77/' && !('rtsp_url' in sentS.data));
  await goto('/admin/cctv-settings');
  ok('new camera listed with a "ดูภาพ" link to its URL', await evaluate(`!!document.querySelector('a[href="/cctv/cam77/"][target="_blank"]')`));
  const rowEdit = `[...document.querySelectorAll('[data-action="open-form"][data-table="cameras"][data-record]')].find((b) => JSON.parse(b.dataset.record).view_url === '/cctv/cam77/')`;
  await evaluate(`${rowEdit}.click()`);
  await sleep(200);
  ok('edit form filled from the record', await evaluate(`${sForm}.elements.name.value === 'ห้องเรียนเบราว์เซอร์' && ${sForm}.elements.is_active.value === '1'`));
  await evaluate(`document.querySelector('[data-action="close-sheet"]').click()`);

  console.log('\nbrowser: other admin pages load without JavaScript errors');
  for (const path of ['/admin', '/admin/classrooms', '/admin/students', '/admin/teachers', '/admin/calendar']) await goto(path);
  ok('no JavaScript errors on any visited page', b.jsErrors.length === 0, b.jsErrors.join(' | '));
} catch (error) {
  ok('browser test ran to the end', false, error.message);
} finally {
  await b.close();
}
process.exit(summary() ? 1 : 0);

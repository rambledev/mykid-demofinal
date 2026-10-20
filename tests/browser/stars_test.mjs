// ธนาคารความดี give-star form in headless Chrome: category switch shows that category's presets, a preset chip
// fills reason + points, the star saves, and no JavaScript errors. Fixture: teacher A1 = 0810000003.
import { launch, login, ok, sleep, summary } from './cdp.mjs';

const b = await launch();
const { evaluate, goto } = b;
try {
  await b.setSession(await login('teacher', '0810000003'));
  console.log('\nbrowser: ธนาคารความดี (/teacher/stars)');
  await goto('/teacher/stars');
  await evaluate(`document.querySelector('[data-action="open-form"][data-table="stars"]:not([data-record])').click()`);
  await sleep(200);
  const form = "document.querySelector('[data-form-table=\"stars\"]')";
  const visible = (cat) => `!${form}.querySelector('[data-show-if="category"][data-show-value="${cat}"]').hidden`;
  ok('category good → good presets shown, safe-driving presets hidden', await evaluate(`${form}.elements.category.value === 'good' && ${visible('good')} && !${visible('safe_driving')}`));
  await evaluate(`(() => { const s = ${form}.elements.category; s.value = 'safe_driving'; s.dispatchEvent(new Event('change', { bubbles: true })); })()`);
  ok('category safe_driving → safe-driving presets shown', await evaluate(`${visible('safe_driving')} && !${visible('good')}`));
  const chip = `${form}.querySelector('[data-show-value="safe_driving"] [data-action="fill-preset"]')`;
  const want = await evaluate(`({ reason: ${chip}.dataset.reason, points: ${chip}.dataset.points })`);
  await evaluate(`${chip}.click()`);
  ok('preset chip fills reason + points', await evaluate(`${form}.elements.reason.value === ${JSON.stringify(want.reason)} && ${form}.elements.points.value === ${JSON.stringify(want.points)}`), JSON.stringify(want));
  await evaluate(`(() => { const f = ${form}; f.elements.student_id.selectedIndex = 1; f.querySelector('[type="submit"]').click(); })()`);
  await sleep(1500);
  await goto('/teacher/stars');
  ok('saved star appears in the history with 🛵', await evaluate(`[...document.querySelectorAll('.star-history li')].some((li) => li.textContent.includes('🛵') && li.textContent.includes(${JSON.stringify(want.reason)}))`));
  ok('no JavaScript errors', b.jsErrors.length === 0, b.jsErrors.join(' | '));
} catch (error) {
  ok('browser test ran to the end', false, error.message);
} finally {
  await b.close();
}
process.exit(summary() ? 1 : 0);

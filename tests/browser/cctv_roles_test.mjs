// CCTV Phase 3 — every role's CCTV page + dashboard in headless Chrome: loads, mounts the players
// through camera_stream, opens the viewer, and throws no JavaScript errors.
// Fixture accounts from tests/lib.php; cameras left by 20_cctv_access_test.php (parent A sees ACC-C1, C4, C7).
import { launch, login, ok, sleep, summary } from './cdp.mjs';

const roles = [
  ['admin', '0810000001'], ['executive', '0810000002'], ['teacher', '0810000003'], ['teacher', '0810000004'], ['parent', '0810000005'],
];
const b = await launch();
try {
  for (const [role, phone] of roles) {
    console.log(`\nbrowser: ${role} ${phone}`);
    await b.setSession(await login(role, phone));
    const before = b.jsErrors.length;
    await b.goto(`/${role}/cctv`);
    await b.evaluate('window.scrollTo(0, document.body.scrollHeight)'); // lazy players mount on scroll
    await sleep(1200);
    const cards = await b.evaluate("document.querySelectorAll('.cctv-card').length");
    const mounted = await b.evaluate("[...document.querySelectorAll('[data-cctv-player]')].filter((el) => el.querySelector('.cctv-signal, video')).length");
    ok(`/${role}/cctv: ${cards} camera card(s), every player mounted`, mounted === cards, `${mounted}/${cards}`);
    const locked = await b.evaluate("[...document.querySelectorAll('[data-cctv-player]')].filter((el) => el.textContent.includes('ไม่สามารถเปิดกล้องได้')).length");
    ok(`/${role}/cctv: no listed camera refused by camera_stream`, locked === 0, `${locked} refused`);
    if (cards) {
      await b.evaluate("document.querySelector('.cctv-card__cta').click()");
      await sleep(800);
      ok(`/${role}/cctv: viewer opens`, await b.evaluate("!document.querySelector('#cctv-viewer').hidden"));
      await b.evaluate("document.querySelector('[data-action=\"cctv-close\"]').click()");
    }
    await b.goto(`/${role}`);
    ok(`/${role}: dashboard + CCTV pages without JavaScript errors`, b.jsErrors.length === before, b.jsErrors.slice(before).join(' | '));
  }
} catch (error) {
  ok('browser test ran to the end', false, error.message);
} finally {
  await b.close();
}
process.exit(summary() ? 1 : 0);

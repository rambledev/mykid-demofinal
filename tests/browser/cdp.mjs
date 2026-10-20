// Tiny headless-Chrome driver over the DevTools protocol (Node 22 WebSocket, no npm packages).
// Used by tests/browser/*_test.mjs against the local test server (tests/run-local.sh).
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

export const BASE = process.env.TEST_BASE_URL || 'http://127.0.0.1:8099';
const CHROME = process.env.CHROME_BIN || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PIN = '482915'; // tests/lib.php TEST_PIN
export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

let pass = 0; let fail = 0;
export const ok = (name, cond, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? 'PASS' : 'FAIL'} ${name}${cond || !detail ? '' : ` — ${detail}`}`);
};
export const summary = () => { console.log(`\n[Test] ${pass} passed, ${fail} failed`); return fail; };

/** Log in over HTTP and return the session cookie "name=value". */
export async function login(role, phone) {
  const page = await fetch(`${BASE}/login`);
  const cookie = page.headers.getSetCookie().map((c) => c.split(';')[0]).join('; ');
  const csrf = (await page.text()).match(/name="csrf" value="([^"]+)"/)[1];
  const body = new URLSearchParams({ csrf, role, phone });
  [...PIN].forEach((d) => body.append('pin[]', d));
  const res = await fetch(`${BASE}/login`, { method: 'POST', body, headers: { cookie }, redirect: 'manual' });
  return res.headers.getSetCookie().map((c) => c.split(';')[0]).find((c) => c.startsWith('MYKID')) || cookie;
}

/** Start Chrome; returns { evaluate, goto, setSession, jsErrors, close }. */
export async function launch() {
  const profile = mkdtempSync(join(tmpdir(), 'mk-chrome-'));
  const chrome = spawn(CHROME, ['--headless=new', '--remote-debugging-port=9333', `--user-data-dir=${profile}`,
    '--no-first-run', '--no-default-browser-check', '--disable-extensions', 'about:blank'], { stdio: 'ignore' });
  let target;
  for (let i = 0; i < 50 && !target; i++) {
    await sleep(200);
    try { target = (await (await fetch('http://127.0.0.1:9333/json/list')).json()).find((t) => t.type === 'page'); } catch { /* not up yet */ }
  }
  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((r) => { ws.onopen = r; });
  const jsErrors = [];
  const pending = new Map();
  let seq = 0;
  ws.onmessage = (event) => {
    const msg = JSON.parse(event.data);
    if (msg.id && pending.has(msg.id)) { const p = pending.get(msg.id); pending.delete(msg.id); msg.error ? p.reject(new Error(msg.error.message)) : p.resolve(msg.result); return; }
    if (msg.method === 'Runtime.exceptionThrown') jsErrors.push(msg.params.exceptionDetails.exception?.description || msg.params.exceptionDetails.text);
    if (msg.method === 'Runtime.consoleAPICalled' && msg.params.type === 'error') jsErrors.push(msg.params.args.map((a) => a.value ?? a.description).join(' '));
  };
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++seq; pending.set(id, { resolve, reject }); ws.send(JSON.stringify({ id, method, params }));
  });
  await send('Runtime.enable'); await send('Page.enable'); await send('Network.enable');

  const evaluate = async (expression) => {
    const r = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
    return r.result.value;
  };
  const goto = async (path) => {
    await send('Page.navigate', { url: BASE + path });
    for (let i = 0; i < 50; i++) { await sleep(100); if (await evaluate('document.readyState') === 'complete') break; }
    await sleep(300);
  };
  const setSession = async (cookie) => {
    await send('Network.clearBrowserCookies');
    const [name, value] = cookie.split('=');
    await send('Network.setCookie', { name, value, url: BASE });
  };
  const close = async () => {
    try { ws.close(); } catch { /* ignore */ }
    chrome.kill();
    await sleep(300);
    rmSync(profile, { recursive: true, force: true });
  };
  return { evaluate, goto, setSession, jsErrors, close, send };
}

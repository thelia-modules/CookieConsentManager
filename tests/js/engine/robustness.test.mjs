import test from 'node:test';
import assert from 'node:assert/strict';
import { boot, state, cookieValue, decodeCookie } from './_helpers.mjs';

const getRaw = (doc) => (doc.cookie.match(/(?:^|; )ccm_consent=([^;]*)/) || [])[1];
const KEY = 'ccm_consent_retry';
const now = () => Math.floor(Date.now() / 1000);
const seed = (n, next) => (win) => win.localStorage.setItem(KEY, JSON.stringify({ n, next }));
const stored = (win) => JSON.parse(win.localStorage.getItem(KEY));

test('choice cookie: written at the click, before the server answers', async () => {
  const a = boot({ beforeHead: (win) => { win.fetch = () => new Promise(() => {}); } });
  a.click('[data-ccm-banner] [data-ccm-action="accept_all"]');
  const raw = getRaw(a.doc);
  assert.ok(raw, 'cookie present while the request is still in flight');
  const st = decodeCookie(raw);
  assert.equal(st.s.youtube, 1);
  assert.equal(st.pd, 1, 'pending until the server confirms');
  const b = boot({ cookie: raw, beforeHead: (win) => seed(0, now() + 30)(win) });
  assert.equal(b.doc.querySelector('[data-ccm-banner]').hidden, true, 'no banner on the next page');
  assert.ok(!b.doc.documentElement.classList.contains('ccm-show'));
});

test('resend: a failed attempt sets an increasing delay and the next page waits for it', async () => {
  const a = boot({ fetchOpts: { status: 503 } });
  a.click('[data-ccm-banner] [data-ccm-action="reject_all"]');
  await a.settle();
  const first = stored(a.win);
  assert.equal(first.n, 1);
  assert.ok(first.next >= now() + 59 && first.next <= now() + 61, 'first delay: one minute');
  assert.equal(decodeCookie(getRaw(a.doc)).pd, 1, 'kept pending after a 5xx');

  const waiting = boot({ cookie: getRaw(a.doc), beforeHead: seed(1, now() + 50) });
  await waiting.settle();
  assert.equal(waiting.calls.length, 0, 'not resent before the delay');

  const due = boot({ cookie: getRaw(a.doc), fetchOpts: { status: 429 }, beforeHead: seed(1, now() - 1) });
  await due.settle();
  assert.equal(due.calls.length, 1, 'resent once the delay has passed');
  const second = stored(due.win);
  assert.equal(second.n, 2);
  assert.ok(second.next >= now() + 119 && second.next <= now() + 121, 'second delay: two minutes');
  assert.equal(decodeCookie(getRaw(due.doc)).pd, 1, 'kept pending after a 429');
});

test('resend: success drops the pending choice and the retry state', async () => {
  const ok = boot({ cookie: state({ pd: 1 }), beforeHead: seed(3, now() - 1) });
  await ok.settle();
  assert.equal(ok.calls.length, 1);
  assert.equal(ok.win.localStorage.getItem(KEY), null);
  assert.equal(decodeCookie(getRaw(ok.doc)).pd, 0);
});

test('resend: dropped after the 8th failed attempt', async () => {
  const last = boot({ cookie: state({ pd: 1 }), fetchOpts: { fail: true }, beforeHead: seed(7, now() - 1) });
  await last.settle();
  assert.equal(last.calls.length, 1);
  assert.equal(last.win.localStorage.getItem(KEY), null, 'no retry state left');
  assert.equal(decodeCookie(getRaw(last.doc)).pd, 0, 'no longer pending');
  const after = boot({ cookie: getRaw(last.doc), fetchOpts: { fail: true } });
  await after.settle();
  assert.equal(after.calls.length, 0, 'nothing is sent again');
});

test('cookie: a cookie written before the change is read without escape/unescape, and the one written now is readable', async () => {
  const id = 'idé-€-0000';
  const old = cookieValue(state({ id }));
  const a = boot({ cookie: old, beforeHead: (win) => { delete win.escape; delete win.unescape; } });
  assert.equal(a.cmp.getConsentId(), id, 'engine reads the old cookie');
  assert.ok(!a.doc.documentElement.classList.contains('ccm-show'), 'head reads the old cookie');
  a.click('[data-ccm-action="open"]');
  await a.cmp.setConsent({ services: { gtag: 1 } });
  await a.settle();
  const written = decodeCookie(getRaw(a.doc));
  assert.equal(written.f, 1);
  assert.equal(written.s.gtag, 1);
});

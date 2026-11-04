import { test } from 'node:test';
import assert from 'node:assert/strict';
import { catalogue, dispose, runService } from './_harness.mjs';
import { FIXTURES } from './_fixtures.mjs';

const verified = catalogue.filter((s) => s.verified);

for (const entry of verified) {
  test(`verified ${entry.key}: loads what it must`, () => {
    const fixture = FIXTURES[entry.key];
    assert.ok(fixture, `no fixture for verified service ${entry.key}`);

    for (const param of entry.params.filter((p) => p.required)) {
      const value = fixture.user[param.name];
      assert.ok(value !== undefined, `fixture lacks required param ${param.name}`);
      if (param.pattern) assert.match(value, new RegExp(param.pattern), `fixture ${param.name} must match its catalogue pattern`);
    }

    const result = runService(entry.key, { user: fixture.user, runCallbacks: true });
    try {
      assert.deepEqual(result.violations, []);
      assert.deepEqual(result.errors, []);
      assert.equal(result.jsCallsDuringFallback, 0);

      if (fixture.script) {
        assert.ok(result.scripts.some((args) => args[0] === fixture.script),
          `addScript not called with ${fixture.script}; got ${JSON.stringify(result.scripts.map((a) => a[0]))}`);
      }
      if (fixture.domScript) {
        assert.ok(result.window.document.querySelector(`script[src="${fixture.domScript}"]`), `no <script src="${fixture.domScript}">`);
      }
      if (entry.kind === 'media') {
        assert.ok(result.placeholders.includes(entry.key), 'fallback() must render the placeholder through engage()');
      }
      if (fixture.media) {
        const iframe = result.window.document.querySelector(`.${fixture.media.cls} iframe`);
        assert.ok(iframe, 'js() must render the iframe');
        assert.ok(iframe.getAttribute('src').includes(fixture.media.iframeSrc), `iframe src: ${iframe.getAttribute('src')}`);
      }
      if (entry.key === 'googletagmanager') {
        assert.ok([...result.window.dataLayer].some((e) => e.event === 'gtm.js'), 'gtm.js event not pushed');
      } else if (entry.googleTag) {
        assert.deepEqual(result.callbackErrors, [], 'gtag callback must run (timeExpire, dataLayer)');
        const config = [...result.window.dataLayer].map((a) => [...a]).find((a) => a[0] === 'config');
        assert.ok(config, 'gtag("config", ...) not pushed');
        assert.equal(config[2].cookie_expires, 31536000);
      }
    } finally {
      dispose(result);
    }
  });
}

test('bare global timeExpire: without it the gtag callback throws ReferenceError (trap checked)', () => {
  const result = runService('gtag', { user: FIXTURES.gtag.user, runCallbacks: true, timeExpire: false });
  try {
    assert.ok(result.callbackErrors.some((e) => e.startsWith('ReferenceError') && e.includes('timeExpire')), JSON.stringify(result.callbackErrors));
  } finally {
    dispose(result);
  }
});

test('frozen parameters: Consent Mode fallbacks never run js() (gtag, googleads, bingads, clarity, matomotm)', () => {
  for (const key of ['gtag', 'googleads', 'bingads', 'clarity', 'matomotm', 'googletagmanager']) {
    const result = runService(key, { user: FIXTURES[key].user });
    try {
      assert.equal(result.jsCallsDuringFallback, 0, key);
      assert.equal(result.scriptsDuringFallback, 0, `${key}: fallback() loaded a script before consent`);
    } finally {
      dispose(result);
    }
  }
});

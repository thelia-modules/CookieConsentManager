import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { boot, baseConfig, state } from './_helpers.mjs';

// The same table drives tests/php/Unit/ConsentStalenessTest.php: PHP and JS must agree on what a stale choice is.
const { cases } = JSON.parse(readFileSync(join(dirname(fileURLToPath(import.meta.url)), '../../fixtures/consent-staleness.json'), 'utf8'));

for (const c of cases) {
  test(`stale choice, shared table: ${c.name}`, () => {
    const config = { ...baseConfig(), repromptDays: c.repromptDays, substantialVersion: c.substantialVersion };
    const choice = state({ t: Math.floor(Date.now() / 1000) - c.ageSeconds, ver: c.choiceVersion === null ? undefined : c.choiceVersion, s: { ...state().s, gtag: 1 }, p: { ...state().p, analytics: 1 } });
    const a = boot({ config, cookie: choice });
    assert.equal(a.doc.documentElement.classList.contains('ccm-show'), c.stale, 'head.js: banner shown only for a stale choice');
    assert.equal(a.cmp.hasConsent('gtag'), !c.stale, 'engine.js: a stale choice grants nothing');
    assert.equal(a.cmp.hasPurposeConsent('analytics'), !c.stale);
  });
}

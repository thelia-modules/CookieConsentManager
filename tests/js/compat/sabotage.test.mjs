// The guard must be able to fail: sabotaged copies of real fragments have to be rejected.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { dispose, readFragment, runService } from './_harness.mjs';
import { FIXTURES } from './_fixtures.mjs';

function sabotage(key, from, to) {
  const original = readFragment(key);
  assert.ok(original.includes(from), `anchor not found in ${key}`);
  return original.replace(from, to);
}

test('sabotage: access to tarteaucitron.nimportequoi inside js() is reported', () => {
  const fragment = sabotage('gtag', '"js": function () {', '"js": function () { tarteaucitron.nimportequoi;');
  const result = runService('gtag', { fragment, user: FIXTURES.gtag.user });
  try {
    assert.ok(result.violations.includes('nimportequoi'), JSON.stringify(result.violations));
  } finally {
    dispose(result);
  }
});

test('sabotage: access outside the contract inside fallback() is reported', () => {
  const fragment = sabotage('youtube', 'var id = \'youtube\';', 'var id = \'youtube\'; tarteaucitron.nimportequoi();');
  const result = runService('youtube', { fragment });
  try {
    assert.ok(result.violations.includes('nimportequoi'), JSON.stringify(result.violations));
  } finally {
    dispose(result);
  }
});

test('sabotage: undeclared parameter and write outside the contract are reported', () => {
  const param = runService('gtag', {
    fragment: sabotage('gtag', '"js": function () {', '"js": function () { tarteaucitron.parameters.inconnu;'),
    user: FIXTURES.gtag.user,
  });
  const write = runService('gtag', {
    fragment: sabotage('gtag', '"js": function () {', '"js": function () { tarteaucitron.nouveau = 1;'),
    user: FIXTURES.gtag.user,
  });
  try {
    assert.deepEqual(param.violations, ['parameters.inconnu']);
    assert.deepEqual(write.violations, ['set nouveau']);
  } finally {
    dispose(param);
    dispose(write);
  }
});

test('sabotage: a fallback() that calls this.js() is reported', () => {
  const fragment = sabotage('youtube', 'var id = \'youtube\';', 'var id = \'youtube\'; this.js();');
  const result = runService('youtube', { fragment });
  try {
    assert.equal(result.jsCallsDuringFallback, 1);
  } finally {
    dispose(result);
  }
});

test('control: the unmodified fragments are clean', () => {
  for (const key of ['gtag', 'youtube']) {
    const result = runService(key, { user: FIXTURES[key].user });
    try {
      assert.deepEqual(result.violations, []);
      assert.equal(result.jsCallsDuringFallback, 0);
    } finally {
      dispose(result);
    }
  }
});

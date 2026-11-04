// Execution guard (ADR-001 v2, decision 7): every non-excluded service runs js() and fallback()
// against the compat layer wrapped in a Proxy that throws on any access outside the contract.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { catalogue, dispose, runService } from './_harness.mjs';
import { FIXTURES, defaultUser } from './_fixtures.mjs';

const services = catalogue.filter((s) => !s.excluded);

test('catalogue sanity: 249 entries, 13 excluded, 23 verified', () => {
  assert.equal(catalogue.length, 249);
  assert.equal(catalogue.filter((s) => s.excluded).length, 13);
  assert.equal(catalogue.filter((s) => s.verified).length, 23);
});

for (const entry of services) {
  test(`guard: ${entry.key} stays inside the contract`, () => {
    const fixture = FIXTURES[entry.key];
    const result = runService(entry.key, { user: fixture?.user ?? defaultUser(entry), runCallbacks: true });
    try {
      assert.deepEqual(result.violations, [], `access outside contract: ${result.violations.join(', ')}`);
      assert.equal(result.jsCallsDuringFallback, 0, 'fallback() called js()');
      if (entry.verified) {
        assert.deepEqual(result.errors, [], `verified service raised: ${result.errors.join(' | ')}`);
      }
    } finally {
      dispose(result);
    }
  });
}

test('Universal Analytics is excluded for a stated reason, not by accident', () => {
  assert.equal(catalogue.find((s) => s.key === 'analytics')?.excluded, 'universal-analytics');
});

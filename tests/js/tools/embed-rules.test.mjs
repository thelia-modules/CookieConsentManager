import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');

test('embed-rules.json is what the fragments give', () => {
  assert.doesNotThrow(() => execFileSync(process.execPath, [join(ROOT, 'tools/catalogue/embed-rules.mjs'), '--check'], { stdio: 'pipe' }));
});

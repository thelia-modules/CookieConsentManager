import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { load } from './_served.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '../../..');

test('the committed minified scripts are what the sources give (npm run build)', () => {
  assert.doesNotThrow(() => execFileSync(process.execPath, [join(root, 'tools/build/minify.mjs'), '--check'], { stdio: 'pipe' }));
});

test('the minified scripts, as the server serves them, run the same scenario: blocked, then allowed once', async () => {
  const t = load({ minified: true });
  assert.match(t.doc.querySelector('.youtube_player').textContent, /Contenu bloqué : YouTube/);
  assert.equal(t.calls.length, 0);

  t.click('.youtube_player [data-ccm-action="service_allow"]');
  await t.settle();

  assert.equal(t.calls.length, 1);
  assert.equal(t.calls[0].body.services.youtube, 1);
  assert.match(t.doc.querySelector('.youtube_player iframe').getAttribute('src'), /youtube-nocookie\.com\/embed\/dQw4w9WgXcQ/);
});

import test from 'node:test';
import assert from 'node:assert/strict';
import { boot, state } from './_helpers.mjs';

const media = () => state({ p: { analytics: 0, ads: 0, media: 1, support: 0, personalization: 0 }, s: { gtag: 0, googletagmanager: 0, youtube: 1, 'acme-widget': 0, 'tiktok-via-gtm': 0 } });
const fragments = { youtube: { js() {}, fallback() {} } };

test('une iframe restaurée ne reprend ni gestionnaire d événement ni srcdoc', () => {
  const a = boot({ cookie: media(), fragments });
  a.doc.querySelectorAll('#vid,iframe').forEach((e) => e.remove());
  a.doc.body.insertAdjacentHTML('beforeend', '<div id="x" data-ccm-placeholder="youtube" data-ccm-src="https://www.youtube-nocookie.com/embed/x" data-ccm-attr-onload="window.__pwn=1" data-ccm-attr-srcdoc="<script>1</script>" data-ccm-attr-width="560" data-ccm-attr-title="Vid" data-ccm-attr-allowfullscreen=""></div>');
  a.doc.dispatchEvent(new a.win.Event('turbo:load'));
  const f = a.doc.querySelector('iframe[data-ccm-restored]');
  assert.ok(f, 'iframe restaurée');
  assert.equal(f.getAttribute('onload'), null);
  assert.equal(f.getAttribute('srcdoc'), null);
  assert.equal(f.getAttribute('width'), '560');
  assert.equal(f.getAttribute('title'), 'Vid');
  assert.ok(f.hasAttribute('allowfullscreen'));
});

test('une adresse qui n est pas https n est jamais posée en src', () => {
  for (const src of ['javascript:window.__pwn=1', 'data:text/html,x', 'http://x.test/a', '/\\x.test/a', '///x.test/a']) {
    const a = boot({ cookie: media(), fragments });
    a.doc.querySelectorAll('#vid,iframe').forEach((e) => e.remove());
    a.doc.body.insertAdjacentHTML('beforeend', '<div id="x" data-ccm-placeholder="youtube"></div>');
    a.doc.querySelector('#x').setAttribute('data-ccm-src', src);
    a.doc.dispatchEvent(new a.win.Event('turbo:load'));
    assert.equal(a.doc.querySelector('iframe'), null, src);
  }
});

test('une iframe intégrée sans protocole (//hôte) est restaurée comme le serveur l accepte', () => {
  const a = boot({ cookie: media(), fragments });
  a.doc.querySelectorAll('#vid,iframe').forEach((e) => e.remove());
  a.doc.body.insertAdjacentHTML('beforeend', '<div id="x" data-ccm-placeholder="youtube" data-ccm-src="//www.youtube.com/embed/x"></div>');
  a.doc.dispatchEvent(new a.win.Event('turbo:load'));
  assert.equal(a.doc.querySelector('iframe[data-ccm-restored]')?.getAttribute('src'), '//www.youtube.com/embed/x');
});

test('un espace réservé libéré ne reprend pas les gestionnaires d événement', () => {
  const a = boot({ cookie: media(), fragments });
  a.doc.querySelectorAll('#vid,iframe').forEach((e) => e.remove());
  a.doc.body.insertAdjacentHTML('beforeend', '<div id="x" data-ccm-placeholder="youtube" data-ccm-attr-class="youtube_player" data-ccm-attr-onmouseover="window.__pwn=1" data-ccm-attr-videoid="abc"></div>');
  a.doc.dispatchEvent(new a.win.Event('turbo:load'));
  const ph = a.doc.querySelector('#x');
  assert.ok(ph.classList.contains('youtube_player'));
  assert.equal(ph.getAttribute('videoid'), 'abc');
  assert.equal(ph.getAttribute('onmouseover'), null);
});

test('une politique dont l adresse contient une barre oblique inverse est écartée', () => {
  for (const [policy, kept] of [['/\\evil.example', false], ['https://x.test/a\\b', false], ['/politique', true]]) {
    const ctx = boot({ start: false });
    const { doc } = ctx;
    doc.body.insertAdjacentHTML('beforeend', '<template data-ccm-placeholder-template><span data-ccm-placeholder-box><span data-ccm-if-policy><a class="pol" href="{{policyUrl}}">p</a></span></span></template>' +
      '<script type="application/json" id="ccm-services-meta">' + JSON.stringify({ youtube: { controller: 'G', policy_url: policy } }) + '</script>');
    doc.querySelector('#vid').innerHTML = '';
    ctx.cmp.__start();
    assert.equal(!!doc.querySelector('#vid .pol'), kept, policy);
  }
});

import test from 'node:test';
import assert from 'node:assert/strict';
import { boot, baseConfig, state } from './_helpers.mjs';

const PANEL_URL = '/ccm/panel/12-9b2e4f0c1a7d-fr_FR.html';
const PANEL = `<div class="ccm-panel" data-ccm-panel hidden role="dialog" aria-modal="true">
  <button data-ccm-action="close">x</button>
  <button role="switch" aria-checked="false" data-ccm-purpose="analytics"></button>
  <button role="switch" aria-checked="false" data-ccm-service="gtag"></button>
  <button data-ccm-action="save">save</button><span data-ccm-consent-id></span>
</div>`;

// A page that does not carry the panel: it is removed from the DOM and served by the stub at its address.
const page = ({ cookie, panelStatus = 200, extraBody, config = { ...baseConfig(), preferencesUrl: '/fr/page.html' } } = {}) => {
  const panelRequests = [];
  const a = boot({
    config: { ...config, panel: PANEL_URL },
    cookie,
    extraBody,
    beforeHead: (win) => {
      const registry = win.fetch;
      win.fetch = (url, init) => {
        if (url !== PANEL_URL) return registry(url, init);
        panelRequests.push({ url, init });

        return panelStatus === 'network' ? Promise.reject(new Error('net')) : Promise.resolve({ ok: panelStatus === 200, status: panelStatus, text: async () => PANEL });
      };
    },
  });
  a.doc.querySelector('[data-ccm-panel]').remove();

  return { ...a, panelRequests };
};

const settled = () => new Promise((resolve) => setTimeout(resolve, 20));

test('le panneau absent de la page est demandé à son adresse à la première ouverture, puis reste dans la page', async () => {
  const a = page({ cookie: state({ p: { analytics: 1, ads: 0, media: 0, support: 0, personalization: 0 }, s: { gtag: 1, googletagmanager: 0, youtube: 0, 'acme-widget': 0, 'tiktok-via-gtm': 0 } }) });
  assert.equal(a.doc.querySelector('[data-ccm-panel]'), null);

  a.click('[data-ccm-action="open"]');
  await settled();

  const panel = a.doc.querySelector('[data-ccm-panel]');
  assert.ok(panel, 'inserted');
  assert.equal(a.panelRequests.length, 1);
  assert.equal(a.panelRequests[0].init.credentials, 'omit', 'the request carries no cookie');
  assert.equal(panel.hidden, false);
  assert.equal(panel.getAttribute('role'), 'dialog');
  assert.equal(panel.getAttribute('aria-modal'), 'true');
  assert.ok(panel.contains(a.doc.activeElement), 'the focus is in the panel');
  assert.equal(a.doc.querySelector('[data-ccm-service="gtag"]').getAttribute('aria-checked'), 'true', 'the switches show the remembered choice');

  a.click('[data-ccm-action="close"]');
  a.click('[data-ccm-action="open"]');
  await settled();
  assert.equal(a.panelRequests.length, 1, 'not fetched twice');
  assert.equal(a.doc.querySelector('[data-ccm-panel]').hidden, false);
  assert.equal(a.doc.querySelectorAll('[data-ccm-panel]').length, 1);
});

test('deux clics rapides ne demandent le panneau qu une fois', async () => {
  const a = page({});
  a.click('[data-ccm-action="open"]');
  a.click('[data-ccm-action="open"]');
  await settled();
  assert.equal(a.panelRequests.length, 1);
  assert.equal(a.doc.querySelectorAll('[data-ccm-panel]').length, 1);
});

test('un panneau introuvable ne casse rien et la demande est refaite au clic suivant', async () => {
  for (const panelStatus of [404, 'network']) {
    const a = page({ panelStatus });
    a.click('[data-ccm-action="open"]');
    await settled();
    assert.equal(a.doc.querySelector('[data-ccm-panel]'), null);
    assert.equal(a.doc.querySelector('[data-ccm-banner]').hidden, false, 'the banner stays usable');
    a.click('[data-ccm-action="open"]');
    await settled();
    assert.equal(a.panelRequests.length, 2, String(panelStatus));
  }
});

test('un panneau déjà dans la page (aperçu, audit) n est pas demandé', async () => {
  const a = boot({ config: { ...baseConfig(), panel: PANEL_URL } });
  let requested = 0;
  const registry = a.win.fetch;
  a.win.fetch = (url, init) => { if (url === PANEL_URL) requested += 1; return registry(url, init); };
  a.click('[data-ccm-action="open"]');
  await settled();
  assert.equal(requested, 0);
  assert.equal(a.doc.querySelector('[data-ccm-panel]').hidden, false);
});

test('le libellé de finalité d un encart vient des métadonnées quand le panneau n est pas dans la page', () => {
  const a = page({
    extraBody: `<script type="application/json" id="ccm-services-meta">${JSON.stringify({ youtube: { controller: 'Google', policy_url: '' }, '@purposes': { media: 'Contenus embarqués' } })}</script>
      <template data-ccm-placeholder-template><span data-ccm-placeholder-box>{{purposeLabel}}</span></template>`,
  });

  assert.match(a.win.tarteaucitron.adapter.placeholderHtml('youtube'), /Contenus embarqués/);
});

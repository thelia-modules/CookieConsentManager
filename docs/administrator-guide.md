# Administrator guide

This guide is for the merchant and the project manager. No technical knowledge is needed.
With it you can set up the banner, publish it, check what visitors receive, and find the proof of a visitor's choice.

The module shows a banner that collects visitors' choices, keeps a record of those choices, and blocks services (statistics, advertising, videos, chat) until they are accepted.
It helps you collect and prove consent. It does not make a website compliant by itself and it is not legal advice.

Where to find it: Thelia back-office, top menu, "Cookie consent". The screen has seven tabs: Dashboard, Appearance and texts, Purposes and services, Consent Mode, Presets, Registry, Versions and verification.
A strip at the top shows the live version and whether your draft is ahead of it.
The Help screen shows this guide in English, or the French version (`docs/fr/`) when the administrator's language is French.

Legal references used in this guide, all read on 2026-11-04:

- CNIL, "Cookies et autres traceurs : la CNIL publie des lignes directrices modificatives et sa recommandation" (29 September 2020): [lignes directrices modificatives et recommandation](https://www.cnil.fr/fr/cookies-et-autres-traceurs/regles/cookies/lignes-directrices-modificatives-et-recommandation)
- CNIL, "Cookies et traceurs : comment mettre mon site web en conformité ?": [comment mettre mon site web en conformité](https://www.cnil.fr/fr/cookies-et-traceurs-comment-mettre-mon-site-web-en-conformite)
- CNIL, "Cookies : solutions pour les outils de mesure d'audience": [solutions pour les outils de mesure d'audience](https://www.cnil.fr/fr/cookies-solutions-pour-les-outils-de-mesure-daudience)

Below, "the first CNIL page", "the second CNIL page" and "the third CNIL page" mean these three pages in this order.

## Draft first, then publish

- Everything you change goes into the draft. Visitors never see the draft.
- Publishing creates a numbered version that can no longer be edited, and puts it online. Until a version is published, the banner does not appear.
- Each consent is recorded with the number of the version the visitor saw. That is what lets you show what was displayed.
- You can go back: on a version's page, "Restore as the draft" copies an old version into the draft, which you then publish. The whole draft is replaced, including what is not published yet: the page warns you and the server refuses the action unless you tick the confirmation.

## Dashboard

- Status: the live version (date, author, hash), and whether the draft equals it.
- Alerts: red means fix before going live, orange means handle, blue is information. Each actionable alert has a "Fix" button. An alert does not prevent publication. Only the refusals described under "Refusals and alerts" do. Examples: cookie policy page missing or not answering, active language without complete texts, registry purge late, a module that emits trackers is active, banner template overridden by the theme and out of date, theme that does not call both banner hooks, "served rendering differs from the published version" (publish again).
- First choices: for each visitor only the first decision counts. You see the number of first choices over 7, 30 or 90 days and the split between accept all, reject all and custom. This is where to look to know how many visitors refuse.
- General settings: close button (continue without accepting, equivalent to refusing), floating "Manage my cookies" button, page reload when a script consent is withdrawn, automatic blocking of embedded iframes (videos, maps), experimental services, renewal delay, cookie policy URL (one per language) and cookie policy page title.
- Renewal delay: how long a choice lasts before the banner asks again. It runs from 1 to 13 months and is 6 months by default. Above 6 months an orange alert recalls the CNIL recommendation of about six months (see the second CNIL page).
- There is no "Banner enabled" switch. The banner appears as soon as a version is published and disappears if the module is disabled.

## Setup assistant

The assistant has four steps, in order, for a first setup:

1. Choose services: tick the services you use (Google Analytics, Meta, YouTube, Crisp...).
2. Identifiers: enter their identifiers, for example the GA4 measurement ID, of the form `G-XXXXXXXXXX`, shown in Google Analytics under Admin, Data streams. An identifier in the wrong format is refused with a message.
3. Privacy policy: set the cookie policy page (see the sample text below). The page is created in the Thelia back-office menu "Folders and Pages". The module provides a model for it (see "Cookie policy page"). Its address is then entered in the general settings.
4. Preview and publish: open the preview, then publish.

## Services (tab "Purposes and services")

- A service belongs to a purpose: audience measurement, advertising, social networks, third-party content (videos, maps), customer support, preferences and personalisation. A purpose without an active service is not shown to visitors. "Essential" (needed by the site) is implicit and does not ask for consent.
- Catalogue: "verified" services are tested and documented. "Imported" services are experimental and hidden while "Experimental services" is off on the dashboard. Filter by level, category or search.
- For a video, the service sheet gives the HTML to paste into the page ("HTML to paste in your page", with a Copy button). The embedded iframes of YouTube, Vimeo, Dailymotion, Google Maps and most other media services already in your pages are replaced by an "Allow" box while the service is active and published. The list of services that are not is in the [integrator guide](integrator-guide.md#hard-coded-scripts).
- Services proposed by other modules: a module (payment, for example) can declare its own cookies. They stay inactive until you enable them and publish. A "strictly necessary" service stays flagged with its justification.
- Some parameters of a catalogue service are addresses or hosts that the service loads as a script, or the container ID of a tag manager (the container runs whatever its owner puts in it). Changing one needs the CREATE right, like the code of a custom service; the other account identifiers stay with UPDATE. The lists of changes mark these rows "Runs on the storefront".
- Custom services: a service that is not in the catalogue. It has a script address, your own code (CREATE right required to write or change the code or the script address, kept in the version) or a plain declaration (a service loaded by Google Tag Manager).
- Purpose of a catalogue service: read-only. Only a custom service chooses its own.
- Exempt audience measurement: the exemption from consent is limited to a list of tools, displayed with a description: self-hosted Matomo, Wysistat in exempt mode, Plausible, Simple Analytics. For each, tick the conditions you confirm (they are set in the tool itself, and the module cannot see them). The missing condition is named with a help text. Google Analytics, custom services and services of other modules are never eligible. A tool that is already exempt and leaves the list stays visible so that you can remove it. When one of them is active, the banner announces it at the first level, with a link to object.
- The list rests on a reading of the code each tool runs. None of the four tools has been tested on a real account. The third CNIL page names no product. It states conditions (measurement of the publisher's own audience only, anonymous statistics only, no cross-referencing with other processing) and recommends a tracker lifetime of 13 months at most and a retention of 25 months at most. The conditions are yours to check on the tool's side. See [decision 004](decisions/004-audience-measurement-allowlist.md).

### Responsible entity and cookie lifetimes

- Responsible entity: each catalogue service has a "Responsible (legal entity)" field, the legal entity that the visitor reads in the panel (for example "Google Ireland Limited"). It is prefilled for 18 of the 23 verified services, from the publisher's official policy. It stays empty for Matomo (if you host it, you are the controller), Matomo Tag Manager, Hotjar, Brevo and Zendesk Chat, because no clear official source exists. When it is empty, the visitor reads the product name instead and an orange alert tells you. Under a preset, publication is refused.
- Cookie lifetimes: the service sheet has a "Cookie lifetimes" block, one field per cookie, in days (0 for a session cookie, 7,300 days at most). The panel and the no-JavaScript page show "name (duration)".
- Lifetimes are provided for Google (Analytics, Ads, Tag Manager), YouTube and Matomo, from the publisher's official documentation (sources in `Resources/catalogue/REPORT.md`). For other services the module shows no lifetime until you enter one. It never invents a figure that has no official source.
- YouTube exception: the player sets `VISITOR_PRIVACY_METADATA`, which Google does not document. The module declares it without a lifetime. Under the France preset, an active YouTube service refuses publication until you enter that lifetime on the service sheet. 180 days was measured in a browser on 2026-11-01. That is a measurement, not a figure announced by Google.
- Google Tag Manager sets no cookie itself. The module declares only `_ga`, `_ga_*` and `_gcl_au`. Google Analytics 4 (`gtag`) does not declare `_gcl_au`, an advertising cookie that GA4 does not set while no advertising purpose is active. The cookies of the other tags in your container are declared by you, in a custom service with their lifetime.
- Meta Pixel: no official Meta page read for this guide gives the lifetime of `_fbp`, `_fbc` and `fr`. Enter them from the Meta documentation you have in front of you.
- Under the France preset attached to the main language, publication is refused as soon as one cookie of an active service has no known lifetime (catalogue, your entry, or the lifetime of a custom service). Enter the lifetime of that cookie on the service, or disable the service. The message names the service and only the cookies without a lifetime.

## Cookie policy page

Under the France preset, the "Cookie policy" link of the banner must lead to a page about cookies, not to the general terms and conditions.

1. Publish first (the model is written from the published version).
2. On the dashboard, under the cookie policy URL field, click "Generate a model of the cookie policy page" and choose the language.
3. Copy the HTML into the description of a page (Thelia menu "Folders and Pages", HTML mode) and put it online.
4. Enter its address in the field of the matching language, then publish.

The model holds the text that does not change: the site publisher (name, address and email read from Configuration, Store in Thelia, with an alert for what is missing), the roles of each party (joint responsibility for collection and transmission when a service such as Meta Pixel or a social module requires it), the duration of the visitor's choice, the retention of proofs, and the rights of individuals.
The list of services, cookies, lifetimes and responsible entities is not copied into it. A link "Up-to-date list of services and cookies" leads to `/ccm/preferences`, which always matches the published version. You do not have to update the page when you change services.

Under the France preset attached to the main language, every active language must have its own page. Make one page per language. The model exists in the six languages of the module. For another language the fixed sentences are in English and need translating. A language without its own address that falls back to the terms and conditions refuses publication. Read the model before putting it online.

Tip: the Thelia back-office text editor saves an empty summary and postscriptum as `<p></p>`, and some themes display it. If these two lines appear on the page, empty them in the database or adapt the theme template.

## Texts and appearance (tab "Appearance and texts")

- Layout: bar (bottom of the page) or modal. On a screen narrower than 768 px the modal becomes a bottom sheet like the bar, at most 38% of the screen height (150 px minimum).
- Colours: three colours (button, banner background, text). A contrast check blocks saving colours that are too light: text on background 4.5:1, buttons on background 3:1, white label on the button 4.5:1. It suggests a compliant shade of the same hue.
- Texts: one set per active language (see "Languages"). The "Reject" button always keeps the same size and colour as "Accept".
- Layout, colours and texts are saved into the draft with a single button. Then publish.

The equal Reject button follows the CNIL's position. Its guidelines state that "refusing trackers must be as easy as accepting them" (first CNIL page, section on principles), and its compliance page gives the example of two buttons "at the same level and in the same format" (second page).
Treat this as CNIL guidance and a recommendation. The module applies it.
A length gap of more than 50% between the two labels, or a consent-by-browsing formula ("by continuing to browse..."), raises a red alert. Each text has a maximum length, and an empty button label is refused.

## Languages

- The module ships in French, English, German, Dutch, Spanish and Italian: banner, panel, banner messages and back-office (in the language of the administrator's profile). The site languages you activate in Thelia appear in the texts screen and the purposes screen.
- For each text the visitor sees, in order: what you entered in their language, else the module's default text in their language, else what you entered in English, else the English default. A provided language therefore never mixes two languages.
- A new draft holds the English text only. The four other languages show the translated default text until you enter something. "Restore default texts" removes your entry. A text you have already entered does not follow a future change of the default.
- A language that is not provided (Portuguese, for example): until you enter something, English is shown and a red alert says so. Even when you enter the editable texts, the module's fixed sentences (purpose list, way of refusing, partner mention, duration of the choice, panel messages) stay in English, because the module cannot translate them for you. The alert "incomplete translation" reminds you as long as a text or a purpose label is missing.
- The cookie policy page title is unique and follows the main language of the site.
- The German, Dutch, Spanish and Italian translations were not reviewed by a native speaker. Have them reviewed before going live.

## Presets (tab "Presets")

A preset is a ready-to-use set of texts and settings for a country. The module provides one: France (CNIL).
Its legal basis is listed on the screen: GDPR, Directive 2002/58/EC article 5(3), the French Data Protection Act article 82, and CNIL deliberations 2020-091 and 2020-092.
Those references were not re-read for this guide. Only the three CNIL pages listed above were.

1. Choose the preset and the target language (among the active languages).
2. Read the before and after preview. Nothing changes until you click "Import into the draft" (UPDATE right).
3. Import, then publish. The import writes into the draft. It is refused if a section it touches contains a blocking error.

- What the import applies to the target language: first-level texts, purpose labels and descriptions, and the attachment of the language to the preset.
- What applies to the whole site (general settings, Consent Mode, registry retention, exempt measurement, policy page title) is applied only when the target language is the main language of the site. Otherwise the preview says so ("common settings left alone") and only the texts are imported. For a site whose main language is English, importing the French preset on "fr" therefore does not change registry retention.
- A preset never touches services, colours or addresses.
- France preset: a bar with the "Continue without accepting" close button, no floating button (withdrawal goes through the "Manage my cookies" link in the footer), reload on withdrawal, blocking of embedded content, renewal after 6 months, basic Consent Mode with advertising data redaction, proof retention of 25 months, IP address not kept. The texts describe the purposes and say that refusing is as simple as accepting.
- A setting changed by hand after the import stays "attached" to the preset. The attachment does not measure the gap. The alerts do (next section).

### Extra checks under a preset

| Situation | Effect |
|-----------|--------|
| A shared setting is less protective than the one of the main language's preset (renewal, reload on withdrawal, blocking of embedded content, Consent Mode mode, advertising data redaction, URL passthrough, retention, IP address) | Red alert naming the setting and the preset value. Does not block. |
| Two languages are attached to presets that contradict each other on a shared setting | Orange alert |
| An active catalogue service has no responsible entity (any attached preset) | Publication refused |
| France preset: a language it covers has no cookie policy address, or the address looks like a terms-and-conditions or legal notice page. It covers the languages it is attached to, and every active language when it is attached to the main one. | Publication refused |
| A policy address does not answer 200 | Red alert |
| A cookie of an active service has no known lifetime (preset of a language other than the main one) | Red alert |

Terms-and-conditions detection relies on the words of the address (`cgv`, `terms`, `conditions`, `mentions`, `legal`...) when `cookie` or `traceur` is absent. A terms page named differently passes, so check the page yourself.
The address probe refuses private networks. On a shop whose host resolves to a private IP (DDEV, intranet) the alert says "cannot be checked".
Policy addresses are limited to a path of the shop or an absolute `https://` address.

## Refusals and alerts

There are two levels:

- Refusal: a red message when saving or publishing, and nothing is written. It covers what can be detected without ambiguity: Consent Mode default "granted" for a European region or for all regions, exempt measurement on a tool outside the allowed list, change of purpose of a catalogue service, registry retention above 60 months, empty label, invalid address (a script address in `http://` is refused), colour too light, and the preset checks above.
- Red or orange alert on the dashboard: editorial matters and settings to watch. It does not prevent publishing but should be handled before going live: implied-consent wording, gap between Reject and Accept, incomplete language, advanced Consent Mode, setting less protective than the preset.

## Preview

"Preview the draft on the shop" opens your real shop with the draft: the banner shows with your services. Nothing is written to the registry.
A "Preview" strip marks the mode, and no service is actually loaded. To check cookies, use the published version.
The preview and the Verification are signed with the secret of the shop (`APP_SECRET`). If it is empty or shorter than 32 characters, both are unavailable: the dashboard shows a warning and the preview button explains why. Set a longer secret in the environment of the shop.

## Publishing and "substantial change"

- Tab "Versions and verification", "Publish" (CREATE right). Add a comment to find the version later. Publishing empties the shared cache of the shop (Thelia 3.3). On a shop served by several servers, each with its own application cache, a publication can take up to 300 seconds to reach the other servers.
- Substantial change: tick it when you change what was announced to the visitor (banner texts, controller of a processing, purpose). The banner is then shown again to everyone, including those who had already chosen. Without it, only visitors who had accepted a purpose are asked again when a new service appears in that purpose. Those who refused everything are not disturbed again. The renewal delay applies in every case.
- Before the first publication, the screen lists the "environment" fields to confirm (see "From test to production").

## Registry and proof sheet

- Tab Registry: one line per choice (date in UTC, identifier, action, source, version, hash, language, page, browser). Filters: identifier (first characters), action, source, version and period.
- CSV export for archiving: it follows the action and period filters (not the other filters). The file is plain, with headers and values in your back-office language (a version is named by its number and hash, a purpose or service by its name). There is no console command for this CSV. The export with technical headers is the PHP service `RegistryCsvExporter`, for developers.
- Finding a visitor: ask them for the identifier shown at the bottom of the "Manage my cookies" panel, type its first characters in the search, and open the proof sheet. The sheet shows their choice, the banner version they saw, and the hashes (a short code identifying the exact rendering served). It shows the close button only if it was active, the policy address of that version, the layout and the cookies of each service. Print it from the browser. All times are in UTC, and the local time is shown in brackets.
- Retention and options: retention duration and truncated IP address (off by default, /24 for IPv4 and /48 for IPv6). The option must be announced in your cookie policy, and it applies from the next publication.
- Retention duration: without a preset the module proposes 60 months, which is also the cap, so a higher value is refused. The France preset sets 25 months. No source read for this module gives a legal duration for proof of consent. The 25 months reuse the maximum the CNIL recommends for audience measurement data, which is another purpose (see [decision 006](decisions/006-proof-retention-25-months.md)). Choose a duration, announce it in your policy (the sample text below uses it) and schedule the purge. Purge and export read the duration of the published version, not of the draft.
- What the registry proves: it shows that the module received a declaration of choice from a browser. It cannot authenticate an anonymous visitor, because the consent identifier is a random UUID supplied by the browser or generated by the server. The recorded page path hides the secret segments of addresses (guest order tracking, opaque tokens).
- Purge: entries older than the retention duration are deleted (DELETE right, irreversible). The purge must run automatically: ask your developer to schedule `ccm:registry:purge` (`--dry-run` counts only). A red alert appears when it is more than 7 days late. On Thelia 3.3 with a worker on `scheduler_thelia`, a daily task at 03:17 already exists. Otherwise use a cron job such as `17 3 * * *`.
- Behind a reverse proxy or a CDN, your developer must declare the proxy in `framework.trusted_proxies`. Otherwise all visitors share the rate limit of the registry endpoint (120 requests per minute) and some choices can be rejected with a 429.

## Consent Mode (tab "Consent Mode")

Consent Mode v2 passes the visitor's choice to Google. There are two modes:

- Basic (recommended): no Google tag loads before consent.
- Advanced: Google tags load before the choice and send signals without cookies, even after a refusal (screen size, browser data). No authority has validated this mode, so have it validated by your legal adviser. Switching to it requires ticking a confirmation box, and saving is refused without it. A permanent red alert stays on the dashboard while the mode is active. Going back to basic removes the alert.

Advanced settings: defaults per region, advertising data redaction, URL passthrough, Microsoft Advertising.

Guardrail: a "granted" default (apart from `security_storage`) is refused for a European region (European Union, European Economic Area, United Kingdom, Switzerland) and for a line without a region, which applies worldwide. It would amount to storing a tracker without consent. A "granted" default stays possible for a region outside Europe (California, for example). See [decision 002](decisions/002-european-scope-and-retention-cap.md).

## Verification

In "Versions and verification", the module visits pages of your shop (home, category, product, cart or another page of your choice). It loads each page first before any choice, then as if the visitor had clicked "Reject all". It lists the third-party domains contacted and the readable cookies, with a verdict. Only pages of your own shop can be audited.

- Expected result: no third-party domain before consent. A domain "loaded outside the module (theme or hard-coded)", or the Google fonts loaded by the Flexy theme, is a defect of the theme. Your developer handles it, because the module cannot prevent it.
- Limits: `HttpOnly` cookies and the content inside third-party iframes are invisible. The Verification writes nothing to the registry.
- Report: "Open the report (printable)" opens a page with the summary first, then a verdict per domain ("managed service", "loaded outside the module (theme or hard-coded)", "theme fonts"). Print it to PDF to hand it to the client. "Download the report (JSON)" gives the same result as a file.
- First "Reject all" while an exempt measurement (Matomo...) runs: the page reloads once, on purpose, to stop the script that has already run.
- The Verification does not replace the manual checks (keyboard, screen reader, mobile) of the checklist below.

### Theme fonts

The Flexy theme loads its fonts from Google (`fonts.googleapis.com`, `fonts.gstatic.com`) as soon as the page opens. The visitor's IP address goes to Google without consent. The module cannot prevent it, because these requests come from the theme.
Ask your developer to host the fonts on the site (font files copied into the theme, a stylesheet that no longer points to Google), then run the Verification again. The verdict "theme fonts" should disappear.

## From test to production

1. On the test site: tab "Versions and verification", "Export the draft (JSON)". The export holds the draft, not the published version, so publish first if needed.
2. On production: "Import a configuration (JSON)" (CREATE right, the one that publishes, because a file can carry code). The import replaces the whole draft, including what is not published yet (the screen counts the unpublished changes and the server refuses the import unless you tick the confirmation), never the live version. A file over 1 MB is refused. Invalid addresses, invalid purpose codes, and services presented as coming from a module that does not declare them, block the write. Other errors are imported so that you can correct them in the back-office. Experimental services of the file are skipped while "Experimental services" is off.
3. The screen lists the environment fields: service identifiers (Google Analytics, Meta, Crisp...), policy address, regions. They come from the test site, so re-enter or confirm the production values one by one before publishing. Publication is refused until they are confirmed. For each field the test value is shown next to the input, and no box is pre-ticked.
4. Check that the policy page exists on the production site, then publish.

The same flow works from the console: `php Thelia ccm:config:export --output=ccm-config.json`, `php Thelia ccm:config:import ccm-config.json`, then `php Thelia ccm:config:publish --confirm-environment --comment="Go live"`. See the [README](../README.md#deploying-a-configuration-from-the-console).

## Rights by profile

Rights are set per profile in Configuration > Administration profiles, module "CookieConsentManager". An administrator without a profile has every right.
Each action needs one right. Rights do not include each other: to open the screens, a profile also needs VIEW. A profile that publishes typically has VIEW, UPDATE and CREATE. The dashboard shows the rights actually granted per profile.

| Action | Right needed |
|--------|:------------:|
| Browse all screens (the registry shows its settings and aggregated statistics, not its entries), preview the draft, run the Verification and download its report, export (JSON of the draft or of a version) | VIEW |
| List, search and open the registry entries (they hold personal data), export the registry (CSV), open a proof sheet | UPDATE |
| Edit the draft (services, purposes, texts, colours, Consent Mode, settings) | UPDATE |
| Import a preset | UPDATE |
| Write or change the code or the script address of a custom service, set or change a service parameter that is an address or a tag manager container (flagged "Runs on the storefront"), switch on experimental services | CREATE |
| Import a configuration | CREATE |
| Confirm environment fields | UPDATE |
| Restore a version as the draft | CREATE |
| Set retention and truncated IP | UPDATE |
| Publish a version (setup assistant included, last step) | CREATE |
| Purge the registry | DELETE |

Publishing is a sensitive right. It sends the configuration to every visitor, and that includes JavaScript: custom services with a `code` field and script services with a `script_url` serve code to all visitors of the shop. Whatever can bring such code into the draft therefore needs CREATE too: writing or changing the code or script address of a service, setting or changing a catalogue parameter that is itself an address or a host loaded as a script (the Matomo address, the custom domain of a Google tag, the library address of AT Internet, the container ID of Google Tag Manager... 39 parameters, flagged `executable` in the catalogue and "Runs on the storefront" in the lists of changes), importing a configuration, restoring a version, switching on experimental services. UPDATE alone edits everything else in the draft and cannot publish. Give CREATE only to people who may put code on every page. DELETE covers the registry purge only.

Read-only profile (VIEW without UPDATE): every screen can be browsed except the registry entries, which need UPDATE; fields are disabled, write buttons are hidden, and a strip "Read-only access" says so. Without VIEW, the menu entry "Cookie consent" does not appear.

## Sample text for the privacy policy

To reuse in your policy (section "Cookies and trackers"), adapted to your case. The registry text is also offered in the Registry tab.

> To prove the consent you give, we keep a record of your choices: a random identifier, the date, your choices, the banner version, the language, the page, your browser family and, if the option is on, a truncated IP address. These data are kept for the period set by the site (**retention period** to be stated) and are used only as proof of consent.
>
> You can change or withdraw your consent at any time through the "Manage my cookies" link at the bottom of every page. Your choice is remembered in a first-party cookie (`ccm_consent`) for 13 months at most; you are asked again after **6 months** (or the delay you set).

Complete it with the list of services used and their controllers, the lifetime of the cookies, and, if you turn it on, the truncated IP address.

## Checklist before going live

Replay it before each production release. Each line gives the action, then what to observe.

| # | Action | What to observe |
|---|--------|-----------------|
| 1 | Clean browser (private window), open the home page, Network and Cookies tabs of the browser tools | no request to a third-party service before a choice; only the site's technical cookies (session, cart, language, `ccm_consent` after a choice) |
| 2 | Click "Reject all" | one click, banner closed, nothing new in Network or Cookies |
| 3 | Compare "Accept" and "Reject" | same size, same colour, same weight |
| 4 | Look for "Manage my cookies" on the home page, a product page, the cart, the checkout | present and working everywhere |
| 5 | Accept, withdraw a consent, reload | the service's cookies are gone (except third-party cookies that cannot be erased, a message says so) |
| 6 | Mobile (375 px): add a product to the cart with the banner displayed | the button is reachable, the banner stays under 38% of the screen height |
| 7 | Keyboard only (Tab, Enter, Space, Escape), then a screen reader (NVDA or VoiceOver) | every choice possible, focus visible, banner announced |
| 8 | Verification, before then after "Reject all" | no third-party domain "before consent"; otherwise handle each domain |
| 9 | Ask your developer to search the theme for GTM, Google Analytics, pixels, embedded videos, Google fonts | nothing hard-coded; otherwise migrate. Google Analytics direct and GTM are never used together. Google fonts belong to the theme (see row 11 and "Theme fonts") |
| 10 | Tag Assistant on the home page, then on a second page after consent | `default` denied then `update` granted before any measurement; tags fire after `ccm_consent_ready` |
| 11 | Network tab on the home page | no request to `fonts.googleapis.com`: theme defect, see "Theme fonts" |
| 12 | Dashboard | no red alert |
| 13 | Cookie policy | linked from the banner (the link answers 200 and is not the terms page), module model used, retention duration and truncated IP option decided and announced |
| 14 | Publish, clear the cache, open a private window | the published version is visible; the alert "served rendering differs" is absent |

## When in doubt

- The banner does not appear: no version is published, or the module is disabled. There is no "Banner enabled" switch.
- Visitors no longer see the services after a long time: an expired choice (renewal delay reached) or one older than a "substantial change" publication suspends non-essential services, and the banner shows again. The services restart at the next answer.
- A service does not load after consent: check that it is active, published, and its identifier. Look at the Verification.
- A red alert does not go away: click "Fix", correct, then publish again.
- Theme or code adjustments: see the [integrator guide](integrator-guide.md).

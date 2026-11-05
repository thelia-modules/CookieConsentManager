# Guide intégrateur : thème, surcharge, API, tests

Pour le développeur qui branche CookieConsentManager sur un thème Thelia 3. Installation et premiers pas : `README.md` (en anglais). La référence complète, en anglais, est `docs/integrator-guide.md` (garde Twig, surcharge des gabarits et tableau des attributs, API JavaScript et Twig, migration GTM, module tiers, tests) ; formats partagés : `docs/runtime-contracts.md`.

## 1. Ce que le module injecte

| Hook Flexy | Contenu |
|------------|---------|
| `layout.head.top` | config publiée (`<script type="application/json" id="ccm-config">`), script inline (Consent Mode `default` puis `update`, file `ccmQueue`, événement `ccm_consent_ready`), `<link rel="stylesheet" id="ccm-style" href="/ccm/<condensat>.css">` (la feuille de style du module, un fichier nommé d'après son propre condensat), `<script defer src="/ccm/<version>-<condensat>.js">` |
| `layout.body.top` | bannière (`hidden`), modèle d'espace réservé, `<script type="application/json" id="ccm-services-meta">` (responsable et adresse de politique des services média, et libellés des finalités sous `@purposes`, lus par le moteur pour l'espace réservé), `<script type="application/json" id="ccm-messages">` (messages du moteur dans la langue du visiteur), bouton flottant (option) |
| `layout.footer.top` (3.0, 3.1) ou slot `footer_links` (3.2+) | lien « Gérer mes cookies » |

- Rien n'est injecté tant qu'aucune version n'est publiée (hors aperçu).
- Le moteur est servi par une route versionnée `GET /ccm/<version>-<condensat>.js`, en cache `immutable` ; aucun fichier n'est écrit dans `public/`.
- Un thème sans les hooks `layout.*` de Flexy n'affiche pas la bannière : reprendre `theme_hook('layout.head.top')` et `theme_hook('layout.body.top')` dans son `base.html.twig`. Page autonome sans JavaScript : `GET /ccm/preferences`.

## 2. Thème enfant Flexy

La surcharge d'un gabarit du module se fait dans le thème actif ou son parent. **Prérequis d'un thème enfant Flexy constatés sur Thelia 3.0.0 (Flexy 1.0.3)** ; ils relèvent de Thelia, pas du module : se reporter à la documentation Thelia de votre version, et les revérifier sur 3.1 à 3.3 (non rejoués).

1. `templates/frontOffice/<enfant>/template.xml` avec `<parent>flexy</parent>` et une section **`<authors>`** (nom, société, courriel, site). Sans elle, le cœur lève une `TypeError` (`TemplateValidator`) : site et console en erreur 500.
2. **Assets** : Flexy est servi par AssetMapper. Un thème enfant qui ne les fournit pas donne une erreur 500 (Tailwind, `assets/styles/app.css` introuvable ; entrée « app » introuvable sans `importmap.php`). Fournir `assets/` et `importmap.php` (copie ou liens symboliques vers ceux de Flexy). Avec ces liens (styles compris), la feuille Tailwind de Flexy est servie compilée au thème enfant (constaté en 3.1.6 : `/assets/frontOffice/flexy/styles/app-*.css`, `tailwindcss v4.3.0`). Les styles propres au thème enfant restent à compiler selon la documentation Thelia.
3. `components/`, `form/`, `blocks/` : liens vers ceux de Flexy si le thème enfant ne les redéfinit pas.
4. **Activation** : le thème actif se règle au back-office (réglage `active-front-template`). Si la variable d'environnement `ACTIVE_FRONT_TEMPLATE` est définie (`.env.local`, serveur), **elle prime sur la valeur en base** (constaté en 3.1.6) : changer alors la variable, puis vider le cache. Référence : `Thelia/Model/Config.php` du cœur, `getValue()` (lit `$_ENV[...]` d'abord, ligne 48) et `getEnvNameFor()` (lignes 21-27).

Ces prérequis concernent **le thème**. Les gabarits du module, eux, se surchargent un par un : le CSS du module est **autonome** et reste correct même si le thème enfant n'est pas stylé.

## 3. Surcharger un gabarit, pas à pas

1. Copier le gabarit d'origine (`templates/frontOffice/flexy/CookieConsentManager/banner.html.twig` du module) vers `templates/frontOffice/<enfant>/CookieConsentManager/banner.html.twig`. Seuls les gabarits modifiés sont à copier.
2. **Première ligne exactement** :

   ```twig
   {# ccm-templates:1 #}
   ```

   Deux-points, pas de barre oblique. `{# ccm-templates/1 #}` (le nom du contrat) n'est pas reconnu. Seuls des espaces peuvent précéder le marqueur.
3. Conserver tous les attributs `data-ccm-*` requis (tableau de `docs/integrator-guide.md`, section « Template variables and required attributes »). Le moteur ne lit jamais de classe CSS ; on peut ajouter ses propres classes (`acme-banner`) sans risque.
4. Utiliser les variables du gabarit (`texts`, `purposes`, `layout`, `colors`...). `ccm_contract` (`ccm-templates/1`) est une variable globale Twig.
5. Vider le cache (`php Thelia cache:clear`), recharger la boutique, puis ouvrir le tableau de bord du module : une surcharge donne l'information « template(s) overridden » ; un marqueur absent ou différent de `1` donne une alerte rouge.
6. **Republier** : les gabarits entrent dans le condensat servi. Après un déploiement de thème, l'alerte « rendu servi différent de la version publiée » se résout en republiant.
7. À chaque montée de version du module, comparer vos copies avec les gabarits d'origine : elles ne sont pas mises à jour automatiquement. Si le numéro de contrat change (`ccm-templates:2`), l'alerte rouge l'indique.

### Langues et gabarits copiés

Les gabarits n'ont plus de texte français ou anglais en dur : chaque libellé passe par le traducteur de Thelia, domaine `cookieconsentmanager.fo.flexy`, fichiers `I18n/frontOffice/flexy/<locale>.php` du module (fr_FR, en_US, de_DE, nl_NL, es_ES). Le domaine est nommé d'après le dossier du module, pas d'après le thème actif : un thème enfant l'utilise sans rien faire. Le français, l'anglais, l'allemand, le néerlandais et l'espagnol sont fournis ; pour une autre langue active, le message anglais est repris (le msgid est l'anglais).

Ce que cela change pour un thème qui a **copié** des gabarits :

1. Un gabarit copié avant cette version garde son « : » en dur : l'espacement du français (« Responsable : ») ne s'applique pas. Remplacer chaque « : » d'un libellé par `{{ texts.colon }}` (français : espace, deux-points, espace ; autres langues : deux-points, espace). Exemple : `{{ texts.controller }}{{ texts.colon }}{{ service.controller }}`.
2. Reprendre aussi les nouveaux éléments du gabarit d'origine (liste des finalités avant le texte, pied `ccm-foot`, phrase de mesure exemptée, lien sans JavaScript de l'espace réservé, `data-ccm-withdrawn` du panneau) : une copie plus ancienne reste valide, mais n'affiche pas ces informations.
3. Les textes éditables par l'administrateur (`texts.title`, `texts.body`, etc.) sont déjà résolus par le module : saisie dans la langue, sinon défaut traduit de la langue, sinon saisie en anglais, sinon défaut anglais. Ne pas réimplémenter ce repli dans le thème.
4. Les messages affichés par le moteur JavaScript (espace réservé, confirmations) viennent de `#ccm-messages`, émis avec le hook `layout.body.top`. Aucun gabarit à copier : un thème qui garde ce hook (voir section 1) les reçoit. Sans cet élément, le moteur retombe sur l'anglais.
5. Un nouveau libellé de gabarit se déclare dans `Front/FrontTexts.php` (`LABELS`) et dans les cinq fichiers de traduction (le test `TranslationCatalogueTest` vérifie que les clés et les `%paramètres%` sont identiques dans les cinq).

Règles à ne pas casser : « Tout accepter » et « Tout refuser » gardent le même composant, la même classe et les mêmes couleurs ; la bannière garde `role="dialog"`, un titre et un nom accessibles ; la modale piège le focus.

## 4. Conditionner du code au consentement

| Besoin | Outil |
|--------|-------|
| Charger un script tiers dans un gabarit | `<script type="text/plain" data-ccm-service="<clé>">` (ou `data-ccm-purpose`) |
| Exécuter du JavaScript à l'accord | `ccmQueue.push(() => CookieConsentManager.onConsent('<clé>', fn))` |
| Réagir à tout changement, retrait compris | `CookieConsentManager.onChange(fn)` ou événement `document` `ccm:consent` |
| Afficher un bloc seulement si accordé (rendu serveur) | `{% if ccm_has_consent('<clé>') %}` (derrière la garde, voir `docs/integrator-guide.md`) |
| Vidéo ou carte intégrée | `ccm_placeholder('<service>', {src, title, ...})` |
| Rouvrir les préférences | `ccm_open_link('Libellé')` ou tout élément portant `data-ccm-open` |
| Déclencher un choix par code | `CookieConsentManager.setConsent({services: {crisp: 1}})` (source `api` au registre ; réservé au code du site, jamais à la place d'un visiteur qui n'a pas choisi) |

Points d'attention :
- Le script du moteur est chargé en `defer` : n'utiliser `window.CookieConsentManager` qu'après `ccm:ready`, ou passer par `ccmQueue` (la file existe dès le `<head>`).
- `onConsent` n'est pas appelé au retrait : utiliser `onChange`.
- Une page qui appelle `ccm_has_consent` dépend du cookie du visiteur : pas de mise en cache partagée de cette page (Thelia 3.3 ne partage que les visiteurs sans cookie).
- GTM codé en dur dans le thème : procédure dans `docs/integrator-guide.md` (« Moving a hard-coded GTM »).
- **Garde Twig** si un gabarit du thème appelle `ccm_*` : voir `docs/integrator-guide.md` (sonde `include ... ignore missing`). Module désactivé sans garde : erreur 500 sur tout le site.

## Iframes remplacées par un encart

- Les iframes des services média sont remplacées automatiquement par un encart « Autoriser » (option « Bloquer automatiquement les iframes intégrées », activée par défaut), seulement quand le service est actif et publié. Cela couvre 41 des 94 services média du catalogue : ceux dont l'intégration est une iframe d'hôte identifiable. Les hôtes et chemins viennent des fragments (`Resources/catalogue/embed-rules.json`, régénéré par `node tools/catalogue/embed-rules.mjs`), plus les hôtes d'intégration de YouTube, Vimeo, Dailymotion et Google Maps. Un hôte seul ne suffit jamais sur un domaine partagé (`www.google.com` sert aussi reCAPTCHA) : chaque règle porte des préfixes de chemin, le plus long l'emporte.
- Les réponses d'un LiveComponent sont réécrites comme une page (le navigateur les fusionne dans la page : une iframe intacte contournerait le consentement). Les fragments ESI, les réponses HTML des contrôleurs et les pages des autres modules sont réécrits aussi. Les autres réponses AJAX ne sont pas touchées.
- Non réécrits : les réponses à une requête portant `X-Requested-With` et les iframes créées en JavaScript. Pour ceux-là, utiliser le gabarit de placeholder ou la file `ccmQueue`.
- Non réécrits côté serveur, avec la raison :
  - le service n'a pas d'iframe, il charge un script ou un widget (l'encart de son fragment s'applique une fois la page exécutée) : `addthis`, `addtoanyshare`, `adsense`, `adsensesearch`, `adsensesearchresult`, `aduptech_ads`, `calendly`, `clicmanager`, `criteo`, `datingaffiliationpopup`, `disqus`, `facebook`, `facebookcomment`, `facebookcustomerchat`, `facebooklikebox`, `fillout`, `googlemaps`, `googlepartners`, `gplus`, `gplusbadge`, `hcaptcha`, `intercomChat`, `kwanko`, `linkedin`, `m6meteo`, `pinterest`, `pipedrive`, `prelinker`, `pubdirecte`, `recaptcha`, `rumbletalk`, `seamlessaccess`, `shareaholic`, `shareasale`, `sharethis`, `statcounter`, `tidycal`, `tiktokvideo`, `trustpilot`, `twitter`, `twitterembed`, `twittertimeline`, `twitterwidgetsapi`, `vshop`, `xandrconversion`, `xandrsegment` ;
  - l'adresse de l'iframe est saisie par l'éditeur, aucun hôte n'est connu d'avance : `gallica`, `geoportail`, `googlemapsembed`, `helloasso`, `iframe`, `openstreetmap`, `podcloud`. Pour ceux-là, envelopper l'iframe avec `ccm_placeholder('<clé>', {src: ..., title: ...})` dans le gabarit, ou étiqueter l'iframe à la main.

## 5. Module tiers

Déclarer un service par tag `ccm.service` dans `configureServices` : exemple complet dans `docs/integrator-guide.md` (section « Declaring a service from another module »). À retenir pour 3.0 à 3.2 : le module tiers doit fournir un `Config/config.xml` (même vide).

Dans le tag, `controller` et `policy_url` sont lus par le panneau et par l'espace réservé ; chaque cookie de `cookies` peut porter `duration_days` (0 = cookie de session), affiché « nom (durée) ». Un service déclaré par un module n'est jamais éligible à l'exemption de consentement.

Pour tester : `php Thelia module:refresh` puis, au back-office, « Finalités et services » : la ligne apparaît sous « Services proposés par d'autres modules », inactive et absente de la configuration publiée tant qu'un administrateur ne l'a pas activée et publiée. En console : `php Thelia ccm:services:sync`.

## 5 bis. Ajouter des alertes et des règles de validation depuis un module

Les alertes du tableau de bord et la validation du brouillon sont construites à partir de services tagués : un module tiers y ajoute les siennes sans modifier celui-ci. Dans un module dont les services sont autoconfigurés (le cas par défaut de Thelia 3), implémenter une interface suffit.

- `CookieConsentManager\Admin\AlertProvider\AlertProvider` (tag `ccm.alert_provider`) : `alerts(array $draft): array` renvoie des `Alert` à partir du brouillon normalisé. Le tableau de bord trie par gravité (danger, avertissement, info) et garde l'ordre des fournisseurs à gravité égale. Le `code` d'une alerte doit rester stable. Exemple complet dans `docs/integrator-guide.md` (section « Adding alerts and validation rules from another module »).
- `CookieConsentManager\Validation\SectionValidator` (tag `ccm.section_validator`) : `validate(array $draft): array` renvoie des `ValidationIssue` pour une partie du brouillon. Une issue de gravité `error` bloque l'enregistrement et la publication ; un `warning` ne bloque pas.
- `priority()` (statique) donne l'ordre, le plus haut d'abord ; les fournisseurs et sections du module vont de 100 à 1000. Un attribut `priority` posé sur le tag l'emporte.
- Les messages sont ceux du module tiers : à traduire chez lui.

## 6. Politique de sécurité de contenu (CSP)

Ce que le module émet, à autoriser :
- script inline en tête de page (pas de nonce en v1) : `script-src 'unsafe-inline'`, ou bien une empreinte (hash) du script inline, **qui change à chaque version du module** ;
- script du moteur : même origine (`script-src 'self'`) ;
- enregistrement des choix : `POST /ccm/consent` en même origine (`connect-src 'self'`) ;
- le moteur n'emploie ni `eval` ni `unsafe-eval` ;
- les scripts restaurés (les scripts `text/plain` que vous étiquetez et le code des services `custom`) sont des scripts inline recréés par le moteur : sous une CSP stricte sans `unsafe-inline`, poser un `nonce` sur la balise d'origine (le moteur le recopie) ou utiliser `strict-dynamic` côté site ;
- les services activés chargent leurs propres domaines : les ajouter à `script-src`, `connect-src`, `img-src`, `frame-src` selon le service (les domaines contactés par un service sont dans le champ `domains` de `Resources/catalogue/catalogue.json` (renseigné pour les services vérifiés, sauf Matomo, auto-hébergé) ; la Vérification les révèle aussi). Les cookies et les iframes tierces restent bloqués par le module tant que le consentement manque.

L'endpoint refuse (403 `forbidden_origin`) un POST dont `Origin` n'est pas l'hôte courant, dont `Sec-Fetch-Site` n'est ni `same-origin` ni `none`, ou qui n'a ni `Origin` ni `Sec-Fetch-Site` : ne pas le proxifier vers un autre domaine, et envoyer ces en-têtes dans un test `curl`.

Les statistiques sont indicatives et l'endpoint n'est pas authentifié : un client HTTP qui forge des POST avec un `consent_id` neuf gonfle les premiers choix, dans la limite du limiteur par adresse. Le registre trace la source de chaque choix.

## 7. Tests

Le module est testé dans un site Thelia 3 de recette (il a besoin du cœur et des modèles Propel générés).

| Quoi | Commande |
|------|----------|
| JavaScript (moteur, couche de compatibilité, script servi) | `npm install` puis `npm run test:js` à la racine du module (`node --test tests/js/`). Le serveur sert `assets/js/*.min.js` : après toute modification de `compat.js`, `engine.js` ou `head.js`, lancer `npm run build` et committer les fichiers minifiés (un test échoue s'ils sont périmés). Après une modification des fragments du catalogue, `node tools/catalogue/embed-rules.mjs` régénère les règles d'iframes |
| PHP (unitaires et intégration) | dans le conteneur DDEV d'un site, base de test : `ddev exec 'cd /var/www/html && DATABASE_NAME=test <chemin>/vendor/bin/phpunit -c <chemin du module>/phpunit.xml.dist'` ; l'amorçage refuse toute base dont le nom ne contient pas `test` |
| PHPStan (niveau 6) | `CCM_SITE_ROOT=$PWD vendor/bin/phpstan analyse -c local/modules/CookieConsentManager/phpstan.neon.dist --memory-limit=1G` depuis la racine du site (commande d'en-tête de `phpstan.neon.dist`) ; style : `php-cs-fixer check --config=.php-cs-fixer.dist.php` |
| Régénérer le catalogue (mainteneur) | `npm run catalogue:build`, voir `docs/maintaining-the-catalogue.md` (en anglais) |

Contrôles navigateur à rejouer sur un thème intégré :
1. Navigateur vierge : aucune requête vers un domaine de service avant choix ; `default` denied dans `dataLayer`.
2. « Tout refuser » : aucune nouvelle requête, seul `ccm_consent` est posé.
3. Un script `text/plain` balisé ne s'exécute qu'après accord, y compris au rechargement.
4. Gabarit surchargé : marqueur `{# ccm-templates:1 #}` présent, attributs `data-ccm-*` présents, alerte rouge absente du tableau de bord.
5. Module désactivé puis réactivé : page 200 grâce à la garde, registre intact.
6. Mobile 375 px : la bannière ne couvre pas le bouton « Ajouter au panier » et reste sous 40 % de la hauteur.

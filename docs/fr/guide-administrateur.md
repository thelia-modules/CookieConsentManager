# Guide administrateur : gérer le consentement aux cookies

Ce module aide à recueillir et à prouver le consentement. Il ne rend pas un site conforme à lui seul et ne constitue pas un conseil juridique (en anglais : « This module helps you collect and prove consent. It does not make a website compliant by itself and is not legal advice. »).

Le guide en anglais, `docs/administrator-guide.md`, est la référence ; cette version française est celle de l'aide du back-office.

Pour le marchand et le chef de projet, sans connaissance technique. Le module affiche une bannière qui recueille le choix des visiteurs, garde la preuve de ce choix et bloque les services (statistiques, publicité, vidéos, chat) tant qu'ils ne l'ont pas accepté.

Où le trouver : back-office Thelia, menu du haut, **Consentement aux cookies**. Sept onglets : Tableau de bord, Apparence et textes, Finalités et services, Consent Mode, Préréglages, Registre, Versions et vérification. Un bandeau en haut indique la version en ligne et si votre brouillon a de l'avance sur elle.

## Le principe : brouillon, puis publication

- Tout ce que vous modifiez va dans le **brouillon**. Le brouillon n'est jamais montré aux visiteurs.
- **Publier** crée une version numérotée qui n'est plus modifiable et la met en ligne. Tant qu'aucune version n'est publiée, **la bannière n'apparaît pas**.
- Chaque consentement est enregistré avec le numéro de version que le visiteur a vue : c'est ce qui permet de le prouver.
- Vous pouvez toujours revenir en arrière : « Restaurer comme brouillon » reprend une ancienne version dans le brouillon, qu'il faut ensuite publier.

## Tableau de bord

- **État** : version en ligne (date, auteur, code d'empreinte), brouillon à jour ou non.
- **Alertes** : rouge = à corriger avant la mise en ligne, orange = à traiter, bleu = information. Chaque alerte actionnable a un bouton **Corriger**. Une alerte n'empêche pas la publication : seuls les refus décrits dans « Refus et alertes » plus bas la bloquent. Exemples : page de politique de cookies absente ou qui ne répond pas, langue active sans texte complet, purge du registre en retard, module émetteur de traceurs actif, gabarit de bannière du thème obsolète, « rendu servi différent de la version publiée » (publier de nouveau).
- **Premiers choix** : pour chaque visiteur, seule sa première décision compte. Vous voyez le nombre de premiers choix sur 7, 30 ou 90 jours et la répartition accepter tout, refuser tout, personnalisé. Pour savoir combien de visiteurs refusent, c'est ici.
- **Réglages généraux** : croix de fermeture (continuer sans accepter, équivaut à refuser), bouton flottant « Gérer mes cookies », rechargement de la page quand un service est retiré, blocage automatique des vidéos et cartes intégrées, services expérimentaux, durée avant de redemander (1 à 13 mois, 6 par défaut ; au-delà de 6 mois, une alerte orange rappelle la recommandation de la CNIL : « une durée de six mois, tant pour le consentement que le refus, est en général appropriée », page de mise en conformité citée plus haut), adresse de la page de politique de cookies (une par langue) et titre de cette page. Il n'y a pas d'interrupteur « Bannière activée » : la bannière apparaît dès qu'une version est publiée et disparaît si le module est désactivé.

## Assistant de démarrage

Quatre étapes, à faire dans l'ordre pour une première mise en place :

1. Cocher les services que vous utilisez (Google Analytics, Meta, YouTube, Crisp...).
2. Saisir leurs identifiants (ex. identifiant de mesure GA4 de la forme `G-XXXXXXXXXX`, visible dans Google Analytics > Administration > Flux de données). Un identifiant au mauvais format est refusé avec un message.
3. Renseigner la page de politique de confidentialité (voir plus bas le texte type). Cette page se crée dans le menu **Dossiers et Pages** du back-office Thelia ; le module en fournit un modèle (voir « Page de politique de cookies »). Son adresse se saisit ensuite dans les réglages généraux.
4. Ouvrir l'**aperçu**, puis **publier**.

## Services (onglet « Finalités et services »)

- Un service appartient à une **finalité** : mesure d'audience, publicité, réseaux sociaux, contenus intégrés (vidéos, cartes), support, personnalisation. Une finalité sans service actif n'est pas montrée aux visiteurs. « Essentiel » (indispensable au site) est implicite et ne demande pas de consentement.
- **Catalogue** : les services « vérifiés » sont testés et documentés ; les services « importés » sont expérimentaux et masqués tant que « Services expérimentaux » est éteint au tableau de bord. Utilisez le filtre par niveau, catégorie ou recherche.
- Pour une vidéo, la fiche du service propose le **code HTML à coller** dans la page (bouton Copier). Les iframes de YouTube, Vimeo, Dailymotion, des cartes Google et de la plupart des autres services média déjà présentes dans vos pages sont remplacées automatiquement par un encart « Autoriser » tant que le service est actif et publié (la liste des services qui ne le sont pas est dans le guide intégrateur).
- **Services proposés par d'autres modules** : un module (paiement, par exemple) peut déclarer ses propres cookies. Ils restent **inactifs** tant que vous ne les activez pas et ne publiez pas. Un service « strictement nécessaire » reste signalé en permanence avec sa justification.
- **Services personnalisés** : un service qui n'est pas au catalogue, avec une adresse de script, votre propre code (droit CREATE requis pour écrire ou changer le code ou l'adresse du script, conservé dans la version) ou une simple déclaration (service chargé par Google Tag Manager).
- **Finalité d'un service du catalogue** : elle est en lecture seule. Un service du catalogue reste dans la finalité prévue pour lui ; seul un service personnalisé choisit la sienne.
- **Mesure d'audience exemptée** : l'exemption de consentement est limitée à une liste d'outils, affichée avec une description : Matomo auto-hébergé, Wysistat en mode exempté, Plausible, Simple Analytics. Aucun des quatre outils n'a été testé sur un compte réel : la liste repose sur une lecture du code que chacun exécute. La page de la CNIL sur la mesure d'audience (<https://www.cnil.fr/fr/cookies-solutions-pour-les-outils-de-mesure-daudience>, lue le 2026-11-04) ne nomme aucun produit : elle pose des conditions (mesure de sa seule audience, statistiques anonymes, pas de recoupement) et recommande un traceur de 13 mois au plus et une conservation de 25 mois au plus. Pour chacun, cochez les conditions que vous confirmez (elles se règlent dans l'outil lui-même, le module ne les voit pas) : la condition manquante est nommée avec une aide. Google Analytics, les services personnalisés et ceux des autres modules ne sont jamais éligibles. Un outil déjà exempté qui sort de la liste reste affiché pour que vous puissiez le retirer. Si l'un d'eux est actif, la bannière l'annonce au premier niveau, avec un lien pour s'y opposer.

### Responsable et durées de cookies

- **Responsable** : chaque service du catalogue a un champ « Responsable », l'entité juridique que le visiteur lit dans le panneau (par exemple « Google Ireland Limited »). Il est prérempli pour 18 des 23 services vérifiés, d'après la politique officielle de l'éditeur. Il reste vide pour Matomo (si vous l'hébergez, le responsable est vous), Matomo Tag Manager, Hotjar, Brevo et Zendesk Chat : aucune source officielle claire. Vide, le visiteur lit le nom du produit à la place ; une alerte orange vous le signale, et sous un préréglage la publication est refusée.
- **Durées des cookies** : la fiche du service a un bloc « Durées des cookies », un champ par cookie, en jours (0 pour un cookie de session, 7 300 jours au plus). Le panneau et la page sans JavaScript affichent « nom (durée) ».
- Les durées sont fournies pour Google (Analytics, Ads, Tag Manager), YouTube et Matomo, d'après la documentation officielle de leur éditeur. **Exception YouTube** : `VISITOR_PRIVACY_METADATA`, que le lecteur dépose, n'est documenté nulle part par Google. Le module le déclare sans durée : sous le préréglage France, YouTube actif refuse la publication tant que vous n'avez pas saisi sa durée (fiche du service, bloc « Durées des cookies »). Saisissez 180 jours, la valeur relevée dans un navigateur le 2026-11-01, et gardez à l'esprit qu'elle est mesurée, pas annoncée par Google. Pour les autres services, **le module n'affiche aucune durée tant que vous n'en saisissez pas** : une durée sans source officielle n'est pas inventée. Google Tag Manager ne pose aucun cookie lui-même : le module ne déclare que `_ga`, `_ga_*` et `_gcl_au` ; Google Analytics 4 (`gtag`) ne déclare plus `_gcl_au`, cookie publicitaire que GA4 ne dépose pas tant qu'aucune finalité publicitaire n'est active ; les cookies des autres balises de votre conteneur, vous les déclarez vous-même dans un service personnalisé, avec leur durée.
- **Cas Meta Pixel** : aucune page officielle de Meta consultée ne donne la durée de `_fbp`, `_fbc` et `fr`. Saisissez-les d'après la documentation de Meta que vous avez sous les yeux. Sous le préréglage France rattaché à la langue principale, la publication est refusée dès qu'**un seul** cookie d'un service actif n'a pas de durée connue (catalogue, saisie, ou durée d'un service personnalisé) : saisissez la durée de ce cookie sur le service, ou désactivez-le. Le message nomme le service et les seuls cookies sans durée.

## Page de politique de cookies

Sous le préréglage France, le lien « Politique de cookies » de la bannière doit mener à une page dédiée aux cookies, pas aux conditions générales.

1. Publiez d'abord (le modèle est écrit d'après la **version publiée**).
2. Au tableau de bord, sous le champ de l'adresse de la politique, cliquez sur « Générer un modèle de page de politique de cookies », puis choisissez la langue.
3. Copiez le texte dans la description d'une page (menu **Dossiers et Pages** de Thelia), mettez-la en ligne.
4. Saisissez son adresse dans le champ de la langue correspondante, puis publiez.

Le modèle reprend le texte qui ne change pas : l'éditeur du site (nom, adresse et courriel lus dans **Configuration > Boutique** de Thelia ; une alerte signale ce qui manque), les rôles de chacun (avec la responsabilité conjointe pour la collecte et la transmission quand un service comme Meta Pixel ou un module social l'impose), la durée du choix du visiteur, la conservation des preuves et les droits des personnes. La liste des services, des cookies, de leurs durées et des responsables n'y est pas recopiée : un lien « Liste à jour des services et des cookies » mène à la page `/ccm/preferences`, toujours alignée sur la version publiée. Vous n'avez donc pas à mettre la page à jour quand vous changez de services.

Sous le préréglage France rattaché à la langue principale, **chaque langue active** doit avoir sa page : faites une page par langue (le modèle existe dans les cinq langues du module ; pour une autre langue, ses phrases fixes sont en anglais, à traduire). Une langue sans adresse propre qui retombe sur les conditions générales refuse la publication. Relisez le modèle avant de le mettre en ligne.

Astuce : l'éditeur de texte du back-office Thelia enregistre un résumé et un post-scriptum vides sous la forme `<p></p>`, et certains thèmes l'affichent tel quel. Si ces deux lignes apparaissent sur la page, videz-les en base ou adaptez le gabarit du thème.

## Textes et apparence (onglet « Apparence et textes »)

- **Mise en page** : bandeau (bas de page) ou modale. Sur mobile, la modale devient une feuille basse limitée à 40 % de la hauteur de l'écran.
- **Couleurs** : trois couleurs. Un contrôle de contraste bloque l'enregistrement d'une couleur trop claire (texte 4,5:1, bouton 3:1) et propose une teinte voisine conforme.
- **Textes** : un jeu par langue active (voir « Langues » plus bas). Le bouton « Refuser » garde toujours la même taille et la même couleur que « Accepter » : c'est une position de la CNIL, à traiter comme une recommandation : ses lignes directrices écrivent que « refuser les traceurs doit être aussi aisé que de les accepter » (<https://www.cnil.fr/fr/cookies-et-autres-traceurs/regles/cookies/lignes-directrices-modificatives-et-recommandation>, lu le 2026-11-04) et sa page de mise en conformité donne l'exemple de deux boutons « présentés au même niveau et sur le même format » (<https://www.cnil.fr/fr/cookies-et-traceurs-comment-mettre-mon-site-web-en-conformite>, lu le 2026-11-04). Un écart de longueur entre les deux libellés ou une formule de consentement par la simple navigation (« en poursuivant votre navigation... ») lève une alerte rouge. Chaque texte a une longueur maximale, et un libellé de bouton vide est refusé.
- Enregistrez les modifications dans le brouillon, puis publiez. Les couleurs et les textes s'enregistrent d'un seul bouton.

## Langues

- Le module est fourni en **français, anglais, allemand, néerlandais et espagnol** : bannière, panneau, messages de la bannière et back-office (dans la langue du profil de l'administrateur). Les langues du site que vous activez dans Thelia apparaissent dans l'écran des textes et dans celui des finalités.
- Pour chaque texte, le visiteur voit dans l'ordre : ce que vous avez saisi dans sa langue, sinon le texte par défaut du module dans sa langue, sinon ce que vous avez saisi en anglais, sinon le texte par défaut anglais. Une langue fournie ne mélange donc jamais deux langues.
- Un brouillon neuf ne contient que le texte anglais. Les quatre autres langues affichent le texte par défaut traduit tant que vous ne saisissez rien. « Rétablir les textes par défaut » supprime votre saisie. Un texte déjà saisi ne suit pas un changement futur du texte par défaut.
- **Une langue non fournie** (l'italien, par exemple) : tant que vous n'avez rien saisi, l'anglais s'affiche et une alerte rouge le signale. Même quand vous saisissez les textes modifiables, les phrases fixes du module (liste des finalités, façon de refuser, mention des partenaires, durée du choix, messages du panneau) restent en anglais : le module ne peut pas les traduire pour vous. L'alerte « traduction incomplète » le rappelle tant qu'il manque un texte ou l'intitulé d'une finalité.
- Le titre de la page de politique est unique : il suit la langue principale du site.
- Les traductions allemande, néerlandaise et espagnole n'ont pas été relues par un locuteur natif : faites-les relire avant une mise en ligne.

## Préréglages (onglet « Préréglages »)

Un préréglage est un jeu de textes et de réglages prêt à l'emploi pour un pays. Le module en fournit un : **France (CNIL)**.

1. Choisir le préréglage et la **langue cible** (parmi les langues actives).
2. Lire l'**aperçu avant / après** : rien n'est modifié tant que vous n'avez pas cliqué sur **Importer** (droit UPDATE).
3. Importer, puis publier. L'import écrit dans le brouillon ; il est refusé si une section touchée contient une erreur bloquante.

- Ce que l'import applique à la langue cible : textes du premier niveau, intitulés et descriptions des finalités, et le **rattachement** de la langue au préréglage.
- Ce qui vaut pour tout le site (réglages généraux, Consent Mode, conservation du registre, mesure exemptée, titre de la page de politique) n'est appliqué **que si la langue cible est la langue principale du site**. Sinon l'aperçu l'indique (« réglages communs laissés ») et seuls les textes sont importés. Pour un site dont la langue principale est l'anglais, importer le préréglage français sur « fr » ne change donc pas la conservation du registre.
- Un préréglage ne touche jamais aux services, aux couleurs ni aux adresses.
- **Préréglage France** : bandeau avec croix « Continuer sans accepter », pas de bouton flottant (le retrait passe par le lien « Gérer mes cookies » du pied de page), rechargement au retrait, blocage des contenus intégrés, redemande à 6 mois, Consent Mode basique avec masquage des données publicitaires, conservation des preuves de 25 mois, adresse IP non conservée. Les textes décrivent les finalités et disent que refuser est aussi simple qu'accepter.
- Un réglage modifié à la main après l'import reste « rattaché » au préréglage : le rattachement ne mesure pas l'écart, ce sont les alertes qui le font (section suivante).

### Sous un préréglage : contrôles supplémentaires

| Situation | Effet |
|-----------|-------|
| Un réglage commun est moins protecteur que celui du préréglage de la langue principale (redemande, rechargement au retrait, blocage des contenus intégrés, mode Consent Mode, masquage des données publicitaires, passage d'URL, conservation, adresse IP) | Alerte rouge qui nomme le réglage et la valeur du préréglage. Ne bloque pas. |
| Deux langues sont rattachées à des préréglages qui se contredisent sur un réglage commun | Alerte orange |
| Un service actif du catalogue n'a pas de responsable (n'importe quel préréglage rattaché) | Publication refusée |
| Préréglage France : la langue française n'a pas d'adresse de politique de cookies, ou cette adresse ressemble à une page de conditions générales ou de mentions légales | Publication refusée |
| Une adresse de politique ne répond pas 200 | Alerte rouge |
| Un cookie d'un service actif n'a pas de durée connue (préréglage d'une autre langue que la principale) | Alerte rouge |

La détection des conditions générales repose sur les mots de l'adresse (`cgv`, `terms`, `conditions`, `mentions`, `legal`...) en l'absence de `cookie` ou `traceur`. Une page de conditions générales nommée autrement passe : vérifiez vous-même la page.

## Refus et alertes

Deux niveaux, à ne pas confondre :

- **Refus** : un message rouge au moment de l'enregistrement ou de la publication, et rien n'est écrit. Il porte sur ce qui se détecte sans ambiguïté : défaut Consent Mode « accordé » pour une région européenne ou pour toutes les régions, mesure exemptée sur un outil qui n'est pas dans la liste admise, changement de finalité d'un service du catalogue, conservation du registre au-delà de 60 mois, libellé vide, adresse invalide (une adresse de script en `http://` est refusée), couleur trop claire, ainsi que les contrôles du préréglage ci-dessus.
- **Alerte rouge ou orange** au tableau de bord : le rédactionnel et les réglages à surveiller. Elle n'empêche pas de publier, mais elle doit être traitée avant la mise en ligne : formule de consentement implicite, écart entre Refuser et Accepter, langue incomplète, Consent Mode avancé, réglage moins protecteur que le préréglage.

## Aperçu

« Aperçu du brouillon sur la boutique » ouvre votre vraie boutique avec le brouillon : la bannière s'affiche avec vos services. Rien n'est enregistré au registre. Un bandeau « Aperçu » signale le mode, et aucun service n'est réellement chargé : pour vérifier les cookies, utilisez la version publiée.

## Publication et « changement substantiel »

- Onglet « Versions et vérification », **Publier** (droit CREATE). Ajoutez un commentaire pour vous y retrouver. La publication vide le cache partagé de la boutique (Thelia 3.3). Sur une boutique servie par plusieurs serveurs qui ont chacun leur cache applicatif, une publication peut mettre jusqu'à 300 secondes à atteindre les autres serveurs.
- **Changement substantiel** : à cocher quand vous changez ce qui a été annoncé au visiteur (textes de la bannière, responsable d'un traitement, finalité). La bannière est alors **réaffichée à tous**, même à ceux qui avaient déjà choisi. Sans cette case, seuls les visiteurs qui avaient accepté une finalité reçoivent à nouveau la question quand un **nouveau service** apparaît dans cette finalité ; ceux qui avaient tout refusé ne sont pas redérangés. La durée de redemande (6 mois par défaut) s'applique dans tous les cas.
- Avant la première publication, l'écran liste les champs « d'environnement » à confirmer (voir Passage recette vers production).

## Registre et fiche de preuve

- Onglet **Registre** : une ligne par choix (date en UTC, identifiant, action, source, version, langue, page, navigateur). Filtres par identifiant, action, source, version et période. **Exporter en CSV** pour archivage : le fichier est **en clair**, avec des en-têtes et des valeurs dans la langue de votre back-office (une version est désignée par son numéro et son empreinte, une finalité ou un service par son nom). Il n'existe pas de commande console pour ce CSV ; l'export à en-têtes techniques est celui du service PHP `RegistryCsvExporter`, réservé aux développeurs.
- **Retrouver un visiteur** : demandez-lui l'identifiant affiché en bas du panneau « Gérer mes cookies », saisissez ses premiers caractères dans la recherche, ouvrez la **fiche de preuve** : son choix, la version de bannière qu'il a vue, les empreintes (code court qui identifie le rendu exact servi). La fiche montre la croix de fermeture seulement si elle était active, l'adresse de politique de cette version, la mise en page et les cookies de chaque service. Imprimez-la depuis le navigateur. Toutes les heures sont en **UTC** (en France, ajouter 1 h en hiver, 2 h en été) ; l'heure locale est affichée entre parenthèses.
- **Conservation et options** : durée de conservation, adresse IP tronquée (désactivée par défaut). L'option **doit être annoncée dans votre politique de cookies** ; elle s'applique à partir de la publication suivante.
- **Durée de conservation** : sans préréglage, le module propose **60 mois**, qui est aussi le **plafond** : une valeur plus haute est refusée. Le préréglage France la fixe à **25 mois**. Aucune source lue pour ce module ne donne de durée légale pour la preuve du consentement. Les 25 mois reprennent le maximum que la CNIL recommande pour les données de mesure d'audience (<https://www.cnil.fr/fr/cookies-solutions-pour-les-outils-de-mesure-daudience>, lu le 2026-11-04), qui est une autre finalité : c'est un choix du module, pas une règle. Ils couvrent la durée de vie du dernier choix (13 mois au plus) puis environ un an de délai de contestation. Choisissez une durée, annoncez-la dans votre politique (le texte type ci-dessous la reprend) et planifiez la purge. La purge et l'export lisent la durée de la **version publiée**, pas celle du brouillon.
- **Valeur de la preuve** : le registre atteste que le module a reçu une déclaration de choix depuis un navigateur. Il n'authentifie pas son auteur : il ne prouve pas qui a cliqué (l'identifiant est un UUID fourni par le navigateur ou généré par le serveur, rien n'authentifie un visiteur anonyme). Le chemin de page enregistré masque les segments secrets des adresses (suivi de commande invité, jetons).
- **Purge** : les entrées plus anciennes que la durée de conservation sont supprimées (droit DELETE, irréversible). La purge doit tourner automatiquement : demandez à votre développeur de planifier `ccm:registry:purge` (derrière un proxy ou un CDN, il déclare aussi le proxy dans `framework.trusted_proxies`, sinon tous les visiteurs partagent la limite de 120 requêtes par minute de l'enregistrement) ; une alerte rouge apparaît si elle est en retard de plus de 7 jours.

## Onglet « Consent Mode »

Active la communication du choix à Google (Consent Mode v2). Deux modes :
- **Basique** (recommandé) : aucune balise Google ne se charge avant le consentement.
- **Avancé** : les balises Google se chargent avant le choix et envoient des signaux sans cookies, même après un refus (taille d'écran, données du navigateur). Aucune autorité n'a validé ce mode : à faire valider par votre juriste. Pour y passer, il faut **cocher une case de confirmation** ; sans elle, l'enregistrement est refusé. Une **alerte rouge permanente** reste au tableau de bord tant que ce mode est actif. Revenir au basique supprime l'alerte.

Réglages avancés : défauts par région, masquage des données publicitaires, passage d'URL, Microsoft Advertising.

Garde-fou : un défaut « accordé » (hors `security_storage`) est **refusé** pour une région d'Europe (Union européenne, Espace économique européen, Royaume-Uni, Suisse) ou pour une ligne sans région, qui vaut pour le monde entier : il équivaudrait à déposer un traceur sans consentement. Un défaut « accordé » reste possible pour une région hors d'Europe (par exemple la Californie).

## Vérification

Dans « Versions et vérification » : le module visite des pages de votre boutique (accueil, catégorie, produit, panier ou autre page de votre choix), d'abord avant tout choix, puis comme si le visiteur avait cliqué « Tout refuser ». Il liste les domaines tiers contactés et les cookies lisibles, avec un verdict.
- Le résultat attendu : aucun domaine tiers avant consentement. Un domaine « chargé hors module (thème ou code en dur) », ou les polices Google chargées par le thème Flexy, est un défaut **du thème** : à traiter par votre développeur (le module ne peut pas l'empêcher).
- Limites : cookies `HttpOnly` et contenus internes aux iframes tierces invisibles ; la Vérification n'écrit rien au registre.
- « Télécharger le rapport » ouvre une page imprimable : synthèse en tête, puis un verdict par domaine (« service géré », « chargé hors module (thème ou code en dur) », « polices du thème »). Imprimez-la en PDF pour la remettre au client.
- Premier « Tout refuser » alors qu'une mesure exemptée (Matomo...) tourne : la page se recharge une fois, c'est voulu, pour arrêter le script déjà exécuté.
- La Vérification ne remplace pas les contrôles manuels (clavier, lecteur d'écran, mobile) de la liste de contrôle plus bas.

### Polices du thème

Le thème Flexy charge ses polices depuis Google (`fonts.googleapis.com`, `fonts.gstatic.com`) dès l'ouverture de la page : l'adresse IP du visiteur part chez Google sans consentement. Le module ne peut pas l'empêcher, car ces requêtes viennent du thème. Demandez à votre développeur d'héberger les polices sur le site (fichiers de police copiés dans le thème, feuille de style qui ne pointe plus vers Google), puis relancez la Vérification : le verdict « polices du thème » doit disparaître.

## Passage recette vers production

1. Sur la recette : onglet « Versions et vérification », **Exporter le brouillon (JSON)**. L'export contient le **brouillon**, pas la version publiée : publiez d'abord si besoin.
2. Sur la production : **Importer une configuration (JSON)** (droit CREATE, celui qui publie, car un fichier peut porter du code). L'import remplace le brouillon, jamais la version en ligne. Un fichier de plus de 1 Mo est refusé, de même qu'un service présenté comme venant d'un module qui ne le déclare pas.
3. L'écran liste les **champs d'environnement** : identifiants de services (Google Analytics, Meta, Crisp...), adresse de la politique, régions. Ils viennent du site de recette : **ressaisissez ou confirmez les valeurs de la production** une à une avant de publier. La publication est refusée tant qu'ils ne sont pas confirmés. Pour chaque champ, la valeur de recette est affichée à côté de la zone de saisie ; aucune case n'est précochée.
4. Vérifier que la page de politique existe sur le site de production, puis publier.

## Droits par profil

Les droits se règlent par profil dans Configuration, Profils d'administrateurs, module « CookieConsentManager ». Un administrateur sans profil dispose de tous les droits.

| Action | VIEW | UPDATE | CREATE | DELETE |
|--------|:----:|:------:|:------:|:------:|
| Consulter tous les écrans, la liste du registre, les versions | oui | oui | oui | oui |
| Exporter (JSON du brouillon ou d'une version) | oui | oui | oui | oui |
| Exporter le registre (CSV), ouvrir une fiche de preuve | non | oui | non | non |
| Aperçu du brouillon | oui | oui | oui | oui |
| Lancer la Vérification et télécharger le rapport | oui | oui | oui | oui |
| Modifier le brouillon (services, finalités, textes, couleurs, Consent Mode, réglages) | non | oui | non | non |
| Importer un préréglage | non | oui | non | non |
| Écrire ou changer le code ou l'adresse du script d'un service personnalisé, activer les services expérimentaux | non | non | oui | non |
| Importer une configuration | non | non | oui | non |
| Confirmer les champs d'environnement | non | oui | non | non |
| Restaurer une version comme brouillon | non | non | oui | non |
| Régler conservation et IP tronquée | non | oui | non | non |
| Publier une version (assistant compris, dernière étape) | non | non | oui | non |
| Purger le registre | non | non | non | oui |

Chaque colonne s'ajoute aux autres : un profil qui publie a typiquement VIEW, UPDATE et CREATE. Le tableau de bord affiche aussi, par profil, les droits réellement accordés.

**La publication est un droit sensible.** Elle envoie la configuration à tous les visiteurs, JavaScript compris : un service personnalisé avec un champ `code`, ou un service à adresse de script, sert du code à tous les visiteurs. Tout ce qui peut amener un tel code au brouillon exige donc aussi CREATE : écrire ou changer le code ou l'adresse de script d'un service, importer une configuration, restaurer une version, activer les services expérimentaux. UPDATE seul modifie le reste du brouillon et ne peut pas publier. DELETE ne couvre que la purge du registre.

**Profil en lecture seule** (VIEW sans UPDATE) : tous les écrans se consultent, les champs sont désactivés, les boutons d'écriture sont masqués et un bandeau « Accès en lecture seule » le rappelle. Sans le droit VIEW, l'entrée de menu « Consentement aux cookies » n'apparaît pas.

## Texte type pour la politique de confidentialité

À reprendre dans votre politique (section « Cookies et traceurs »), en l'adaptant. Le texte du registre est aussi proposé dans l'onglet Registre.

> Pour prouver le consentement que vous donnez, nous conservons une trace de vos choix : un identifiant aléatoire, la date, vos choix, la version de la bannière, la langue, la page, la famille de votre navigateur et, si l'option est active, une adresse IP tronquée. Ces données sont conservées pendant la durée fixée par le site (**durée de conservation** à indiquer) et servent uniquement de preuve du consentement.
>
> Vous pouvez modifier ou retirer votre consentement à tout moment par le lien « Gérer mes cookies » présent en bas de chaque page. Votre choix est mémorisé dans un cookie de premier niveau (`ccm_consent`) pour une durée de 13 mois au plus ; vous êtes de nouveau sollicité après **6 mois** (ou la durée réglée).

À compléter avec : la liste des services utilisés et de leurs responsables, la durée de vie des cookies, et, si vous l'activez, l'adresse IP tronquée.

## Liste de contrôle avant mise en ligne

À rejouer avant chaque mise en production. Une ligne : le geste, puis ce qu'il faut observer.

| # | Geste | À observer |
|---|-------|------------|
| 1 | Navigateur vierge (navigation privée), ouvrir l'accueil, onglets Réseau et Cookies des outils du navigateur | aucune requête vers un service tiers avant choix ; seuls les cookies techniques du site (session, panier, langue, `ccm_consent` après choix) |
| 2 | Cliquer « Tout refuser » | un seul clic, bannière fermée, rien de nouveau dans Réseau ni Cookies |
| 3 | Comparer « Accepter » et « Refuser » | même taille, même couleur, même poids |
| 4 | Chercher « Gérer mes cookies » sur l'accueil, une fiche produit, le panier, le tunnel de commande | présent et fonctionnel partout |
| 5 | Accepter, retirer un consentement, recharger | les cookies du service ont disparu (sauf cookies tiers non effaçables, un message l'indique) |
| 6 | Mobile (375 px) : ajouter un produit au panier avec la bannière affichée | le bouton est accessible, la bannière ne dépasse pas 40 % de l'écran |
| 7 | Clavier seul (Tab, Entrée, Espace, Échap), puis lecteur d'écran (NVDA ou VoiceOver) | tous les choix possibles, focus visible, bannière annoncée |
| 8 | Vérification, avant puis après « Tout refuser » | aucun domaine tiers « avant consentement » ; sinon traiter chaque domaine |
| 9 | Demander à votre développeur de chercher dans le thème : GTM, Google Analytics, pixels, vidéos intégrées, polices Google | rien en dur ; sinon migrer. Google Analytics direct et GTM ne s'utilisent jamais ensemble. Les polices Google relèvent du thème (ligne 11 et section « Polices du thème ») |
| 10 | Tag Assistant sur l'accueil, puis sur une 2e page après accord | `default` refusé puis `update` accordé avant toute mesure ; balises déclenchées après `ccm_consent_ready` |
| 11 | Onglet Réseau sur l'accueil | aucune requête `fonts.googleapis.com` : défaut du thème, voir « Polices du thème » (héberger les polices sur le site) |
| 12 | Tableau de bord | aucune alerte rouge |
| 13 | Politique de cookies | liée à la bannière (le lien répond 200, ce n'est pas la page des conditions générales), modèle du module repris, durée de conservation, option IP tronquée décidée et annoncée |
| 14 | Publier, vider le cache, ouvrir en navigation privée | la version publiée est visible ; l'alerte « rendu servi différent » est absente |

## En cas de doute

- La bannière n'apparaît pas : aucune version n'est publiée, ou le module est désactivé. Il n'existe pas d'interrupteur « Bannière activée ».
- Les visiteurs ne voient plus les services après un long moment : un choix expiré (durée de redemande atteinte) ou antérieur à une publication « changement substantiel » suspend les services non essentiels et la bannière se réaffiche ; ils redémarrent à la réponse suivante.
- Un service ne se charge pas après accord : vérifier qu'il est actif, publié, et son identifiant ; consulter la Vérification.
- Une alerte rouge ne disparaît pas : cliquer « Corriger », corriger, puis publier de nouveau.
- Besoin d'un réglage de thème ou de code : `docs/guide-integrateur.md`.

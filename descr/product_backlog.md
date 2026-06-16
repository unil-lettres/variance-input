# Variance Backlog

Ce document est la source unique pour les demandes produit, éditoriales, UX,
tests, sécurité et exploitation qui restent ouvertes pour Variance.

Le cutover de `variance-input` en production a été validé le 2026-06-05.
L'ancien stack legacy reste disponible au moins jusqu'au 2026-07-01 pour
rollback/audit, mais les tâches pré-cutover terminées sont archivées en fin de
document.

## Ouvert

### Urgence 1 - Securite / a traiter rapidement

#### 1. Mettre a jour les dependances signalees par audit

Constat securite :
- `composer audit` signale des advisories Laravel / Symfony, dont des niveaux
  high / medium sur mail, mime, routing et HTTP ;
- `npm audit` signale notamment `axios` et `postcss` ;
- aucun chemin exploitable n'a ete confirme pendant l'audit non destructif,
  mais le niveau des advisories impose une correction rapide.

A faire :
- mettre a jour les dependances Composer Laravel concernees ;
- mettre a jour les dependances npm concernees et reconstruire les assets ;
- relancer les tests Laravel, le build front-end et les probes publiques ;
- deployer apres validation.

#### 2. Durcissement cookie session HTTPS

Constat securite :
- en production, les cookies Laravel `XSRF-TOKEN` et `variance_admin_session`
  sont `SameSite=Lax`, et le cookie de session est `HttpOnly`, mais le flag
  `Secure` n'est pas present dans les reponses HTTPS.

A faire :
- definir `SESSION_SECURE_COOKIE=true` en production ;
- garder `SESSION_HTTP_ONLY=true` et `SESSION_SAME_SITE=lax` explicites ;
- verifier que le flag `Secure` apparait bien sur `/admin/login` ;
- conserver le fonctionnement local sans HTTPS.

#### 3. Masquer les versions runtime dans les en-tetes HTTP

Constat securite :
- les reponses exposent encore `X-Powered-By: PHP/8.3.31`.

A faire :
- desactiver `expose_php` dans les images / configurations PHP concernees ;
- verifier les reponses publiques et admin apres redeploiement ;
- documenter le controle dans les probes post-deploiement.

#### 4. Probes de non-regression sur les chemins publics interdits

Contexte :
- les chemins sensibles ont ete bloques au niveau proxy le 2026-06-05 ;
- ces protections doivent rester verifiees a chaque deploiement.

A couvrir :
- `/medite/` ;
- `/storage/uploads/versions/...` ;
- `/storage/uploads/.../comparisons/...` ;
- `/uploads/versions/...` ;
- `/uploads/_quarantine/...` ;
- `/uploads/__medite_inputs/...` ;
- `/uploads/.../comparisons/...` ;
- `/test-comparison.php` ;
- `/upload_functions.php` ;
- `/php/...` et `/dev/php/...` ;
- `/.env`, `/.git/...`, listings `/uploads/`, `/uploads_images/`,
  `/uploads/pdf/`.

### Urgence 2 - Securite / prochain cycle court

#### 5. Revue des endpoints authentifies en lecture

Contexte :
- les routes Laravel non publiques sont protegees par `auth` ;
- les ecritures critiques sont progressivement alignees sur les permissions
  `edit` ;
- certains endpoints de lecture authentifies peuvent encore exposer des
  metadonnees ou listes liees a des oeuvres non assignees.

A faire :
- auditer les routes `GET` authentifiees (`facsimiles`, `works`, `authors`,
  `chapters`, `comparisons`) ;
- decider quelles lectures restent globales pour le selecteur / UX ;
- appliquer un scoping explicite la ou une donnee ne doit pas etre visible par
  un utilisateur sans droit sur l'oeuvre ;
- ajouter des tests d'autorisation par profil.

#### 6. Autorisation du polling Medite par comparaison

Contexte :
- `/api/task_status/{taskId}` est authentifie ;
- le resultat Medite peut mettre a jour une comparaison et declencher une
  injection de pagination ;
- le controle actuel ne rattache pas explicitement le `taskId` a l'utilisateur
  ou a une comparaison autorisee.

A faire :
- stocker l'association `taskId` / `comparison_id` / `user_id` lors du lancement ;
- verifier l'autorisation avant d'appliquer les metriques ou de declencher les
  jobs de post-traitement ;
- conserver les polling inconnus en lecture passive ou les refuser.

#### 7. Durcissement interne Medite

Contexte :
- Medite n'est pas expose publiquement en production ;
- les endpoints internes acceptent cependant des chemins et sorties fournis par
  le client Laravel ;
- si le service devenait joignable depuis une zone non fiable, `/run_diff`,
  `/run_diff2`, `/task_status/...` et `/uploads/...` seraient trop permissifs.

A faire :
- ajouter un secret interne ou une validation stricte d'origine entre Laravel et
  Medite ;
- supprimer ou desactiver l'endpoint manuel `/run_diff` en production ;
- valider que les chemins transmis a `/run_diff2` restent sous les repertoires
  attendus ;
- eviter toute exposition host-port de Medite en prod.

#### 8. Refonte durable du stockage des artefacts prives

Etat :
- le 2026-06-05, les acces publics directs aux chemins sensibles ont ete
  bloques au niveau Nginx.

A faire :
- deplacer les sources TXT/XML, brouillons de comparaison et entrees Medite
  hors des arbres servis statiquement ;
- servir les besoins admin via des routes Laravel authentifiees et autorisees ;
- conserver uniquement les artefacts publies dans les chemins publics legacy ;
- garder les probes de non-regression sur les chemins publics interdits.

#### 9. Durcissement des scripts legacy non publics

Contexte :
- certains scripts PHP historiques restent dans le `DocumentRoot` legacy alors
  qu'ils ne font pas partie du site public courant ;
- les scripts d'upload legacy sont proteges par Basic Auth en production, mais
  cette protection depend de fichiers `.htaccess` et `.htpasswd` externes.

A faire :
- supprimer ou bloquer explicitement les scripts de test et utilitaires legacy ;
- remplacer la dependance `.htaccess` par des regles Apache/Nginx versionnees
  pour les chemins non publics ;
- ajouter des probes de deploiement sur ces chemins (`upload.php`, `backoff/`,
  `test-comparison.php`, `php/`, fichiers de configuration).

#### 10. Durcissement XSS des contenus edites

Contexte :
- les editeurs XML/XHTML permettent a des utilisateurs autorises de modifier des
  contenus qui seront ensuite rendus dans l'admin et/ou le site public ;
- la confiance est forte cote editeurs, mais un compte compromis pourrait
  injecter du HTML actif.

A faire :
- definir la liste exacte des balises/attributs autorises dans les composants
  publies ;
- nettoyer ou refuser les elements dangereux avant publication ;
- completer avec des en-tetes de securite (`Content-Security-Policy`,
  `X-Frame-Options`/`frame-ancestors`, `X-Content-Type-Options`).

#### 11. Politique explicite pour l'espace public `/dev`

Contexte :
- le catalogue legacy expose aussi un espace `/dev` pour les comparaisons
  publiees en mode brouillon ;
- cette exposition est fonctionnelle mais doit etre assumee explicitement.

A decider :
- conserver `/dev` public comme espace de validation partage ;
- ou le proteger par authentification / VPN / role admin ;
- dans tous les cas, documenter la regle et ajouter des controles de regression.

#### 12. Alignement de la politique mots de passe

Contexte :
- la creation utilisateur admin impose 8 caracteres ;
- l'ancien controleur d'inscription admin-only accepte encore 4 caracteres.

A faire :
- aligner tous les flux a une regle commune plus stricte ;
- envisager une validation type longueur minimale 12 et listes de mots de passe
  compromis si la dependance est acceptable.

#### 13. Audit des dependances Python Medite

Contexte :
- l'audit non destructif a releve les versions Python Medite, mais aucun outil
  type `pip-audit` n'est encore integre au projet.

A faire :
- ajouter un scan d'advisories Python au workflow de dependances ;
- verifier Flask, Celery, Redis, Werkzeug, Jinja, Kombu et les dependances
  transitives ;
- documenter la procedure dans `descr/dependency_updates.md`.

#### 14. Durcissement Docker production

Objectif :
- reduire l'impact d'une compromission de conteneur.

A evaluer :
- utilisateurs non-root la ou c'est compatible ;
- `read_only`, `no-new-privileges`, capabilities minimales ;
- volumes explicitement en lecture seule pour les services qui n'ecrivent pas ;
- ports host uniquement lorsqu'ils sont necessaires.

#### 15. Durcissement HTTP complementaire

Pistes :
- eviter que `/health` cree ou renvoie des cookies de session si ce n'est pas
  necessaire ;
- verifier les en-tetes cache sur les pages admin ;
- completer les en-tetes de securite apres validation d'une CSP compatible avec
  l'admin et le legacy public.

### Priorite haute - Produit / donnees

#### 16. Nettoyage des listes de transformations XHTML

Origine :
- retour Maxime apres comparaisons sur Balzac / *Melmoth*.

Symptomes decrits :
- le libelle `[retour ligne]` doit devenir le symbole de paragraphe `¶` dans les
  listes `d.xhtml`, `i.xhtml`, `r.xhtml`, `s.xhtml` ;
- les transformations constituees uniquement d'espaces (`[espace]`) ne doivent
  plus apparaitre dans ces listes ;
- certains cas semblent correspondre a un remplacement d'espace par un retour
  ligne / nouveau paragraphe, qui doit etre identifie de maniere specifique ;
- certains libelles incluent des espaces indesirables en debut ou fin de mot,
  par exemple un rendu de ponctuation du type `hello ,`.

Contexte technique :
- generation cote Medite Python, notamment
  `medite/app/variance/variance/tei_writer.py` ;
- les tests actuels valident encore l'ancien comportement, donc il s'agit d'un
  changement produit a tester explicitement.

A faire :
- remplacer `[retour ligne]` par `¶` ;
- filtrer les entrees dont le libelle normalise est uniquement espace ou espace
  insecable ;
- definir le libelle pour espace -> paragraphe et paragraphe -> espace ;
- nettoyer les espaces parasites aux frontieres de mots et avant ponctuation ;
- ajouter des tests unitaires Medite.

#### 17. Suppression de version par un editeur restreint

Symptome :
- un utilisateur de type editeur ne peut pas supprimer une version creee par
  lui-meme.

Contexte :
- `VersionController::destroy()` appelle `assertVersionEditable()` ;
- `User::canEditVersion()` demande le droit complet `edit` sur l'oeuvre ;
- les versions ne portent pas aujourd'hui de champ `created_by`.

A decider :
- conserver le comportement et ameliorer le message UI ;
- ou ajouter une vraie propriete des versions (`created_by`) avec migration,
  attribution a l'import et regle de suppression.

#### 18. Cas "version fantome" sur *La Cousine Bette*

Origine :
- retour Maxime apres imports / alignements sur *La Cousine Bette*.

Symptomes :
- une version supprimee / recreee peut rester visible ;
- apres reimport, le texte peut reapparaitre en double.

A faire :
- reproduire localement ou diagnostiquer sur staging ;
- inspecter versions, chemins XML, fac-similes, sidecars et cache editeur ;
- decider si une version sans texte mais encore referencee doit rester visible
  explicitement comme cas casse, etre masquee, ou etre archivee ;
- ameliorer les messages associes.

#### 19. Selection d'oeuvre reinitialisee par `Choisir l'oeuvre`

Symptome :
- quand on clique sur `Choisir l'oeuvre`, les menus deroulants auteur et oeuvre
  sont reinitialises comme si l'utilisateur avait clique sur le bouton croix.

Etat 2026-06-16 :
- correctif implemente localement : le retour a l'etape `Choisir l'oeuvre`
  n'emet plus d'evenement global de rechargement / remise a zero ;
- a valider visuellement sur la stack Docker locale.

A faire :
- reproduire localement ;
- identifier si l'evenement de selection reutilise par erreur le chemin de
  remise a zero ;
- verifier le comportement avec aucun auteur selectionne, auteur seul, auteur et
  oeuvre selectionnes, et retour depuis une URL `/select/{author}/{work}`.

#### 20. Investiguer les `Avertissements de coherence`

Contexte :
- des `Avertissements de coherence` apparaissent notamment dans
  `Le Horla [demonstration]` ;
- le cas whitespace-only `as_*` / `bi_*` a ete corrige pour les nouvelles
  comparaisons ;
- le cas restant connu concerne les transpositions cible `bd_*` generees sans
  contrepartie source `ad_*` et sans entree `d.xhtml`.

A faire :
- reproduire sur le cas `Le Horla [demonstration]` ;
- lister precisement les avertissements affiches ;
- determiner s'ils signalent un vrai probleme de donnees, un artefact attendu
  du pipeline legacy / Medite, ou un controle trop strict / mal libelle ;
- corriger les donnees, la logique de controle ou le message selon le diagnostic.

#### 21. Nettoyage des versions legacy et simplification de l'UI versions

Objectif :
- verifier et nettoyer les versions legacy pour s'assurer que tous les textes
  attendus sont presents et reconstruisibles ;
- reconstituer, quand c'est possible, les sidecars de pagination legacy depuis
  les marqueurs `page-marker` presents dans les XHTML de comparaison publics ;
- une fois ce controle termine, supprimer les elements d'interface devenus
  inutiles : `Encodage`, `Reconstruire`, `Texte reconstruit depuis source`.

A faire :
- auditer les versions legacy importees et leurs fichiers source / XML ;
- identifier les versions sans texte, texte incomplet ou artefacts incoherents ;
- auditer les oeuvres legacy sans sidecar de pagination mais avec fac-similes et
  marqueurs XHTML exploitables, par exemple *Le Crime de Sylvestre Bonnard* ;
- extraire / dedupliquer les marqueurs `page-marker`, les rattacher aux bonnes
  versions, puis comparer le resultat au nombre de fac-similes attendus ;
- generer les sidecars localement, tester le lecteur synchronise, puis appliquer
  en prod seulement apres sauvegarde et validation visuelle ;
- corriger ou documenter les cas restants ;
- seulement apres validation du corpus legacy, retirer les actions UI qui
  servaient a compenser ces incertitudes.

Etat local :
- sous-cas *Le Crime de Sylvestre Bonnard* teste localement le 2026-06-16 :
  les sidecars `pb-xhtml` reconstruits depuis les comparaisons legacy ont ete
  abandonnes, car l'alignement visuel etait correct pour certaines versions
  mais incorrect pour la version `0_3csb` ;
- les sidecars locaux CSB 73 a 78 ont ete supprimes en attendant de retrouver
  d'eventuels fichiers `_lignes` originaux ;
- 28 fichiers `_lignes` legacy retrouves localement et correspondant
  directement a un `folder` de version ont ete importes dans la stack Docker
  locale, puis leurs sidecars ont ete regeneres.
- le bouton `Reconstruire` du lecteur synchronise est masque localement ; la
  route backend reste conservee temporairement comme outil technique de secours.
- le menu de choix `TXT de version` / `Texte reconstruit` est masque localement ;
  le lecteur garde le choix automatique cote code.
- le menu `Encodage auto` est masque localement, les textes legacy ayant ete
  convertis et verifies manuellement.
- le sous-titre du lecteur ne mentionne plus la source texte ; il affiche une
  indication simplifiee du type `Pagination : 72 reperes disponibles`.
- le selecteur central du lecteur utilise des libelles explicites, par exemple
  `Page 10a (56/72)` ou `Image 10a (1/72)`.

#### 22. Email a Joel Zufferey apres correction Crisinel

A faire :
- envoyer un email a Joel pour lui indiquer que Variance est desormais en
  production ;
- lui preciser que sa comparaison Crisinel / *Alectone* a ete regeneree,
  corrigee et reassignee a son compte.

### Priorite moyenne - UX / edition

#### 23. Verification UI des tableaux `Versions` / `Comparaisons`

A revalider :
- tableau `Versions` ;
- tableau `Comparaisons` ;
- fenetre etroite ;
- troncatures ;
- tailles de boutons ;
- pills / libelles compacts ;
- lisibilite des colonnes ;
- page de login : centrer le titre `VARIANCE` uniquement sur cette page, ou il
  est actuellement aligne a gauche.

Etat 2026-06-16 :
- centrage du titre `VARIANCE` implemente localement pour la page de login
  seulement ;
- autres points UI de cette entree encore a revalider / traiter.

#### 24. Messages d'erreur encore generiques

Objectif :
- remplacer les alertes JS generiques restantes par des messages exploitables.

A verifier :
- import ;
- suppression ;
- publication partiellement reussie ;
- erreurs Medite ;
- erreurs de permissions ;
- message 403 de l'editeur de versions legacy : `Acces limite aux versions
  assignees` est trompeur pour un admin, car le refus vient du statut legacy de
  la version / oeuvre et non d'une assignation manquante.

Etat 2026-06-16 :
- message 403 legacy de l'editeur de versions implemente localement avec test
  de non-regression ;
- autres messages generiques encore a auditer.

#### 25. Clarifier le flux editorial pagination

Objectif :
- faire des balises `<pb>` la representation editoriale claire dans la version ;
- laisser le sidecar et les jobs gerer les marqueurs visuels de comparaison.

A faire :
- verifier quels gestes UI creent aujourd'hui des marqueurs de pagination ;
- supprimer l'ambiguite entre balise TEI `<pb .../>` et marqueur XHTML injecte
  `<span class="page-marker">...` ;
- documenter le flux recommande dans `descr/workflow.md`.

#### 26. Outil d'insertion / edition de `<pb>`

A faire :
- ajouter ou corriger un outil d'insertion `<pb .../>` ;
- definir les attributs reellement pris en charge : `facs`, `pagination`,
  eventuellement `n` ;
- afficher une aide courte dans l'editeur.

#### 27. Verification bout en bout `<pb>` -> sidecar -> comparaison

Recette a figer :
1. insertion de `<pb>` dans une version ;
2. sauvegarde ;
3. fusion ou creation du sidecar depuis `<pb>` ;
4. comparaison ;
5. injection de pagination ;
6. verification du rendu final.

#### 28. Signalement UI de l'etat pagination

Pistes :
- nombre de balises `<pb>` detectees ;
- presence / absence du sidecar ;
- divergence XML / sidecar ;
- actions explicites : creer sidecar depuis `<pb>`, fusionner `<pb>` vers sidecar.

#### 29. Nettoyage d'anciens marqueurs incoherents

A faire :
- auditer les contenus avec `page-marker` la ou on attend du TEI ;
- decider s'il faut nettoyer automatiquement ou documenter les cas legacy.

#### 30. Tests Medite autour des `<pb>`

A faire :
- ajouter des tests de non-regression sur des versions contenant `<pb>` ;
- verifier ce qui survit dans les sorties intermediaires du pipeline.

#### 31. Recherche / remplacement dans l'editeur de version

Origine :
- demande Maxime, 8 mai 2026.

A preciser :
- portee : document entier ou selection ;
- remplacement simple ou expressions regulieres ;
- previsualisation / confirmation ;
- interaction avec sauvegarde, rechargement et controles XML.

#### 32. Export des fichiers `_lignes`

Objectif :
- permettre l'export / telechargement du fichier `_lignes` associe a une
  version quand il existe.

A faire :
- verifier le comportement actuel du bouton / endpoint ;
- rendre l'action visible et comprehensible dans le tableau des versions ;
- tester fichier present, fichier absent et utilisateur sans droit complet.

Etat 2026-06-16 :
- implemente localement : le tableau `Versions` affiche un bouton de
  telechargement quand un fichier `_lignes` existe ;
- le telechargement est protege par authentification ;
- les admins peuvent telecharger les `_lignes` legacy, les editeurs restreints
  seulement ceux des versions non legacy assignees ;
- tests de regression ajoutes pour acces non authentifie, admin legacy et
  editeur restreint assigne / non assigne ;
- valide visuellement localement par Julien.

#### 33. Outil exposant dans l'editeur de version

Objectif :
- introduire une gestion complete des exposants dans l'application, sur le
  modele de la gestion des italiques ;
- preserver le travail editorial legacy : `^...^` doit rester reconnu comme
  marqueur source d'exposant, comme `\...\` pour les italiques ;
- produire et conserver une representation XML explicite en `<sup>...</sup>`.

Contexte :
- le legacy utilisait deja `^...^` pour les exposants ;
- l'audit prod du 2026-06-16 a confirme que les versions reconstruites depuis
  XHTML preservent les exposants en TXT et XML ;
- les versions legacy sans XHTML de reference, notamment les 6 versions de
  `La Cousine Bette`, peuvent encore contenir des `^...^` non convertis en XML ;
- le parseur actuel `Txt2TeiInlineMarkup` gere les italiques mais pas encore
  les exposants.

A faire :
- etendre le parseur TXT -> TEI pour convertir `^...^` en `<sup>...</sup>`
  sans regression sur les italiques ;
- ajouter un outil d'exposant dans l'editeur de version ;
- verifier le rendu dans l'editeur, le lecteur public, les comparaisons et les
  exports ;
- ajouter des tests pour import, edition, sauvegarde / reouverture, Medite et
  publication ;
- prevoir un backfill controle pour les versions qui ont encore des marqueurs
  `^...^` non convertis.

Etat 2026-06-16 :
- implemente localement : le parseur TXT -> TEI convertit `^...^` en
  `<sup>...</sup>` et conserve les marqueurs orphelins en texte brut ;
- les croisements ambigus entre marqueurs italiques et exposants sont conserves
  sous forme de texte brut lorsqu'une conversion produirait du XML invalide ;
- l'editeur de version propose des boutons d'insertion `<sup>` / `</sup>` ;
- le mode balises masquees affiche les exposants comme widgets cliquables,
  comme les italiques ;
- le rapport de coherence des balises texte controle maintenant italiques et
  exposants ;
- tests unitaires et test d'import ajoutes, build front-end valide ;
- reste a valider visuellement dans la stack Docker locale avant de considerer
  le deploiement.

#### 34. Insertion d'image in-texte

A faire :
- definir la balise / les attributs cibles ;
- preciser le lien avec les fac-similes ;
- eviter les conventions `[Image]` dans les sources TXT.

#### 35. Gestion des appels de notes

A faire :
- choisir la structure XML cible ;
- ajouter l'outil d'insertion / edition ;
- couvrir au moins un cas de sauvegarde et reouverture.

### Priorite basse - Tests / recettes

#### 36. Test d'import de version enrichi

A couvrir :
- alineas ;
- doubles espaces ;
- fins de ligne ;
- bords de fichier ;
- caracteres invisibles ;
- normalisations legacy d'encodage et de ponctuation.

#### 37. Recette manuelle editeur

Parcours minimal :
1. ouvrir une version ;
2. modifier le XML ;
3. sauvegarder ;
4. rouvrir ;
5. verifier pagination / fac-similes / comparaison selon le cas.

#### 38. Test end-to-end du workflow editorial

Perimetre vise :
1. creation d'une version ;
2. import `_lignes` ;
3. creation d'une comparaison ;
4. publication ou export ;
5. verification minimale des artefacts attendus.

Le test doit rester cible et robuste, sans devenir une suite UI lourde.

## Recurrent / Exploitation

### 39. Nettoyage ponctuel des reliquats sur staging

Origine :
- bilan post-deploiement du 17 avril 2026.

A nettoyer apres validation :
- `__medite_inputs/...` orphelins ;
- dossiers vides sous les arbres d'uploads ;
- restes de queue / staging ;
- failed jobs connus comme historiques.

### 40. Copie hors VM des backups quotidiens

A decider :
- copie automatique hors VM ;
- ou backup manuel hors VM avant les deploiements importants.

### 41. Verification operationnelle apres chaque deploiement staging

A controler :
- `/health` ;
- `/admin/health/report` ;
- workers / scheduler ;
- migrations ;
- chemins legacy critiques ;
- backup DB ;
- assets admin `/admin/build/...` ;
- hard refresh navigateur si interface ancienne.

### 42. Convergence backups DB vers Laravel

Objectif :
- utiliser le scheduler Laravel comme source versionnee plutot qu'un cron VM
  temporaire.

A faire :
- verifier que `backup:database` et sa planification sont deployes ;
- confirmer un dump Laravel valide ;
- desactiver le cron temporaire si encore present.

### 43. Annonce de maintenance avant gros deploiement

A faire :
- annoncer la fenetre au moins 48 h a l'avance ;
- activer le splash admin avant migrations / recreations ;
- desactiver apres validation finale.

### 44. Demarrage Medite et permissions

Etat :
- le sweep recursif de permissions a ete limite dans `medite/entrypoint.sh`,
  mais le point reste a surveiller sur de gros arbres d'uploads.

A verifier :
- redemarrage Medite rapide ;
- `/health` disponible rapidement ;
- aucune correction recursive couteuse au demarrage.

## Termine / Archive

### A. Cutover production `variance-input`

Etat :
- termine et valide le 2026-06-05 ;
- l'ancien stack legacy reste disponible au moins jusqu'au 2026-07-01.

### B. Masquer le menu `Site public` sur la page de connexion

Etat :
- termine, teste localement, deploye en production le 2026-06-05.

### C. Clarifier les controles d'espace disque VM / NAS

Etat :
- termine, teste localement, deploye en production le 2026-06-05 ;
- le rapport sante distingue stockage Laravel local et chemins NAS ;
- le preflight fac-similes utilise le chemin public uploads.

### D. Mettre a jour le message staging

Etat :
- termine sur `plt-tst-1` le 2026-06-05 ;
- message affiche sur l'interface admin staging sans prefixe fixe
  `Maintenance annoncee`.

### E. Confirmer la mise en production a Maxime

Etat :
- email envoye le 2026-06-05.

### F. Rendre Medite reproductible

Etat :
- termine pour le besoin cutover ;
- `PYTHONHASHSEED=0` est defini dans le Dockerfile Medite et les compose dev,
  staging et production.

Note long terme :
- remplacer les usages de `hash()` Python par une cle stable explicite reste
  envisageable si une non-determinisme residuel est observe.

### G. Stabiliser l'etat operationnel staging pour le cutover

Etat :
- termine pour le cutover ;
- les anciens `failed_jobs` connus ont ete traites avant la promotion des
  donnees ;
- staging et production ont ete valides verts avant/apres cutover.

### H. Valider le corpus de pre-production

Etat :
- obsolete depuis le cutover valide ;
- le catalogue public production a ete compare a legacy avant validation.

### I. Controle XML avant Medite

Etat :
- implemente ;
- l'editeur refuse les XML invalides sans ecraser le fichier existant ;
- Medite refuse les extractions texte vides avec une erreur explicite.

### J. Acces restreint a l'editeur de versions

Etat :
- implemente ;
- permission `version_editor` disponible ;
- routes et tests d'autorisation en place.

## Documents associes

- Plan de cutover production :
  [prod_cutover_variance_input_2026-05-22.md](/Users/jganivet/Développement/variance2/descr/prod_cutover_variance_input_2026-05-22.md:1)
- Workflow :
  [workflow.md](/Users/jganivet/Développement/variance2/descr/workflow.md:1)
- Fac-similes :
  [facsimiles.md](/Users/jganivet/Développement/variance2/descr/facsimiles.md:1)
- Carte du code Laravel :
  [laravel_current_code_map.md](/Users/jganivet/Développement/variance2/descr/laravel_current_code_map.md:1)

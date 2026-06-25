# Variance Backlog

Ce document est la source unique pour les demandes produit, éditoriales, UX,
tests, sécurité et exploitation qui restent ouvertes pour Variance.

Le cutover de `variance-input` en production a été validé le 2026-06-05.
L'ancien stack legacy reste disponible au moins jusqu'au 2026-07-01 pour
rollback/audit, mais les tâches pré-cutover terminées sont archivées en fin de
document.

Mise a jour 2026-06-25 :
- la release production `0.6.0` est deployee ;
- l'upgrade Laravel 13 et les mises a jour de securite urgentes sont clos ;
- `scripts/probe_public_security.sh https://variance.unil.ch` passe en
  production.

## Ouvert

### Urgence 1 - Securite / a traiter rapidement

Aucune entree ouverte. Les anciennes entrees urgentes 1 a 4 ont ete fermees
apres le deploiement production `0.6.0` et les probes du 2026-06-25.

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
- 5 fichiers `_lignes` generiques ont ete exploites localement le 2026-06-19
  apres diagnostic par preview du moteur de matching :
  `2es1879` (515 reperes, 1 manque), `2chabert1835` (131/0),
  `3chabert1839` (97/1), `1pib1898` (351/9), `5pib1923` (259/1).
- validation visuelle locale 2026-06-19 : les 5 sidecars ci-dessus sont
  acceptes pour l'alignement global.
- les 12 reperes manquants des sidecars ci-dessus ont ete retrouves localement
  depuis les XHTML publics legacy et rattaches au texte courant sans recopier
  directement les offsets XHTML : `2es1879` (516/0), `2chabert1835` (131/0),
  `3chabert1839` (98/0), `1pib1898` (360/0), `5pib1923` (260/0).
  Sauvegarde locale :
  `laravel/storage/app/private/local_legacy_sidecar_xhtml_completion_backup/20260619_145308`.
- cas restants a traiter manuellement ou apres amelioration du parseur :
  Perrault `lignes.txt`, Albert Savarus `lignes.txt` avec reperes `1.1a`,
  Boylesve `lignes1.txt`, et les versions tres proches Boylesve 1908/1923
  pour validation visuelle.
- reprise locale 2026-06-25 :
  - commande ajoutee : `php artisan legacy:rebuild-missing-sidecars` ;
  - dry-run par defaut, ecriture explicite avec `--write` ;
  - les fichiers `_lignes` directs sont privilegies ;
  - les fichiers generiques `lignes*.txt` ne sont utilises que sous seuil de
    confiance (`--max-generic-misses`, `--max-generic-miss-rate`) ;
  - sinon, le sidecar est reconstruit depuis les marqueurs XHTML de comparaison ;
  - execution locale : 78 sidecars legacy crees, dont 32 `origin=lignes` et
    46 `origin=pb-xhtml`, aucun sidecar legacy manquant apres verification ;
  - reste a valider visuellement avant toute application en production,
    notamment les sidecars derives de XHTML pour CSB et les cas qui etaient
    precedemment signales comme sensibles.
- correctif local 2026-06-25 pour CSB `0_3csb` / version 75 :
  - le TXT importe avec retours de ligne artificiels a ete sauvegarde puis
    remplace depuis le TXT original CP1252 decode en UTF-8 ;
  - le TEI a ete regenere via `VersionTextService` ;
  - le fichier `_lignes` original a ete archive comme `lignes/75.txt` et le
    sidecar local est desormais `origin=lignes`, 87 reperes, 0 manque ;
  - le matching `_lignes` ignore maintenant aussi les marqueurs exposant
    legacy `^...^`, en plus des marqueurs italiques `\...\`.
  - validation visuelle par Julien : version 75 parfaite dans le visualiseur ;
  - a reporter en production : TXT corrige, XML regenere, `_lignes/75.txt`,
    sidecar `pagination/75.json`, apres sauvegarde prod.
- correctif local 2026-06-25 pour CSB `1csb` / version 76 :
  - le sidecar `pb-xhtml` local produisait trois entrees par page dans le
    carrousel ;
  - le fichier original `1csb_lignes.txt` CP1252 a ete decode en UTF-8 et
    archive comme `lignes/76.txt` ;
  - le sidecar local est desormais `origin=lignes`, 323 reperes, 0 manque,
    sans pages dupliquees ;
  - validation visuelle locale a faire avant report en production.
- correctif local 2026-06-25 pour CSB `2csb` / version 77 :
  - meme probleme de sidecar `pb-xhtml` avec trois entrees par page ;
  - le fichier original `2csb_lignes.txt` CP1252 a ete decode en UTF-8 et
    archive comme `lignes/77.txt` ;
  - le matching `_lignes` accepte maintenant les titres de chapitre en chiffres
    romains presents dans le fac-simile mais absents du TXT propre ;
  - le sidecar local est desormais `origin=lignes`, 319 reperes, 0 manque,
    sans pages dupliquees ;
  - validation visuelle par Julien : version 77 correcte dans le visualiseur ;
  - a reporter en production : `_lignes/77.txt` et sidecar
    `pagination/77.json`, apres sauvegarde prod.
- validation visuelle locale 2026-06-25 :
  - version 78 / `3csb` correcte dans le visualiseur avec son sidecar
    `pb-xhtml` existant ;
  - toutes les versions CSB 73 a 78 sont validees localement.
- validation visuelle locale 2026-06-25 :
  - *La Vie d'un simple* / Emile Guillaumin : les 4 versions 69 a 72
    (`1vds`, `2vds`, `3vds`, `4vds`) sont validees localement ; sidecars
    `origin=lignes`, alignement visuel correct.
- validation visuelle locale 2026-06-25 :
  - *Les Signes parmi nous* / Ramuz : versions 37 et 38 (`1spn`, `2spn`)
    validees localement, sans derive visible ;
  - version 39 (`3spn`, sidecar `origin=pb-xhtml`) presentait une derive
    lorsque l'ancre XHTML collait le numero de page, un titre de chapitre en
    chiffres romains et le premier mot (`97VICaille...`) ;
  - correctif lecteur local : les ancres `pb-xhtml` suppriment maintenant ce
    titre romain initial avant reancrage dans le TXT ;
  - validation visuelle par Julien : version 39 correcte apres correction ;
    toutes les versions Ramuz 37 a 39 sont validees localement.
- validation visuelle locale 2026-06-25 :
  - *Adolphe* / Benjamin Constant : version 46 (`1ado`) validee localement ;
  - version 47 (`1bado`) validee localement apres correction
    locale du chargement des images du lecteur, qui precharge/decode le
    fac-simile cible avant de remplacer l'image visible et ignore les anciens
    chargements termines hors ordre ;
  - diagnostic 47 : les fac-similes complets `img_1bado_007.jpg`,
    `img_1bado_009.jpg`, `img_1bado_010.jpg` etaient tronques/corrompus dans
    le miroir local et dans la sauvegarde prod locale ; les miniatures
    correspondantes etaient completes ;
  - correction locale : les trois fichiers complets ont ete sauvegardes puis
    remplaces par leurs miniatures completes dans `variance/uploads` et
    `laravel/public/uploads` ; sauvegarde :
    `laravel/storage/app/private/local_legacy_image_repair_backup/20260625_141841/benjamin_constant/adolphe/1bado` ;
  - le lecteur ajoute maintenant une cle `?v=` basee sur taille/mtime aux URL
    d'images pour eviter la reutilisation navigateur d'anciens JPEG corrompus ;
  - a reporter en production apres validation : fichiers `007`, `009`, `010`
    repares, ou meilleurs originaux si retrouves.
- correctif local 2026-06-25 pour *Adolphe* version 50 (`4ado`) :
  - la page 263 etait tronquee dans le lecteur parce que le sidecar
    `pb-xhtml` contenait `CHAPITRE X.Je...` sans espace, alors que le TXT
    contient `CHAPITRE X.` suivi d'un retour de ligne ;
  - le reancrage trouvait alors un fragment ambigu de la page 263 avant la
    page 262 ;
  - correctif lecteur local : les ancres `pb-xhtml` normalisent maintenant un
    espace apres ponctuation forte collee a une majuscule, et le nettoyage des
    titres romains n'enleve plus le `C` de `CHAPITRE` ;
  - controle technique : page 263 passe de 62 a 591 caracteres dans le payload
    lecteur ; tests `VersionReaderWorkflowTest` OK ;
  - validation visuelle locale a faire pour la version 50.
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

#### 33. Backfill controle des exposants legacy

Contexte :
- l'outil exposant et le rendu `<sup>` sont deployes en production ;
- les nouveaux imports TXT convertissent `^...^` en `<sup>...</sup>` ;
- les versions legacy reconstruites depuis XHTML preservent deja les exposants
  en TXT et XML ;
- les versions legacy sans XHTML de reference, notamment les 6 versions de
  `La Cousine Bette`, peuvent encore contenir des `^...^` non convertis en XML.

A faire :
- identifier les versions qui contiennent encore des marqueurs `^...^` bruts ;
- verifier manuellement les cas ambigus avant conversion ;
- appliquer un backfill controle seulement quand le resultat XML est certain ;
- relancer une verification lecteur / comparaison sur les versions corrigees.

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

#### 37. Test d'import de version enrichi

A couvrir :
- alineas ;
- doubles espaces ;
- fins de ligne ;
- bords de fichier ;
- caracteres invisibles ;
- normalisations legacy d'encodage et de ponctuation.

#### 38. Recette manuelle editeur

Parcours minimal :
1. ouvrir une version ;
2. modifier le XML ;
3. sauvegarder ;
4. rouvrir ;
5. verifier pagination / fac-similes / comparaison selon le cas.

#### 39. Test end-to-end du workflow editorial

Perimetre vise :
1. creation d'une version ;
2. import `_lignes` ;
3. creation d'une comparaison ;
4. publication ou export ;
5. verification minimale des artefacts attendus.

Le test doit rester cible et robuste, sans devenir une suite UI lourde.

#### 40. Publication `/dev` et manifestes fac-similes cible

Contexte :
- signalement Maxime 2026-06-25 : images de colonne droite absentes sur des
  comparaisons `/dev` de *Poemes negres* ;
- diagnostic prod le 2026-06-25 : les comparaisons 137 a 172 ont toutes un
  manifeste `images_target_...json` present et le premier fac-simile cible
  retourne `200` depuis `127.0.0.1:8081` ;
- les manifestes des nouvelles comparaisons 158 a 172 ont ete generes autour
  de 11:20, et les pages HTML rendent bien `imagesTarget` et `data-src` cible.

Etat local :
- correctif workflow implemente localement : `publishManifests()` peut
  reconstruire un manifeste depuis les fac-similes du miroir legacy quand le
  stockage Laravel ne contient pas les images ;
- le miroir legacy du manifeste est tente meme si la recopie des images a ete
  ignoree ;
- correctif visualiseur legacy implemente localement et hotfixe en production :
  quand une colonne a des images mais aucun marqueur de page, le visualiseur
  ouvre l'image 1 apres affichage du panneau, afin d'eviter une zone image vide ;
- regression ajoutee : publication `/dev` avec fac-similes cible presents
  uniquement dans le miroir legacy.

A faire :
- inclure ce correctif dans le prochain deploiement applicatif ;
- demander a Maxime de recharger les pages concernees et de signaler un ID si
  une colonne droite reste vide apres hard refresh.

## Recurrent / Exploitation

### 41. Nettoyage ponctuel des reliquats sur staging

Origine :
- bilan post-deploiement du 17 avril 2026.

A nettoyer apres validation :
- `__medite_inputs/...` orphelins ;
- dossiers vides sous les arbres d'uploads ;
- restes de queue / staging ;
- failed jobs connus comme historiques.

### 42. Copie hors VM des backups quotidiens

A decider :
- copie automatique hors VM ;
- ou backup manuel hors VM avant les deploiements importants.

### 43. Verification operationnelle apres chaque deploiement staging

A controler :
- `/health` ;
- `/admin/health/report` ;
- workers / scheduler ;
- migrations ;
- chemins legacy critiques ;
- backup DB ;
- assets admin `/admin/build/...` ;
- hard refresh navigateur si interface ancienne.

### 44. Convergence backups DB vers Laravel

Objectif :
- utiliser le scheduler Laravel comme source versionnee plutot qu'un cron VM
  temporaire.

A faire :
- verifier que `backup:database` et sa planification sont deployes ;
- confirmer un dump Laravel valide ;
- desactiver le cron temporaire si encore present.

### 45. Annonce de maintenance avant gros deploiement

A faire :
- annoncer la fenetre au moins 48 h a l'avance ;
- activer le splash admin avant migrations / recreations ;
- desactiver apres validation finale.

### 46. Demarrage Medite et permissions

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

### K. Cas "version fantome" sur *La Cousine Bette*

Etat :
- termine ;
- le cas signale apres imports / alignements sur *La Cousine Bette* est clos.

### L. Email a Joel Zufferey apres correction Crisinel

Etat :
- termine ;
- Joel a ete informe de la mise en production de Variance et de la correction /
  reassignment de sa comparaison Crisinel / *Alectone*.

### M. Nettoyage des listes de transformations XHTML

Etat :
- termine localement ;
- implementation dans `medite/app/variance/variance/tei_writer.py` ;
- tests de regression dans `medite/app/variance/tests/test_tei_writer.py`.

Controle couvert :
- les retours ligne / paragraphes sont affiches avec `¶` dans les listes XHTML ;
- les transformations uniquement composees d'espaces ou espaces insecables ne
  produisent plus d'entree de liste ;
- les substitutions espace -> retour ligne et retour ligne -> espace sont
  rendues avec `¶` ;
- les espaces parasites aux frontieres de libelle et avant ponctuation sont
  nettoyes.

Note 2026-06-23 :
- verification statique faite dans le code et les tests existants ;
- les tests Medite n'ont pas ete relances dans le conteneur local car `pytest`
  n'est pas installe dans l'image courante.

### N. Corriger le statut migrations dans le rapport sante admin

Etat :
- termine localement le 2026-06-23 ;
- implementation dans `laravel/app/Http/Controllers/HealthController.php` et
  `laravel/resources/views/pages/health.blade.php` ;
- regression couverte dans
  `laravel/tests/Feature/Workflow/AdminMaintenanceModeTest.php`.

Controle couvert :
- `HealthController` resout le migrator via `app('migrator')` ;
- le controle migrations est separe du comptage des comparaisons ;
- `/admin/health/report` affiche `A jour` quand les migrations sont appliquees ;
- les erreurs du controle migrations sont affichees explicitement au lieu de
  retomber sur `n/a`.

Validation locale :
- `docker compose exec laravel php artisan test tests/Feature/Workflow/AdminMaintenanceModeTest.php --filter=health_report` ;
- `docker compose exec laravel php artisan test tests/Feature/Workflow/SecurityRouteAccessTest.php` ;
- `docker compose exec laravel php artisan view:cache` ;
- `/health` retourne `200`.

### O. Upgrade Laravel 13 et dependances securite urgentes

Etat :
- termine, valide sur staging, deploye en production le 2026-06-25 dans la
  release `0.6.0` ;
- Laravel de production : `13.17.0` ;
- `APP_VERSION=0.6.0` ;
- les images production sont epinglees par tag et digest dans
  `docker-compose.prod.yml`.

Controle couvert :
- mise a jour Composer Laravel / Symfony et dependances concernees ;
- mise a jour npm, reconstruction des assets et corrections de cache de test ;
- migration prod : `Nothing to migrate` ;
- backup DB manuel avant deploiement :
  `/var/www/variance-input/var/db_backups/manual/variance_prod_pre_0.6.0_20260625T064132Z.sql.gz` ;
- conteneur MariaDB conserve pendant le deploiement ;
- smoke checks prod OK : `/health`, `/admin/health/report`, version Laravel,
  absence de failed jobs.

Document associe :
- [laravel_13_security_upgrade_log_2026-06-24.md](/Users/jganivet/Développement/variance2/descr/laravel_13_security_upgrade_log_2026-06-24.md:1)

### P. Durcissement cookie/session, headers runtime et chemins publics interdits

Etat :
- termine et valide en production le 2026-06-25 ;
- `scripts/probe_public_security.sh https://variance.unil.ch` passe sans
  echec.

Controle couvert :
- `XSRF-TOKEN` : `Secure` et `SameSite=Lax` ;
- `variance_admin_session` : `Secure`, `SameSite=Lax`, `HttpOnly` ;
- absence de `X-Powered-By` sur `/`, `/health`, `/admin/login` ;
- chemins sensibles bloques : `/medite`, sources TXT/XML, brouillons de
  comparaisons, entrees Medite, scripts legacy non publics, `.env`, `.git`,
  listings `uploads`, `uploads_images` et `uploads/pdf`.

### Q. Export des fichiers `_lignes`

Etat :
- termine, teste localement et integre a la release production `0.6.0`.

Controle couvert :
- le tableau `Versions` affiche un bouton de telechargement quand un fichier
  `_lignes` existe ;
- le telechargement est protege par authentification ;
- les admins peuvent telecharger les `_lignes` legacy ;
- les editeurs restreints ne telechargent que les `_lignes` des versions non
  legacy assignees ;
- tests de regression ajoutes pour acces non authentifie, admin legacy et
  editeur restreint assigne / non assigne.

### R. Simplification du lecteur synchronise fac-simile / texte

Etat :
- termine et deploye avant la release production `0.6.0`.

Controle couvert :
- les modes `Auto`, `Largeur`, `Hauteur`, `Reel` ont ete retires ;
- l'image est ajustee a la largeur de la carte image, qui grandit pour rendre
  l'image entiere visible ;
- la carte texte prend la meme hauteur que la carte image ;
- l'en-tete texte affiche uniquement la citation `_lignes` ;
- le survol / focus declenche le soulignement jaune discret dans le texte quand
  la citation est retrouvee ;
- le matching de citation est accent-insensible pour eviter les faux partiels.

### S. Outil exposant dans l'editeur et rendu TEI

Etat :
- termine et integre a la release production `0.6.0`.

Controle couvert :
- le parseur TXT -> TEI convertit `^...^` en `<sup>...</sup>` et conserve les
  marqueurs orphelins en texte brut ;
- les croisements ambigus entre marqueurs italiques et exposants sont conserves
  sous forme de texte brut lorsqu'une conversion produirait du XML invalide ;
- l'editeur de version propose des boutons d'insertion `<sup>` / `</sup>` ;
- le mode balises masquees affiche les exposants comme widgets cliquables ;
- le rapport de coherence des balises texte controle italiques et exposants ;
- les sorties Medite XHTML rendent les exposants TEI.

## Documents associes

- Plan de cutover production :
  [prod_cutover_variance_input_2026-05-22.md](/Users/jganivet/Développement/variance2/descr/prod_cutover_variance_input_2026-05-22.md:1)
- Workflow :
  [workflow.md](/Users/jganivet/Développement/variance2/descr/workflow.md:1)
- Fac-similes :
  [facsimiles.md](/Users/jganivet/Développement/variance2/descr/facsimiles.md:1)
- Carte du code Laravel :
  [laravel_current_code_map.md](/Users/jganivet/Développement/variance2/descr/laravel_current_code_map.md:1)

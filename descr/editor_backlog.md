# Editor Backlog

Ce document regroupe les chantiers encore ouverts autour de l’éditeur Laravel :
- éditeur de version
- pagination `<pb>` / sidecar
- ergonomie éditoriale
- tests de non-régression

Il remplace l’ancien document de “plan” pagination, qui mélangeait état courant,
intentions et implémentation désormais partielle.

## État actuel

Ce qui existe déjà dans le code :
- l’éditeur de version Laravel existe
- `EditorController::versionUpdate()` réécrit le XML et appelle `syncSidecarWithPb()`
- `PageMarkerService` sait :
  - lire des balises `<pb>`
  - créer un sidecar depuis `<pb>`
  - fusionner `<pb>` vers un sidecar existant
  - matérialiser des `<pb>` dans le XML éditeur
- les routes suivantes existent déjà :
  - `POST /api/versions/{version}/pagination/from-pb`
  - `POST /api/versions/{version}/pagination/merge-from-pb`
- les tests couvrent déjà une partie du comportement :
  - cache d’éditeur
  - lazy loading du document
  - invalidation du cache lecteur après `merge-from-pb`

Ce qui n’est pas encore clairement bouclé :
- l’ergonomie de l’éditeur autour des balises `<pb>`
- la cohérence de bout en bout entre édition, sidecar, comparaison et rendu final
- plusieurs outils éditoriaux encore absents

## Priorité haute

### 1. Clarifier le flux éditorial pagination

Objectif :
- faire des balises `<pb>` la représentation éditoriale claire dans la version
- laisser le sidecar et les jobs gérer les marqueurs visuels de comparaison

À faire :
- vérifier exactement quels gestes UI de l’éditeur créent aujourd’hui des marqueurs de pagination
- supprimer toute ambiguïté entre :
  - balise TEI éditoriale `<pb .../>`
  - marqueur XHTML injecté `<span class="page-marker">...`
- documenter explicitement le flux recommandé dans `descr/workflow.md`

### 2. Outil d’insertion/édition de `<pb>` dans l’éditeur

Objectif :
- offrir un vrai geste éditorial pour poser un repère de pagination TEI

À faire :
- ajouter ou corriger un outil d’insertion de balise `<pb .../>`
- définir les attributs réellement pris en charge par le code :
  - `facs`
  - `pagination`
  - éventuellement `n`
- afficher une aide courte dans l’éditeur sur le format attendu

### 3. Vérification bout en bout `<pb>` → sidecar → comparaison

Objectif :
- figer le workflow réel par des tests et une recette manuelle simple

À faire :
- test fonctionnel :
  1. insertion de `<pb>` dans une version
  2. sauvegarde de la version
  3. fusion ou création du sidecar depuis `<pb>`
  4. comparaison
  5. injection de pagination
- confirmer que les comparaisons affichent bien les marqueurs attendus sans conflit

## Priorité moyenne

### 4. Signalement UI de l’état pagination dans l’éditeur

Objectif :
- mieux montrer à l’éditeur ce qui est déjà aligné ou non

Pistes :
- nombre de balises `<pb>` détectées
- présence/absence du sidecar
- divergence éventuelle entre XML et sidecar
- action explicite :
  - “Créer sidecar depuis `<pb>`”
  - “Fusionner `<pb>` vers sidecar”

### 5. Nettoyage d’anciens marqueurs incohérents

Objectif :
- éviter les cas hybrides où des versions ou comparaisons anciennes portent encore des marqueurs historiques ambigus

À faire :
- auditer les contenus qui contiennent encore des `page-marker` là où on attend du TEI
- décider s’il faut :
  - nettoyer automatiquement
  - ou seulement documenter les cas legacy

### 6. Tests Medite autour des `<pb>`

Objectif :
- confirmer que le passage par Medite ne casse pas la stratégie pagination

À faire :
- ajouter des tests de non-régression sur des versions contenant `<pb>`
- vérifier ce qui survit dans les sorties intermédiaires utiles au pipeline

## Priorité moyenne / basse

### 7. Gestion de l’italique

Objectif :
- définir une stratégie claire et stable pour l’italique dans Variance, depuis
  l’import jusqu’à Medite et au rendu final

Contexte :
- certains chercheurs marquent aujourd’hui l’italique avec `\...\` dans les TXT
- l’éditeur Laravel possède déjà des outils autour de balises italiques HTML
  (`<em>...</em>`)
- il faut décider si la représentation éditoriale de référence doit être :
  - en TEI/XML : `<emph>...</emph>`
  - en HTML rendu / comparaison : `<em>...</em>`

Points ouverts :
- faut-il convertir automatiquement la convention `\...\` lors de l’import ?
- faut-il la conserver seulement comme convention source, ou la supprimer au
  profit d’une balise éditoriale explicite ?
- comment exposer cette différence à Medite pour qu’elle soit repérable entre
  versions ?
- que faire des longues séquences en italique, sachant qu’aujourd’hui Medite
  semble surtout repérer le premier et le dernier mot marqués par `\` ?

À faire :
- documenter la représentation canonique retenue pour l’italique
- préciser les conversions attendues :
  - TXT source
  - TEI/XML
  - XHTML / HTML
- vérifier ce que Medite reçoit réellement et ce qu’il compare
- ajouter un ou plusieurs cas de test couvrant :
  - italique court
  - phrase longue en italique
  - différences d’italique entre deux versions
- aligner ensuite l’UI éditeur sur cette décision

### 8. Outil exposant dans l’éditeur de version

Objectif :
- gérer les exposants sans conventions ad hoc de type `^...^`

À faire :
- définir la représentation XML cible
- ajouter l’UI
- tester l’aller-retour sauvegarde/rechargement

### 9. Insertion d’image in-texte

Objectif :
- permettre un ancrage éditorial explicite d’image dans le flux de texte

À faire :
- définir la balise / attributs cibles
- préciser le lien avec les fac-similés déjà gérés ailleurs
- éviter toute convention `[Image]` dans les sources TXT

### 10. Gestion des appels de notes

Objectif :
- fournir une édition propre des appels et de leur contenu associé

À faire :
- choisir la structure XML cible
- ajouter l’outil d’insertion/édition
- couvrir au moins un cas de sauvegarde et de réouverture

## Tests backlog complémentaires

### 11. Test d’import de version enrichi

Objectif :
- figer toutes les normalisations actuellement faites lors de l’import

À couvrir :
- alinéas
- doubles espaces
- fins de ligne
- bords de fichier
- caractères invisibles
- normalisations legacy d’encodage et de ponctuation

### 12. Recette manuelle éditeur

Maintenir une recette simple pour les évolutions de l’éditeur :
1. ouvrir une version
2. modifier le XML
3. sauvegarder
4. rouvrir
5. vérifier pagination / fac-similés / comparaison selon le cas

### 13. Test end-to-end du workflow éditorial

Priorité :
- basse, à traiter dans un prochain cycle

Objectif :
- figer par un test unique le parcours éditorial principal présenté dans la
  documentation

Périmètre visé :
1. création d’une version
2. import `_lignes`
3. création d’une comparaison
4. publication ou export
5. vérification minimale des artefacts attendus

Remarque :
- ce test doit rester ciblé et robuste ; il ne doit pas devenir une suite UI
  lourde ni fragile.

## Décision documentaire

Ce document est un **backlog** :
- pas une description de l’architecture actuelle
- pas une spec figée

Les documents de référence sur l’état réel doivent rester :
- [workflow.md](/Users/jganivet/Développement/variance2/descr/workflow.md:1)
- [facsimiles.md](/Users/jganivet/Développement/variance2/descr/facsimiles.md:1)
- [laravel_current_code_map.md](/Users/jganivet/Développement/variance2/descr/laravel_current_code_map.md:1)

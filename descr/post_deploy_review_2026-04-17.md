# Bilan Post-Déploiement — 17 avril 2026

Ce document résume les actions décidées puis menées après le déploiement en
deux phases du 17 avril 2026 sur `plt-tst-1`.

Il ne sert plus de backlog actif général. Son rôle est désormais :

- garder une trace des risques identifiés ce jour-là ;
- noter ce qui a été effectivement corrigé ;
- isoler les quelques points encore ouverts.

## Réalisé

### Exploitation / déploiement

- sauvegarde DB quotidienne mise en place avec rétention 14 jours ;
- runbook de déploiement mis à jour pour inclure `php artisan migrate --force` ;
- runbook de restauration rédigé ;
- backup applicatif complet de `plt-tst-1` réalisé avant déploiement ;
- déploiement en deux phases validé sur la VM.

### Maintenance / health

- splash maintenance admin en place ;
- bypass admin corrigé ;
- page `/login` accessible pendant maintenance ;
- état de maintenance persistant hors cache, donc survivant à `optimize:clear` ;
- endpoint public `/health` réduit à un statut minimal ;
- page santé détaillée conservée côté admin ;
- health report enrichi :
  - migrations en attente ;
  - SHA de build ;
  - chemins legacy critiques ;
  - état scheduler / workers.

### Fiabilité métier

- suppression renforcée pour nettoyer davantage les reliquats :
  - `__medite_inputs`
  - dossiers vides
  - artefacts privés liés aux versions ;
- publication durcie pour éviter les faux `500` quand la publication principale
  réussit mais qu’une copie legacy secondaire échoue ;
- messages de blocage plus explicites pour certaines suppressions ;
- journalisation structurée des actions sensibles :
  - publication
  - dépublication
  - suppression
  - import chapitres
  - commentaires.

### Documentation

- référence des endpoints admin remise à jour ;
- vue d’architecture remise en phase avec la stack réelle ;
- les documents purement internes d’exploitation ont été sortis du périmètre
  public vers `descr/internal/`.

## Reste ouvert

### 1. Nettoyage des reliquats actuels sur la VM

Le code évite mieux les reliquats futurs, mais il reste encore à faire un
nettoyage ponctuel sur `plt-tst-1` des dossiers techniques déjà orphelins :

- `__medite_inputs/...`
- dossiers vides sous les arbres d’uploads
- éventuels restes de queue / staging.

### 2. Finitions UX sur les tableaux `Versions` / `Comparaisons`

Le responsive a été nettement amélioré, mais ces écrans méritent encore une
vérification plus systématique sur différentes largeurs :

- troncatures
- tailles de boutons
- équilibre visuel des colonnes
- comportement des détails compacts.

### 3. Derniers messages encore génériques

Plusieurs messages ont été clarifiés, mais il reste probablement encore
quelques alertes JS génériques à rendre plus explicites quand une action
échoue ou n’est que partiellement réussie.

### 4. Copie hors VM des backups quotidiens

Le dump quotidien local à la VM existe désormais.

Il reste à décider si l’on souhaite en plus :

- une copie automatique hors VM ;
- ou un simple backup manuel hors VM avant les déploiements importants.

## Décisions prises

- ne plus conserver dans `descr/` public des documents contenant des chemins,
  hôtes ou procédures internes liés à `plt-tst-1` ;
- committer localement par blocs logiques pendant le travail ;
- pousser en fin de session plutôt qu’à chaque étape ;
- conserver un backup manuel complet avant les déploiements significatifs, en
  plus de la sauvegarde DB quotidienne.

## Documents associés

- walkthrough requêtes / code :
  - [codebase_walkthrough_by_request.md](/Users/jganivet/Développement/variance2/descr/codebase_walkthrough_by_request.md:1)
- notes de déploiement :
  - [deployment_notes.md](/Users/jganivet/Développement/variance2/descr/deployment_notes.md:1)
- documentation interne d’exploitation :
  - `descr/internal/`

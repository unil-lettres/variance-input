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

### 5. Contrôle santé VM après le prochain déploiement

Après le prochain déploiement prévu vendredi prochain, prévoir une vérification
opérationnelle explicite de la VM sur la nouvelle version :

- `/health`
- `/health/report`
- workers / scheduler
- état des migrations
- permissions des chemins legacy critiques
- présence et validité du mécanisme de backup
- avant migration, vérifier que les `short_title` non nuls des œuvres sont
  bien uniques sur la VM :
  ```sql
  select short_title, count(*) as count
  from works
  where short_title is not null and short_title <> ''
  group by short_title
  having count(*) > 1;
  ```
- garder en tête que les œuvres legacy peuvent encore avoir `short_title =
  NULL` ; MariaDB accepte plusieurs `NULL` dans l’index unique, mais les
  doublons non nuls bloqueraient la migration

Objectif :
- ne pas se limiter à “l’application répond”, mais vérifier aussi les services
  d’exploitation ajoutés récemment.

### 6. Bascule des backups quotidiens vers Laravel après le prochain déploiement

Aujourd’hui, un backup quotidien DB tourne sur la VM via `cron` comme solution
transitoire.

Après le déploiement de vendredi prochain, il faudra :

- vérifier que la nouvelle version déployée embarque bien :
  - la commande `backup:database`
  - sa planification dans le scheduler Laravel
- confirmer qu’un premier dump Laravel fonctionne sur la VM
- puis désactiver le cron temporaire pour éviter deux systèmes concurrents

Objectif :
- converger vers une seule source de vérité :
  - le scheduler Laravel versionné dans le dépôt
  - plutôt qu’un script cron VM maintenu à part

### 7. Annonce de la maintenance de vendredi prochain

Prévoir explicitement l’annonce de la maintenance du vendredi prochain :

- fenêtre prévue : `08:00–12:00`
- annonce publiée au plus tard `48h` à l’avance

Canal visé :
- annonce Laravel sur la page d’accueil admin, dès que la version déployée sur
  la VM embarque bien la commande `admin:maintenance:announce`

Objectif :
- prévenir les chercheurs suffisamment tôt
- éviter de dépendre seulement d’un message manuel de dernière minute

### 8. Démarrage Medite local trop lent à cause du sweep de permissions

Constat :
- en local, le conteneur `medite` peut rester longtemps `Up` mais indisponible
  car son `entrypoint.sh` fait encore un `chgrp/chmod -R` sur tout `/app/uploads`
  avant de lancer Celery et Flask
- pendant cette phase :
  - `http://medite:5000/health` est injoignable
  - la page santé locale passe en `fail`

Objectif :
- éviter qu’un simple redémarrage Medite soit bloqué par un sweep récursif sur
  tout l’arbre des uploads

Pistes :
- limiter la correction de permissions à la racine des montages
- ne traiter que les nouveaux fichiers
- ou déplacer cette opération dans une maintenance explicite, hors démarrage

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

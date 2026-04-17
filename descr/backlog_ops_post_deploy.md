# Backlog Ops / Fiabilisation après déploiement

Ce document regroupe les suites utiles identifiées pendant le déploiement en deux phases du 17 avril 2026 sur `plt-tst-1`.

Objectif :
- consolider l’exploitation de la VM de test ;
- réduire les risques de prochain déploiement ;
- corriger les angles morts mis en évidence en production de test ;
- nettoyer les résidus techniques laissés par certaines opérations.

## P1 — À faire en priorité

### 1. Sauvegarde quotidienne de la base
Constat :
- il n’existe pas de sauvegarde quotidienne automatisée de la base Variance sur `plt-tst-1`.
- la seule sauvegarde récente est le backup manuel réalisé sur le Mac local avant déploiement.

À faire :
- ajouter un dump quotidien `mariadb-dump` sur la VM ;
- stocker les dumps dans un dossier daté ;
- définir une rétention ;
- ajouter une vérification simple du dump produit ;
- décider si une copie hors VM est nécessaire.

Critère de succès :
- un dump SQL compressé est produit automatiquement chaque jour ;
- les sauvegardes expirées sont purgées selon la politique retenue ;
- on sait restaurer un dump récent sans improvisation.

### 2. Faire survivre le splash maintenance à `optimize:clear`
Constat :
- l’état de maintenance admin est actuellement perdu pendant le déploiement ;
- cause : il est stocké dans un cache effacé par `php artisan optimize:clear`.

À faire :
- déplacer l’état de maintenance vers un stockage persistant non vidé par `optimize:clear` :
  - base de données ;
  - fichier dédié sous `storage/app/private` ;
  - ou autre mécanisme équivalent.

Critère de succès :
- si la maintenance est activée avant déploiement, elle reste active après `optimize:clear`.

### 3. Corriger le `Git SHA` du health report
Constat :
- le health report affiche actuellement un SHA trompeur provenant de l’environnement ;
- il ne correspond pas forcément au code réellement déployé.

À faire :
- afficher le SHA réellement exécuté ;
- ou distinguer explicitement `runtime SHA` et `env SHA` ;
- ou supprimer l’information si elle n’est pas fiable.

Critère de succès :
- le SHA affiché dans le health report reflète l’état réel du code sur la VM.

### 4. Ajouter `php artisan migrate --force` au runbook de déploiement
Constat :
- la migration `add_comments_to_comparisons_table` était déployée dans le code mais pas appliquée sur la VM ;
- cela a cassé l’enregistrement des commentaires de comparaison.

À faire :
- intégrer explicitement `php artisan migrate --force` dans le flux standard de déploiement ;
- idéalement, automatiser le déploiement complet.

Critère de succès :
- un déploiement standard ne laisse plus de migrations applicatives en attente.

## P2 — Fiabilité et nettoyage

### 5. Nettoyer les reliquats de suppression sur la VM
Constat :
- certaines suppressions laissent des résidus :
  - `__medite_inputs/...`
  - dossiers vides sous `storage/app/public/uploads/...`
  - dossiers temporaires ou de queue vides.

À faire :
- identifier les reliquats actuels ;
- supprimer ceux qui sont purement techniques ;
- documenter la liste des emplacements à surveiller.

Critère de succès :
- après suppression d’un auteur / œuvre / version / comparaison de test, il ne reste pas de dossiers techniques inutiles.

### 6. Durcir le code de suppression pour éviter les reliquats
Constat :
- le comportement métier est correct, mais le nettoyage fichier est incomplet.

À faire :
- compléter les routines de suppression pour inclure :
  - `__medite_inputs` liés ;
  - dossiers de staging vides ;
  - dossiers facsimilés / comparaison devenus inutiles ;
  - éventuels backups temporaires vides.

Critère de succès :
- une suppression complète via l’UI laisse un état DB + filesystem cohérent sans nettoyage manuel.

### 7. Durcir le code de publication face aux permissions legacy
Constat :
- une publication peut réussir fonctionnellement tout en retournant un `500` si la copie des fac-similés legacy échoue ensuite ;
- cas observé sur `pdab` à cause de dossiers `root:www-data` non inscriptibles.

À faire :
- rendre la publication plus robuste face à des dossiers legacy préexistants ;
- éventuellement distinguer :
  - succès publication ;
  - avertissement sur synchronisation legacy partielle ;
- vérifier les permissions avant copie ou les normaliser explicitement.

Critère de succès :
- une publication ne doit plus finir en faux échec si le principal est déjà publié ;
- les erreurs de permissions sont soit corrigées, soit signalées plus proprement.

### 8. Ajouter un contrôle health sur les migrations en attente
Constat :
- le health report était vert alors qu’une migration critique restait `Pending`.

À faire :
- enrichir le health report pour signaler :
  - migrations Laravel en attente ;
  - éventuellement un niveau `warning` ou `not_ok`.

Critère de succès :
- un schéma DB non à jour est visible immédiatement dans la page santé.

### 9. Ajouter un contrôle health sur certains chemins critiques
Constat :
- plusieurs problèmes rencontrés venaient de permissions ou états inattendus sur les répertoires legacy / facsimilés.

À faire :
- ajouter des checks sur quelques chemins clés :
  - arbres legacy d’uploads ;
  - répertoires facsimilés cibles ;
  - écritures attendues côté Laravel.

Critère de succès :
- les états filesystem anormaux sont détectés avant de casser une action utilisateur.

## P3 — Produit, UX, traçabilité

### 10. Améliorer encore les tableaux `Versions` et `Comparaisons`
Constat :
- le redimensionnement a été amélioré, mais ces écrans restent sensibles aux petites largeurs.

À faire :
- vérifier le comportement sur plusieurs résolutions ;
- ajuster si besoin les colonnes visibles, troncatures, tailles de boutons ;
- envisager à terme un mode compact dédié.

Critère de succès :
- tableaux exploitables sans débordement gênant sur largeur moyenne / petite.

### 11. Clarifier les raisons de blocage dans l’UI
Constat :
- plusieurs cas ont demandé une investigation technique :
  - version non supprimable car encore référencée ;
  - publication partiellement réussie ;
  - maintenance active.

À faire :
- rendre les messages de l’UI encore plus explicites quand une action est bloquée ou partiellement réussie.

Critère de succès :
- l’utilisateur comprend davantage l’état sans devoir lire les logs.

### 12. Journaliser les actions sensibles
Constat :
- certains problèmes sont difficiles à reconstituer a posteriori.

À faire :
- prévoir une traçabilité simple pour :
  - publication ;
  - suppression ;
  - import chapitres ;
  - commentaires ;
  - opérations d’édition sensibles.

Critère de succès :
- on peut reconstituer plus facilement qui a fait quoi et quand.

## Ordre recommandé pour l’après-midi

1. sauvegarde DB quotidienne ;
2. persistance du splash maintenance ;
3. `php artisan migrate --force` dans le runbook de déploiement ;
4. correction du `Git SHA` du health report ;
5. nettoyage des reliquats actuels ;
6. durcissement du code de suppression ;
7. durcissement du code de publication ;
8. contrôles health supplémentaires ;
9. finitions UX / traçabilité.

## Notes

- Le backup manuel de référence avant déploiement se trouve dans `Développement/Backups/`.
- Le document de référence des chemins persistants VM est :
  - [vm_persistent_paths_reference.md](/Users/jganivet/Développement/variance2/descr/vm_persistent_paths_reference.md:1)
- Le walkthrough code / requêtes est :
  - [codebase_walkthrough_by_request.md](/Users/jganivet/Développement/variance2/descr/codebase_walkthrough_by_request.md:1)

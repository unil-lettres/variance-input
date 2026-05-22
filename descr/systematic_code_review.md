# Revue Systématique Du Code

Ce document sert de **point d’entrée unique** pour la revue du code de
Variance.

Il ne remplace pas les autres documents techniques ; il indique où se trouve la
trace utile selon l’angle de lecture :
- flux de requêtes
- cartographie Laravel
- architecture conteneurs
- pipeline métier
- jobs/queues
- endpoints

## 1. Point De Départ Recommandé

Pour comprendre l’application sans se perdre dans les dossiers, le meilleur
point d’entrée reste :

- [codebase_walkthrough_by_request.md](/Users/jganivet/Développement/variance2/descr/codebase_walkthrough_by_request.md:1)

Ce document suit l’application comme un enchaînement de requêtes :
- requête reçue
- route
- contrôleur
- services / modèles
- fichiers / stockage
- réponse

Il est particulièrement utile pour expliquer le code à quelqu’un de nouveau sur
le projet.

## 2. Cartographie Laravel Actuelle

Pour une vue plus “inventaire du code”, utiliser :

- [laravel_current_code_map.md](/Users/jganivet/Développement/variance2/descr/laravel_current_code_map.md:1)

Ce document résume le Laravel courant par couches :
- routes
- commandes artisan
- contrôleurs
- middleware
- jobs
- modèles
- services
- JavaScript front-end
- tests

Il correspond le mieux à une revue systématique du périmètre Laravel.

## 3. Architecture Et Circulation Des Données

Pour comprendre la stack complète :

- [architecture.md](/Users/jganivet/Développement/variance2/descr/architecture.md:1)

Ce document couvre :
- les conteneurs
- les volumes / montages
- les responsabilités de chaque service
- la séparation Laravel / Medite / legacy

Pour la circulation concrète des artefacts :

- [workflow.md](/Users/jganivet/Développement/variance2/descr/workflow.md:1)

Ce document décrit le pipeline éditorial réel :
- upload de version
- `_lignes`
- sidecar
- fac-similés
- Medite
- injection pagination
- publication
- export

## 4. Surfaces Fonctionnelles Déjà Revues

### API / routes
- [api_endpoints.md](/Users/jganivet/Développement/variance2/descr/api_endpoints.md:1)

### Fac-similés
- [facsimiles.md](/Users/jganivet/Développement/variance2/descr/facsimiles.md:1)

### Jobs / workers / scheduler
- [queues_jobs.md](/Users/jganivet/Développement/variance2/descr/queues_jobs.md:1)

### Import de chapitres
- [chapters_import_flow.md](/Users/jganivet/Développement/variance2/descr/chapters_import_flow.md:1)

### Déploiement / exploitation versionnée
- [deployment_notes.md](/Users/jganivet/Développement/variance2/descr/deployment_notes.md:1)
- [dependency_updates.md](/Users/jganivet/Développement/variance2/descr/dependency_updates.md:1)

## 5. Backlogs Encore Ouverts

### Backlog éditeur
- [editor_backlog.md](/Users/jganivet/Développement/variance2/descr/editor_backlog.md:1)

### Backlog produit / UX
- [product_backlog.md](/Users/jganivet/Développement/variance2/descr/product_backlog.md:1)

### Bilan post-déploiement / restes opérationnels
- [post_deploy_review_2026-04-17.md](/Users/jganivet/Développement/variance2/descr/post_deploy_review_2026-04-17.md:1)

## 6. Ce Que Cette Revue Couvre Déjà

La revue systématique menée jusqu’ici a déjà permis de :
- réaligner une grande partie de la documentation sur le code réel
- sortir les informations d’exploitation internes du périmètre public versionné
- identifier les vraies surfaces stables du Laravel courant
- clarifier les flux principaux :
  - versions
  - pagination
  - fac-similés
  - comparaisons
  - publication
  - health / maintenance / backups

## 7. Ce Qui N’Est Pas Encore Une Revue Exhaustive

Ce travail n’est pas encore un audit ligne à ligne de tout le dépôt.

En particulier, il reste encore possible d’approfondir :
- le code legacy PHP public
- la partie Medite interne côté Python
- les comportements UI très fins côté JS
- certains cas limites de l’éditeur
- les scénarios rares de migration / reprise de données

## 8. Usage Recommandé

Si quelqu’un reprend le projet, l’ordre conseillé est :

1. [systematic_code_review.md](/Users/jganivet/Développement/variance2/descr/systematic_code_review.md:1)
2. [codebase_walkthrough_by_request.md](/Users/jganivet/Développement/variance2/descr/codebase_walkthrough_by_request.md:1)
3. [laravel_current_code_map.md](/Users/jganivet/Développement/variance2/descr/laravel_current_code_map.md:1)
4. [architecture.md](/Users/jganivet/Développement/variance2/descr/architecture.md:1)
5. [workflow.md](/Users/jganivet/Développement/variance2/descr/workflow.md:1)

Cela donne :
- d’abord une vue d’ensemble
- puis une lecture par requêtes
- puis une cartographie du code réel
- puis les détails d’architecture et de pipeline

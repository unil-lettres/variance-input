# Fac-similés

Ce document décrit l’implémentation actuelle des fac-similés dans Variance :
- import
- traitement en file d’attente
- galerie admin
- manifests par comparaison
- publication vers le miroir legacy
- usage par le lecteur synchronisé

Références principales :
- [FacsimileController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/FacsimileController.php:1)
- [ProcessFacsimileImage.php](/Users/jganivet/Développement/variance2/laravel/app/Jobs/ProcessFacsimileImage.php:1)
- [VersionController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/VersionController.php:1)
- [PublishController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/PublishController.php:1)
- [versions.blade.php](/Users/jganivet/Développement/variance2/laravel/resources/views/components/main/versions.blade.php:166)
- [facsimiles.blade.php](/Users/jganivet/Développement/variance2/laravel/resources/views/components/main/facsimiles.blade.php:1)

## Vue d’ensemble

Les fac-similés ont deux vies :
- côté Laravel admin, dans `storage/app/public/uploads/{author}/{work}/{version}`
- côté miroir legacy public, dans `variance/uploads/{author}/{work}/{version}`

Le système :
- importe les fichiers source dans Laravel
- génère une image principale JPEG et une miniature
- permet de définir des manifests par comparaison et par rôle
- publie ou synchronise ensuite ces images vers le miroir legacy

## Import admin

### Interface

L’import se fait depuis la carte `Versions`, via la modale `Téléverser des fac-similés`.

L’utilisateur sélectionne :
- un **dossier source**
- pas une liste de fichiers isolés

Le champ HTML accepte :
- `image/*`

Le backend valide effectivement :
- `jpg`
- `jpeg`
- `png`
- `tif`
- `tiff`

### Endpoint réel

L’upload passe par :
- `POST /api/upload_facsimiles`

Contrôleur :
- `FacsimileController::store()`

Payload principal :
- `version_id`
- `images[]`
- `reset` optionnel

### Restrictions

Les versions legacy sont en lecture seule :
- impossible d’y importer de nouveaux fac-similés

Chaque fichier est limité à :
- `12 Mo`

### Nommage et file d’attente

Les fichiers uploadés ne sont pas conservés sous leur nom d’origine.

Le contrôleur :
1. résout le dossier cible de la version
2. calcule l’index suivant disponible
3. place chaque source dans :
   - `storage/app/private/facsimile_queue/{author}/{work}/{version}`
4. dispatch un job `ProcessFacsimileImage`

Les noms normalisés sont de la forme :
- `img_<version_folder>_001.jpg`
- `img_<version_folder>_002.jpg`

Cette convention est importante, car elle sert ensuite au :
- mapping pagination ↔ images
- lecteur synchronisé
- manifests

### Cas TIFF multipage

Le système gère aussi les TIFF multipages.

Dans ce cas :
- chaque page source produit une image normalisée distincte
- le job reçoit `sourcePage`
- la sortie reste un JPEG unitaire par page

### Traitement asynchrone

Les jobs `ProcessFacsimileImage` tournent sur la queue :
- `facsimiles`

Responsabilités du job :
- lire la source mise en queue
- produire l’image principale JPEG
- produire la miniature `*_thumb.jpg`
- écrire dans :
  - `storage/app/public/uploads/{author}/{work}/{version}`
- supprimer la source temporaire de queue

Le job ne conserve donc pas l’original uploadé comme artefact final.

### Images produites

Pour chaque image finale, on obtient typiquement :
- `img_<version>_<index>.jpg`
- `img_<version>_<index>_thumb.jpg`

La grande image sert :
- à la galerie
- au lecteur
- à la publication

La miniature sert :
- à la galerie
- aux sélecteurs visuels

### Suivi d’état

L’état d’une version peut être consulté via :
- `GET /api/versions/{version}/facsimiles/progress`

Le statut agrège notamment :
- `source_count`
- `published_count`
- `queue_count`
- `processing`
- `in_sync`

Le comptage ignore les miniatures.

## Galerie admin

La galerie de fac-similés utilise :
- `GET /api/facsimiles?version_id=…`

Cette route :
- lit d’abord les fichiers Laravel
- sinon retombe sur le miroir legacy si nécessaire

Chaque entrée renvoyée contient notamment :
- `name`
- `big`
- `thumb`
- `hasThumb`
- `size_bytes`
- `size_human`
- `width`
- `height`

## Annulation / reset d’import

Le système distingue deux opérations.

### Annuler un import en cours

Route :
- `DELETE /api/versions/{version}/facsimiles/cancel-upload`

Effets :
- suppression des fichiers partiels
- suppression du dossier de queue
- pose d’un marqueur d’annulation
- restauration de la série précédente si disponible

### Réinitialiser avant un nouvel import

Lors d’un nouvel upload avec `reset=true`, le backend :
- sauvegarde la série précédente dans
  - `storage/app/private/facsimile_backups/{version_id}`
- supprime la série actuelle
- nettoie aussi le miroir legacy correspondant

## Manifests par comparaison

Les manifests servent à sélectionner, pour une comparaison donnée, quelles images appartiennent :
- au rôle `source`
- au rôle `target`

### Chargement des comparaisons pertinentes

Route :
- `GET /api/versions/{version}/comparisons`

Cette route renvoie les comparaisons liées à la version, avec filtrage pour les non-admins :
- leurs propres comparaisons
- plus les comparaisons legacy visibles

### Sauvegarde d’un manifest

Route :
- `PUT /api/versions/{version}/manifests/{comparison}`

Payload :
- `role`
- `images[]`

Le fichier est écrit sous :
- `storage/app/public/uploads/{author}/{work}/{version}/images_{role}_{author--work--comparison}.json`

Il est aussi recopié vers le miroir legacy.

Important :
- les images listées sont les **noms normalisés** des fac-similés
- la sauvegarde peut déclencher `ensureDefaultMarkers()` si le manifest devient non vide

## Publication

### Publication d’une version seule

Route :
- `POST /api/facsimiles/publish`

Payload :
- `version_id`

Cette opération :
- copie tous les fac-similés de la version vers le miroir legacy
- republie aussi les manifests liés à cette version

### Publication d’une comparaison

Lorsqu’une comparaison est publiée via `PublishController` :
- les fac-similés des versions source et cible sont publiés si nécessaire
- les manifests correspondants sont assurés

Cas possibles côté résultat :
- `ok`
- `skipped`
- `warning`

Exemples de `skipped` :
- version legacy
- dossier vide
- miroir déjà synchronisé
- même montage partagé (`same_mount`)

Important :
- depuis les derniers correctifs, un échec de copie legacy des fac-similés peut remonter comme `warning`
- la publication de la comparaison elle-même peut donc réussir malgré un avertissement sur les images

## Lecteur synchronisé

Le lecteur version utilise :
- `GET /api/versions/{version}/reader`
- `GET /api/versions/{version}/reader/page`
- `GET /api/versions/{version}/reader/progress`
- `POST /api/versions/{version}/reader/rebuild`

Les fac-similés utilisés par le lecteur viennent de :
- `storage/app/public/uploads/...`
- ou, à défaut, du miroir legacy

Le lecteur reçoit pour chaque image :
- `name`
- `image_code`
- `big`
- `thumb`
- `hasThumb`
- et, selon le contexte, dimensions et taille

Le mapping pagination ↔ image s’appuie sur :
- le nom normalisé
- `image_code`
- les marqueurs pagination / `_lignes`
- et, en fallback, des heuristiques d’alignement séquentiel

## Suppression

La suppression d’une version déclenche aussi le nettoyage des fac-similés associés :
- storage public Laravel
- miroir legacy
- queue privée
- backups privés
- marqueur d’annulation

Le nettoyage est conservateur sur les dossiers parents :
- on élague seulement les répertoires devenus vides

## Points d’attention

- garder les workers Laravel actifs, sinon les uploads restent bloqués en queue
- le nommage `img_<version>_<index>` est une convention structurelle, pas cosmétique
- les miniatures ne comptent pas comme fac-similés “éditoriaux”
- les manifests sont propres à un couple :
  - comparaison
  - rôle `source` ou `target`
- une version legacy peut être consultée mais pas réimportée

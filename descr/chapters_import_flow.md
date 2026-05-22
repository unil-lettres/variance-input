# Chapters XLSX Import

Ce document décrit l’implémentation réelle actuelle de l’import des chapitres côté Laravel.

Références principales :
- [ChaptersController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/ChaptersController.php:1)
- [ChapterImportService.php](/Users/jganivet/Développement/variance2/laravel/app/Services/ChapterImportService.php:1)
- [chapters.blade.php](/Users/jganivet/Développement/variance2/laravel/resources/views/components/main/chapters.blade.php:1)
- [ChaptersImportWorkflowTest.php](/Users/jganivet/Développement/variance2/laravel/tests/Feature/Workflow/ChaptersImportWorkflowTest.php:1)

## Périmètre

Le système actuel permet :
- de lister les comparaisons d’une œuvre pouvant recevoir des chapitres
- de charger un fichier `.xlsx`
- d’en prévisualiser le contenu
- de remplacer les lignes existantes de `chapters` pour une comparaison
- de consulter en lecture seule les chapitres déjà stockés pour une comparaison legacy

Le système ne permet pas :
- l’édition manuelle des chapitres dans le navigateur
- l’upload de `.xls` ou `.csv`
- l’import de libellés source et cible distincts dans le fichier
- l’archivage du classeur importé

## Modèle de données

La table `chapters` reste une donnée de navigation liée au `folder` de comparaison, pas une table de sommaire d’œuvre autonome.

Colonnes utilisées par l’import :
- `folder`
- `level`
- `label_source`
- `label_target`
- `chapter_parent`
- `start_line_source`
- `start_line_target`
- `id_tome_source`
- `id_tome_target`

Valeurs actuellement écrites par défaut :
- `label_source = label`
- `label_target = label`
- `id_tome_source = 0`
- `id_tome_target = 0`

## Contrat du fichier XLSX

Le contrôleur accepte uniquement :
- un fichier `.xlsx`
- taille maximale `5 Mo`

Le service d’import lit :
- la **deuxième feuille** du classeur
- uniquement les **4 premières colonnes**
- la **première ligne** comme en-tête

Ordre attendu des colonnes :
1. `level`
2. `label`
3. `start_line_source`
4. `start_line_target`

Les noms d’en-tête ne sont pas validés textuellement. Ce qui compte est la position des colonnes.

### Exemple minimal

```text
level | label             | start_line_source | start_line_target
1     | Première partie   | 1a                | 9
1.1   | I - Arrivée       | 1a                | 9
1.2   | II - Le voyage    | 2a                | 16
2     | Deuxième partie   | 10a               | 82
```

## Règles de parsing

Chaque ligne de données est `trim()`ée sur les 4 colonnes.

Une ligne entièrement vide est ignorée.

### `level`

Le niveau est obligatoire.

Formats acceptés :
- `1`
- `1.1`
- `1.2.3`
- plus généralement toute suite de segments non vides séparés par des points

Formats rejetés :
- niveau vide
- niveau avec segment vide, par exemple `1..2`

Parent inféré :
- parent de `1.2` = `1`
- parent de `1.2.3` = `1.2`

Contrainte importante :
- le parent doit avoir déjà été rencontré plus haut dans le fichier

### `label`

Le libellé est libre.

S’il est vide :
- la ligne est importable
- mais un avertissement est émis

Le même libellé est copié dans :
- `label_source`
- `label_target`

### `start_line_source` et `start_line_target`

Ces ancres sont stockées telles quelles après `trim()`.

Exemples usuels :
- `1a`
- `9`
- `14d`

Si une ancre est vide :
- la ligne reste importable
- un avertissement est émis

## Validation réelle

### Erreurs bloquantes

L’aperçu échoue si :
- le classeur ne peut pas être ouvert
- le classeur ne contient pas au moins deux feuilles
- la deuxième feuille ne peut pas être résolue
- il n’existe pas au moins une ligne d’en-tête et une ligne de données
- une ligne utile n’a pas de `level`
- un `level` est invalide
- un niveau enfant référence un parent non encore vu
- un niveau hiérarchique non racine est dupliqué
- aucune ligne exploitable n’est trouvée après filtrage

### Avertissements non bloquants

Des warnings sont remontés si :
- le libellé est vide
- l’ancre source est vide
- l’ancre cible est vide
- un niveau racine est répété

Important :
- un niveau racine répété comme `1`, puis plus loin `1` n’est pas bloquant
- il est importé comme entrée sœur et signalé par un warning

## Flux utilisateur actuel

### 1. Sélection de l’œuvre

Le panneau `Chapitres` reste inactif tant qu’aucune œuvre n’est sélectionnée.

### 2. Chargement des cibles

Route :
- `GET /chapters/targets?work_id={id}`

Le backend renvoie les comparaisons de l’œuvre :
- non-legacy modifiables
- legacy uniquement si elles ont déjà des lignes dans `chapters`

Chaque cible expose :
- `id`
- `folder`
- `label`
- `readonly`
- `chapter_count`

### 3. Cas legacy

Si la comparaison est legacy et possède déjà des chapitres :
- elle apparaît dans la liste
- elle est marquée lecture seule
- son contenu peut être chargé via :
  - `GET /chapters/{comparison}`
- aucun import n’est autorisé dessus

### 4. Prévisualisation

Route :
- `POST /chapters/import/preview`

Payload :
- `comparison_id`
- `file`

Si la comparaison est modifiable :
- le fichier est lu
- les lignes sont validées
- un aperçu JSON est renvoyé

Le backend met aussi en cache pendant `30 minutes` :
- l’identifiant de comparaison
- le `folder`
- les lignes préparées à l’import

La réponse contient :
- `token`
- `sheet_name`
- `header`
- `rows`
- `warnings`
- `summary.count`
- `summary.root_count`
- `summary.existing_count`

### 5. Commit

Route :
- `POST /chapters/import/commit`

Payload :
- `comparison_id`
- `token`

Le commit :
1. relit l’aperçu en cache
2. vérifie que le token correspond bien à la comparaison
3. supprime les anciennes lignes `chapters` du même `folder`
4. recrée les lignes dans une transaction
5. résout `chapter_parent` à partir du `parent_level`
6. supprime le token de cache

Le commit remplace donc entièrement les chapitres existants pour cette comparaison.

## Permissions

Lecture :
- admins autorisés
- non-admins autorisés s’ils ont un droit `edit` sur l’œuvre ou sur l’auteur

Écriture :
- admins autorisés
- non-admins soumis à `WorkPolicy::edit`
- comparaisons legacy toujours bloquées en écriture

## Comportement UI

Le panneau admin actuel :
- affiche la cible sélectionnée
- permet une prévisualisation explicite
- permet aussi un flux en deux clics sur `Importer les chapitres`
- affiche les warnings
- affiche le nombre de lignes existantes qui seraient remplacées

En lecture seule legacy :
- le panneau charge les lignes déjà stockées
- le bouton d’import reste désactivé

## Écarts avec l’ancienne documentation

Les points suivants ne sont plus des hypothèses mais l’état réel :
- l’import Laravel existe déjà
- il n’y a pas aujourd’hui de format source/target séparé dans le fichier
- il n’y a pas de table `chapter_imports`
- le classeur n’est pas archivé
- l’import est centré sur la comparaison, pas sur une “œuvre de destination” abstraite

## Recommandation documentaire

Ce document remplace les anciennes notes séparées :
- format Excel legacy
- flux d’import proposé

Il doit désormais servir de source de vérité tant que l’implémentation reste celle de [ChapterImportService.php](/Users/jganivet/Développement/variance2/laravel/app/Services/ChapterImportService.php:1) et [ChaptersController.php](/Users/jganivet/Développement/variance2/laravel/app/Http/Controllers/ChaptersController.php:1).

Phase 1 can use:
- `phpoffice/phpspreadsheet`

Reason:
- maintained
- better fit than carrying legacy XLSX reader code into Laravel

### Model cleanup

Before implementing the feature, the current Laravel `Chapter` model should be aligned with the real schema.

Current mismatch:
- [Chapter.php](/Users/jganivet/Développement/variance2/laravel/app/Models/Chapter.php:1)

That model currently does not reflect the actual `chapters` columns.

## Deferred Work

Good later improvements:
- explicit separate `label_source` / `label_target` columns in a new spreadsheet template
- export current DB state back to XLSX
- selective row editing in Laravel
- tree reordering UI
- marker existence checks against real rendered comparison data

## Bottom Line

Recommended implementation order:

1. align `Chapter` model with schema
2. build Laravel XLSX preview import
3. build commit/replace flow
4. add read-only tree inspection
5. only later consider a visual editor

That gives a safe operational replacement for the legacy XLS upload flow without overbuilding the first iteration.

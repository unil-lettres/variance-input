# Product Backlog

Ce document regroupe les demandes produit / UX qui ne relèvent ni du backlog
ops post-déploiement, ni du backlog spécifique de l’éditeur.

## Semaine du 8 mai 2026

### 1. Nettoyage des listes de transformations XHTML

Origine :
- retour Maxime après comparaisons sur Balzac / *Melmoth*

Symptômes décrits :
- le libellé `[retour ligne]` doit devenir le symbole de paragraphe `¶`
  dans les listes `d.xhtml`, `i.xhtml`, `r.xhtml`, `s.xhtml`
- les transformations constituées uniquement d’espaces (`[espace]`) ne doivent
  plus apparaître dans ces listes
- certains cas semblent correspondre à un remplacement d’espace par un retour
  ligne / nouveau paragraphe, qui doit être identifié de manière spécifique
- certains libellés incluent des espaces indésirables en début ou fin de mot,
  par exemple un rendu de ponctuation du type `hello ,`

Contexte technique initial :
- la génération actuelle est côté Medite Python, notamment
  `medite/app/variance/variance/tei_writer.py`
- `render_list_label_for_xhtml()` transforme explicitement les espaces seuls
  en `[espace]` et les retours ligne seuls en `[retour ligne]`
- les tests actuels de `medite/app/variance/tests/test_tei_writer.py`
  valident ce comportement, donc il s’agit d’un changement voulu de règle
  produit plutôt que d’un simple bug isolé

À faire :
- remplacer le rendu `[retour ligne]` par `¶` dans les listes XHTML finales
- filtrer les entrées dont le libellé normalisé est uniquement espace ou
  espace insécable
- définir et tester un libellé spécifique pour le cas espace → paragraphe
  ou paragraphe → espace
- auditer les libellés de substitution pour supprimer les espaces parasites
  avant ponctuation et aux frontières de mots, sans casser les espaces
  significatifs à l’intérieur des extraits
- ajouter des tests unitaires Medite sur ces cas avant de régénérer des
  comparaisons de contrôle

### 2. Suppression de version par un éditeur restreint

Symptôme décrit :
- un utilisateur de type `Editeur` n’a pas pu supprimer une version qu’il avait
  créée lui-même

Contexte technique initial :
- la suppression de version passe par `VersionController::destroy()`
- elle appelle `assertVersionEditable()`, qui repose sur
  `User::canEditVersion()`
- `canEditVersion()` demande le droit complet `edit` sur l’œuvre ; le droit
  restreint `version_editor` permet d’ouvrir/sauver l’éditeur de version mais
  pas de supprimer une version
- les versions ne portent pas aujourd’hui de champ `created_by`, contrairement
  aux comparaisons

À décider :
- soit conserver ce comportement et améliorer le message UI pour expliquer
  qu’un éditeur restreint ne peut pas supprimer
- soit ajouter une vraie règle de propriété des versions, avec migration
  `created_by`, attribution à l’import, et policy claire pour la suppression

### 3. Erreur éditeur sur Balzac / *La Cousine Bette*

Symptôme décrit :
- sur staging, les versions de Balzac / *La Cousine Bette* provoquent une
  erreur serveur à l’ouverture dans l’éditeur de versions
- cette œuvre a déjà un historique de cas « version fantôme »

À faire :
- reproduire directement sur staging ou récupérer l’erreur Laravel associée
- inspecter les versions, chemins XML, fac-similés, sidecars et cache éditeur
  pour cette œuvre
- vérifier si l’erreur vient d’un fichier XML absent/invalide, d’un sidecar
  incohérent, d’une version résiduelle référencée, ou d’un cache éditeur
  obsolète
- compléter le cas « version fantôme » ci-dessous avec le diagnostic réel

## Semaine du 20 avril 2026

### 1. Cas « version fantôme » sur *La Cousine Bette*

Origine :
- retour Maxime après imports/alignements sur *La Cousine Bette*

Symptôme décrit :
- une version « fantôme » reste visible après suppression/recréation de textes
- la version concernée était `Le Musée littéraire du Siècle (1847)`
- après réimport, le texte peut réapparaître en double :
  - sous la nouvelle version voulue
  - et sous l’entrée fantôme

Contexte probable :
- double téléversement initial du même TXT sous deux noms
- lancement d’un alignement
- suppression de l’alignement puis des TXT
- persistance d’une version encore retenue par un état applicatif ou un lien de
  comparaison

Objectif :
- permettre de repartir proprement du bon état sans laisser une entrée
  résiduelle confuse pour l’utilisateur

À faire :
- reproduire le scénario localement si possible
- préciser quel état doit être autorisé :
  - suppression réelle
  - archivage/masquage
  - rattachement/recréation guidée
- décider si une version sans texte mais encore référencée doit :
  - rester visible explicitement comme cassée
  - ou être masquée tant qu’elle ne peut pas être supprimée
- améliorer l’UI / le message associé si ce cas reste possible

### 2. Vérification des ajustements UI demandés par Maxime

Contexte :
- plusieurs problèmes d’affichage des tableaux ont déjà été corrigés

À garder en backlog :
- revalider après déploiement :
  - tableau `Versions`
  - tableau `Comparaisons`
  - comportement à fenêtre étroite
  - cohérence des boutons / pills / libellés compacts

## Remarque

Le backlog éditeur reste séparé dans :
- [editor_backlog.md](/Users/jganivet/Développement/variance2/descr/editor_backlog.md:1)

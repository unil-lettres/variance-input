# Variance Backlog

Ce document est la source unique pour les demandes produit, éditoriales, UX,
tests et exploitation qui restent ouvertes pour Variance.

Le cutover de `variance-input` en production a été validé le 2026-06-05.
L'ancien stack legacy reste disponible au moins jusqu'au 2026-07-01 pour
rollback/audit, mais les tâches pré-cutover terminées sont archivées en fin de
document.

## Ouvert

### 1. Nettoyage des listes de transformations XHTML

Origine :
- retour Maxime après comparaisons sur Balzac / *Melmoth*.

Symptômes décrits :
- le libellé `[retour ligne]` doit devenir le symbole de paragraphe `¶`
  dans les listes `d.xhtml`, `i.xhtml`, `r.xhtml`, `s.xhtml` ;
- les transformations constituées uniquement d'espaces (`[espace]`) ne doivent
  plus apparaître dans ces listes ;
- certains cas semblent correspondre à un remplacement d'espace par un retour
  ligne / nouveau paragraphe, qui doit être identifié de manière spécifique ;
- certains libellés incluent des espaces indésirables en début ou fin de mot,
  par exemple un rendu de ponctuation du type `hello ,`.

Contexte technique :
- génération côté Medite Python, notamment
  `medite/app/variance/variance/tei_writer.py` ;
- les tests actuels valident encore l'ancien comportement, donc il s'agit d'un
  changement produit à tester explicitement.

À faire :
- remplacer `[retour ligne]` par `¶` ;
- filtrer les entrées dont le libellé normalisé est uniquement espace ou espace
  insécable ;
- définir le libellé pour espace → paragraphe et paragraphe → espace ;
- nettoyer les espaces parasites aux frontières de mots et avant ponctuation ;
- ajouter des tests unitaires Medite.

### 2. Suppression de version par un éditeur restreint

Symptôme :
- un utilisateur de type éditeur ne peut pas supprimer une version créée par
  lui-même.

Contexte :
- `VersionController::destroy()` appelle `assertVersionEditable()` ;
- `User::canEditVersion()` demande le droit complet `edit` sur l'œuvre ;
- les versions ne portent pas aujourd'hui de champ `created_by`.

À décider :
- conserver le comportement et améliorer le message UI ;
- ou ajouter une vraie propriété des versions (`created_by`) avec migration,
  attribution à l'import et règle de suppression.

### 3. Cas « version fantôme » sur *La Cousine Bette*

Origine :
- retour Maxime après imports / alignements sur *La Cousine Bette*.

Symptômes :
- une version supprimée / recréée peut rester visible ;
- après réimport, le texte peut réapparaître en double.

À faire :
- reproduire localement ou diagnostiquer sur staging ;
- inspecter versions, chemins XML, fac-similés, sidecars et cache éditeur ;
- décider si une version sans texte mais encore référencée doit rester visible
  explicitement comme cassée, être masquée, ou être archivée ;
- améliorer les messages associés.

### 4. Vérification UI des tableaux `Versions` / `Comparaisons`

À revalider :
- tableau `Versions` ;
- tableau `Comparaisons` ;
- fenêtre étroite ;
- troncatures ;
- tailles de boutons ;
- pills / libellés compacts ;
- lisibilité des colonnes.

### 5. Messages d'erreur encore génériques

Objectif :
- remplacer les alertes JS génériques restantes par des messages exploitables.

À vérifier :
- import ;
- suppression ;
- publication partiellement réussie ;
- erreurs Medite ;
- erreurs de permissions.

### 6. Sélection d'œuvre réinitialisée par `Choisir l'œuvre`

Symptôme :
- quand on clique sur `Choisir l'œuvre`, les menus déroulants auteur et œuvre
  sont réinitialisés comme si l'utilisateur avait cliqué sur le bouton croix.

À faire :
- reproduire localement ;
- identifier si l'événement de sélection réutilise par erreur le chemin de
  remise à zéro ;
- vérifier le comportement avec :
  - aucun auteur sélectionné ;
  - auteur sélectionné sans œuvre ;
  - auteur et œuvre sélectionnés ;
  - retour depuis une URL `/select/{author}/{work}`.

### 7. Investiguer les `Avertissements de cohérence`

Contexte :
- des `Avertissements de cohérence` apparaissent notamment dans
  `Le Horla [démonstration]`.
- le cas whitespace-only `as_*` / `bi_*` a été corrigé pour les nouvelles
  comparaisons ; le cas restant connu concerne les transpositions cible
  `bd_*` générées sans contrepartie source `ad_*` et sans entrée `d.xhtml`.

À faire :
- reproduire sur le cas `Le Horla [démonstration]` ;
- lister précisément les avertissements affichés ;
- déterminer s'ils signalent :
  - un vrai problème de données ;
  - un artefact attendu du pipeline legacy / Medite ;
  - ou un contrôle trop strict / mal libellé ;
- corriger les données, la logique de contrôle ou le message selon le
  diagnostic.

### 8. Clarifier le flux éditorial pagination

Objectif :
- faire des balises `<pb>` la représentation éditoriale claire dans la version ;
- laisser le sidecar et les jobs gérer les marqueurs visuels de comparaison.

À faire :
- vérifier quels gestes UI créent aujourd'hui des marqueurs de pagination ;
- supprimer l'ambiguïté entre :
  - balise TEI `<pb .../>` ;
  - marqueur XHTML injecté `<span class="page-marker">...` ;
- documenter le flux recommandé dans `descr/workflow.md`.

### 9. Outil d'insertion / édition de `<pb>`

À faire :
- ajouter ou corriger un outil d'insertion `<pb .../>` ;
- définir les attributs réellement pris en charge :
  - `facs` ;
  - `pagination` ;
  - éventuellement `n` ;
- afficher une aide courte dans l'éditeur.

### 10. Vérification bout en bout `<pb>` → sidecar → comparaison

Recette à figer :
1. insertion de `<pb>` dans une version ;
2. sauvegarde ;
3. fusion ou création du sidecar depuis `<pb>` ;
4. comparaison ;
5. injection de pagination ;
6. vérification du rendu final.

### 11. Signalement UI de l'état pagination

Pistes :
- nombre de balises `<pb>` détectées ;
- présence / absence du sidecar ;
- divergence XML / sidecar ;
- actions explicites :
  - créer sidecar depuis `<pb>` ;
  - fusionner `<pb>` vers sidecar.

### 12. Nettoyage d'anciens marqueurs incohérents

À faire :
- auditer les contenus avec `page-marker` là où on attend du TEI ;
- décider s'il faut nettoyer automatiquement ou documenter les cas legacy.

### 13. Tests Medite autour des `<pb>`

À faire :
- ajouter des tests de non-régression sur des versions contenant `<pb>` ;
- vérifier ce qui survit dans les sorties intermédiaires du pipeline.

### 14. Recherche / remplacement dans l'éditeur de version

Origine :
- demande Maxime, 8 mai 2026.

À préciser :
- portée : document entier ou sélection ;
- remplacement simple ou expressions régulières ;
- prévisualisation / confirmation ;
- interaction avec sauvegarde, rechargement et contrôles XML.

### 15. Export des fichiers `_lignes`

Objectif :
- permettre l'export / téléchargement du fichier `_lignes` associé à une
  version quand il existe.

À faire :
- vérifier le comportement actuel du bouton / endpoint ;
- rendre l'action visible et compréhensible dans le tableau des versions ;
- tester fichier présent, fichier absent et utilisateur sans droit complet.

### 16. Outil exposant dans l'éditeur de version

Objectif :
- gérer les exposants sans conventions ad hoc de type `^...^`.

À faire :
- définir la représentation XML cible ;
- ajouter l'UI ;
- tester sauvegarde / réouverture.

### 17. Insertion d'image in-texte

À faire :
- définir la balise / les attributs cibles ;
- préciser le lien avec les fac-similés ;
- éviter les conventions `[Image]` dans les sources TXT.

### 18. Gestion des appels de notes

À faire :
- choisir la structure XML cible ;
- ajouter l'outil d'insertion / édition ;
- couvrir au moins un cas de sauvegarde et réouverture.

### 19. Test d'import de version enrichi

À couvrir :
- alinéas ;
- doubles espaces ;
- fins de ligne ;
- bords de fichier ;
- caractères invisibles ;
- normalisations legacy d'encodage et de ponctuation.

### 20. Recette manuelle éditeur

Parcours minimal :
1. ouvrir une version ;
2. modifier le XML ;
3. sauvegarder ;
4. rouvrir ;
5. vérifier pagination / fac-similés / comparaison selon le cas.

### 21. Test end-to-end du workflow éditorial

Périmètre visé :
1. création d'une version ;
2. import `_lignes` ;
3. création d'une comparaison ;
4. publication ou export ;
5. vérification minimale des artefacts attendus.

Le test doit rester ciblé et robuste, sans devenir une suite UI lourde.

### 22. Nettoyage des versions legacy et simplification de l'UI versions

Objectif :
- vérifier et nettoyer les versions legacy pour s'assurer que tous les textes
  attendus sont présents et reconstruisibles ;
- une fois ce contrôle terminé, supprimer les éléments d'interface devenus
  inutiles :
  - `Encodage` ;
  - `Reconstruire` ;
  - `Texte reconstruit depuis source`.

À faire :
- auditer les versions legacy importées et leurs fichiers source / XML ;
- identifier les versions sans texte, texte incomplet ou artefacts incohérents ;
- corriger ou documenter les cas restants ;
- seulement après validation du corpus legacy, retirer les actions UI qui
  servaient à compenser ces incertitudes.

## Récurrent / Exploitation

### 23. Nettoyage ponctuel des reliquats sur staging

Origine :
- bilan post-déploiement du 17 avril 2026.

À nettoyer après validation :
- `__medite_inputs/...` orphelins ;
- dossiers vides sous les arbres d'uploads ;
- restes de queue / staging ;
- failed jobs connus comme historiques.

### 24. Copie hors VM des backups quotidiens

À décider :
- copie automatique hors VM ;
- ou backup manuel hors VM avant les déploiements importants.

### 25. Vérification opérationnelle après chaque déploiement staging

À contrôler :
- `/health` ;
- `/admin/health/report` ;
- workers / scheduler ;
- migrations ;
- chemins legacy critiques ;
- backup DB ;
- assets admin `/admin/build/...` ;
- hard refresh navigateur si interface ancienne.

### 26. Convergence backups DB vers Laravel

Objectif :
- utiliser le scheduler Laravel comme source versionnée plutôt qu'un cron VM
  temporaire.

À faire :
- vérifier que `backup:database` et sa planification sont déployés ;
- confirmer un dump Laravel valide ;
- désactiver le cron temporaire si encore présent.

### 27. Annonce de maintenance avant gros déploiement

À faire :
- annoncer la fenêtre au moins 48 h à l'avance ;
- activer le splash admin avant migrations / recréations ;
- désactiver après validation finale.

### 28. Démarrage Medite et permissions

État :
- le sweep récursif de permissions a été limité dans `medite/entrypoint.sh`,
  mais le point reste à surveiller sur de gros arbres d'uploads.

À vérifier :
- redémarrage Medite rapide ;
- `/health` disponible rapidement ;
- aucune correction récursive coûteuse au démarrage.

## Terminé / Archivé

### A. Cutover production `variance-input`

État :
- terminé et validé le 2026-06-05 ;
- l'ancien stack legacy reste disponible au moins jusqu'au 2026-07-01.

### B. Masquer le menu `Site public` sur la page de connexion

État :
- terminé, testé localement, déployé en production le 2026-06-05.

### C. Clarifier les contrôles d'espace disque VM / NAS

État :
- terminé, testé localement, déployé en production le 2026-06-05 ;
- le rapport santé distingue stockage Laravel local et chemins NAS ;
- le préflight fac-similés utilise le chemin public uploads.

### D. Mettre à jour le message staging

État :
- terminé sur `plt-tst-1` le 2026-06-05 ;
- message affiché sur l'interface admin staging sans préfixe fixe
  `Maintenance annoncée`.

### E. Confirmer la mise en production à Maxime

État :
- email envoyé le 2026-06-05.

### F. Rendre Medite reproductible

État :
- terminé pour le besoin cutover ;
- `PYTHONHASHSEED=0` est défini dans le Dockerfile Medite et les compose dev,
  staging et production.

Note long terme :
- remplacer les usages de `hash()` Python par une clé stable explicite reste
  envisageable si une non-déterminisme résiduel est observé.

### G. Stabiliser l'état opérationnel staging pour le cutover

État :
- terminé pour le cutover ;
- les anciens `failed_jobs` connus ont été traités avant la promotion des
  données ;
- staging et production ont été validés verts avant/après cutover.

### H. Valider le corpus de pré-production

État :
- obsolète depuis le cutover validé ;
- le catalogue public production a été comparé à legacy avant validation.

### I. Contrôle XML avant Medite

État :
- implémenté ;
- l'éditeur refuse les XML invalides sans écraser le fichier existant ;
- Medite refuse les extractions texte vides avec une erreur explicite.

### J. Accès restreint à l'éditeur de versions

État :
- implémenté ;
- permission `version_editor` disponible ;
- routes et tests d'autorisation en place.

## Documents associés

- Plan de cutover production :
  [prod_cutover_variance_input_2026-05-22.md](/Users/jganivet/Développement/variance2/descr/prod_cutover_variance_input_2026-05-22.md:1)
- Workflow :
  [workflow.md](/Users/jganivet/Développement/variance2/descr/workflow.md:1)
- Fac-similés :
  [facsimiles.md](/Users/jganivet/Développement/variance2/descr/facsimiles.md:1)
- Carte du code Laravel :
  [laravel_current_code_map.md](/Users/jganivet/Développement/variance2/descr/laravel_current_code_map.md:1)

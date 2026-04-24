# Product Backlog

Ce document regroupe les demandes produit / UX qui ne relèvent ni du backlog
ops post-déploiement, ni du backlog spécifique de l’éditeur.

## Semaine du 20 avril 2026

### 1. Adaptation du catalogue : « Réécritures allographiques »

Origine :
- demande transmise par Maxime / direction éditoriale

Besoin :
- distinguer dans le catalogue un ensemble d’éditions atypiques
- pour le moment, la proposition est d’ajouter sous le dernier auteur une
  rubrique :
  - `Réécritures allographiques`

Contraintes / remarques :
- ne pas brouiller la ligne éditoriale principale du catalogue existant
- garder une solution simple dans un premier temps
- si ce sous-catalogue prend de l’ampleur, une autre présentation pourra être
  étudiée plus tard :
  - par exemple deux colonnes ou une vraie séparation visuelle

À faire :
- identifier le point de génération du catalogue public legacy
- définir comment marquer une œuvre / un auteur comme relevant de cette rubrique
- proposer une implémentation minimale compatible avec l’existant
- vérifier le rendu sur la page catalogue

### 2. Cas « version fantôme » sur *La Cousine Bette*

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

### 3. Vérification des ajustements UI demandés par Maxime

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

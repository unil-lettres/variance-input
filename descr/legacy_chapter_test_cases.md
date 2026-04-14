# Legacy Chapter Test Cases

This note lists imported legacy comparisons that already have chapter rows in the `chapters` table. These are useful as real-world references when redesigning the table-of-contents workflow in Laravel.

## Recommended Primary Test Cases

### `1mdm-2mdm`
- Comparison id: `37`
- Author: `Émile Zola`
- Work: `Les Mystères de Marseille (1867-1884)`
- Source version: `1mdm`
- Target version: `2mdm`
- Chapter rows: `64`
- Sample labels:
  - `Première partie`
  - `I - COMME QUOI BLANCHE DE CAZALIS S’ENFUIT AVEC PHILIPPE CAYOL`
  - `II - OÙ L’ON FAIT CONNAISSANCE DU HÉROS, MARIUS CAYOL`
  - `III - IL Y A DES VALETS DANS L’ÉGLISE`

Why it is useful:
- dense chapter structure
- labels exist on both sides
- good candidate for validating import, display, and navigation

### `2as-3as`
- Comparison id: `18`
- Author: `Honoré de Balzac`
- Work: `Albert Savarus (1842-1843)`
- Source version: `2as`
- Target version: `3as`
- Chapter rows: `61`
- Sample target labels:
  - `I. Madame Watteville.`
  - `II. Le Baron.`
  - `III. L’histoire commence.`
  - `IV. Le lion de Province.`

Why it is useful:
- many chapter rows
- asymmetric data: target labels present while source labels are empty
- good candidate for testing partial/misaligned TOC data

## Other Strong Cases

- `2mdm-3mdm`
  - Comparison id: `38`
  - Work: `Les Mystères de Marseille (1867-1884)`
  - Chapter rows: `66`

- `3mdm-4mdm`
  - Comparison id: `39`
  - Work: `Les Mystères de Marseille (1867-1884)`
  - Chapter rows: `66`

- `1vds-2vds`
  - Comparison id: `49`
  - Work: `La Vie d’un simple (1904-1945)`
  - Chapter rows: `59`

- `2vds-3vds`
  - Comparison id: `51`
  - Work: `La Vie d’un simple (1904-1945)`
  - Chapter rows: `59`

- `1pib1898-2pib1898`
  - Comparison id: `22`
  - Work: `Le Parfum des îles Borromées`
  - Chapter rows: `30`

- `2pib1898-3pib1902`
  - Comparison id: `23`
  - Work: `Le Parfum des îles Borromées`
  - Chapter rows: `30`

- `2pib1898-4pib1908`
  - Comparison id: `24`
  - Work: `Le Parfum des îles Borromées`
  - Chapter rows: `30`

## Current Data-Model Reminder

The imported legacy `chapters` rows are not a pure “work table of contents”.

They currently behave more like aligned navigation metadata tied to a comparison/work folder:
- `folder`
- `level`
- `label_source`
- `label_target`
- `chapter_parent`
- `start_line_source`
- `start_line_target`
- `id_tome_source`
- `id_tome_target`

This matters for future design decisions:
- some cases have labels on both sides
- some cases have labels only on one side
- line anchors are part of the stored structure, not just labels/hierarchy

## Suggested Use

When TOC work starts in Laravel, use at least:
- `1mdm-2mdm` as the main “rich and balanced” case
- `2as-3as` as the “asymmetric/partial data” case

That pair should expose most of the design constraints early.

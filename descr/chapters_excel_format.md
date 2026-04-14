# Legacy Chapters Excel Format

This note documents the chapter spreadsheet format expected by the legacy import path in `variance/backoff/chapitrage.php`.

## Example File

An example workbook is available here:

- [chapters_example_legacy.xlsx](/Users/jganivet/Développement/variance2/descr/examples/chapters_example_legacy.xlsx)

## How The Legacy Import Works

The legacy PHP importer:
- expects an `.xlsx` file
- reads the **second worksheet**
- ignores the **first row** as a header
- reads the first **4 columns**

Those columns are:

1. `level`
2. `label`
3. `start_line_source`
4. `start_line_target`

## Meaning Of Each Column

### `level`

Hierarchy encoded with dotted numbering:

- `1`
- `1.1`
- `1.2`
- `2`
- `2.3.1`

Parenthood is inferred from the dotted prefix:
- parent of `1.1` is `1`
- parent of `2.3.1` is `2.3`

So the system is hierarchical and can support more than two levels.

### `label`

Visible chapter title.

Examples:
- `Première partie`
- `I - Arrivée de l’héroïne`
- `I.1 - Le départ`

### `start_line_source`

Source-side line anchor used for navigation in the comparison.

Examples:
- `1a`
- `12`
- `14d`

### `start_line_target`

Target-side line anchor used for navigation in the comparison.

Examples:
- `9`
- `16`
- `36`

## Example Rows

```text
level | label                         | start_line_source | start_line_target
1     | Première partie               | 1a                | 9
1.1   | I - Arrivée de l’héroïne      | 1a                | 9
1.2   | II - Le voyage                | 2a                | 16
2     | Deuxième partie               | 10a               | 82
2.1   | I - Retour                    | 10a               | 82
2.1.1 | I.1 - Le soir de la rentrée   | 10c               | 84
```

## Important Limitation

The current imported Laravel `chapters` schema stores:
- `label_source`
- `label_target`
- `start_line_source`
- `start_line_target`

But the legacy importer code visible in the repo only documents a single `label` input column. That means the historical real spreadsheets may have evolved outside the exact code snapshot we have here.

So this example file is intentionally a **legacy-compatible minimum example**, not a final format recommendation for future Laravel TOC work.

# Chapters Import Flow In Laravel

This note proposes a first Laravel implementation for chapter management.

## Recommendation

Phase 1 should be:
- XLSX import in Laravel
- parsing preview before write
- explicit confirmation before replacement
- read-only inspection of imported chapter trees

Phase 1 should **not** be:
- a full visual tree editor
- drag and drop hierarchy editing
- freeform in-browser chapter authoring

The reason is simple:
- the `chapters` table already exists
- researchers already use spreadsheets
- the current operational gap is safe import and validation, not authoring flexibility

## Current Data Model

Existing table: `chapters`

Relevant columns:
- `folder`
- `level`
- `label_source`
- `label_target`
- `chapter_parent`
- `start_line_source`
- `start_line_target`
- `id_tome_source`
- `id_tome_target`

Important reminder:
- this is not a pure “work table of contents”
- it is legacy comparison/navigation data tied to a `folder`
- chapter jumps reuse existing rendered comparison markers

## Proposed Laravel Scope

### Goal

Allow a researcher to import a chapters spreadsheet for a selected legacy comparison/work context and replace the existing `chapters` rows safely.

### Non-goals for Phase 1

- in-browser tree editing
- mixed manual edits plus spreadsheet merge logic
- public-site redesign of TOC behavior
- chapter creation without a spreadsheet

## User Workflow

### 1. Choose target

User selects the target `folder` to receive chapter data.

Recommended UI:
- dedicated admin panel section for chapters
- target chosen from existing imported cases
- display associated author / work / source version / target version when known

### 2. Upload spreadsheet

User uploads `.xlsx`.

Accepted in Phase 1:
- `.xlsx` only

Rejected:
- `.xls`
- `.csv`
- malformed workbook

### 3. Parse and preview

Laravel parses the workbook but does not write DB rows yet.

Preview should show:
- selected target folder
- row count
- detected hierarchy
- first parsed rows
- validation errors and warnings

### 4. Confirm replacement

User confirms import.

Laravel then:
- deletes existing `chapters` rows for that folder
- inserts new rows in one transaction
- reports success with inserted row count

## Route Design

Suggested routes:

- `GET /chapters/import`
  - render import page
- `POST /chapters/import/preview`
  - upload + parse + validate
  - return preview payload
- `POST /chapters/import/commit`
  - confirm and replace rows
- `GET /chapters/{folder}`
  - read-only chapter tree view for one imported folder

All routes should require:
- `auth`

Recommended permission model:
- admins always allowed
- non-admin researchers allowed only when they own or can edit the related work

## Spreadsheet Contract

For legacy-compatible import, accept this core structure on the **second worksheet**:

1. `level`
2. `label`
3. `start_line_source`
4. `start_line_target`

Header row:
- first row ignored as headers

### Example

```text
level | label                       | start_line_source | start_line_target
1     | Première partie             | 1a                | 9
1.1   | I - Arrivée de l’héroïne    | 1a                | 9
1.2   | II - Le voyage              | 2a                | 16
2     | Deuxième partie             | 10a               | 82
```

## Parsing Rules

### `level`

Must be a dotted hierarchy token such as:
- `1`
- `1.1`
- `1.2.3`

Parent inference:
- parent of `1.2` is `1`
- parent of `1.2.3` is `1.2`

### `label`

Plain text label.

Phase 1 default mapping:
- `label_source = label`
- `label_target = label`

Reason:
- matches the minimum legacy format we can document reliably

### `start_line_source`
### `start_line_target`

Plain text marker labels used by chapter navigation.

Examples:
- `1a`
- `9`
- `14d`

They should be stored exactly as provided after trim.

## Validation Rules

### Hard errors

- workbook missing second worksheet
- missing header/data rows
- missing required columns
- empty `level`
- invalid hierarchy syntax in `level`
- child row whose parent level does not exist
- duplicate exact `level` values in same import

### Warnings

- empty label
- empty source anchor
- empty target anchor
- very long label
- level sequence gaps such as `1.3` without `1.2`

Warnings should not block preview by default.

## Persistence Rules

On commit:

1. begin transaction
2. delete existing `chapters` rows for target folder
3. insert new rows in level order
4. resolve `chapter_parent` ids from parsed hierarchy
5. commit

Stored defaults in Phase 1:
- `label_source = imported label`
- `label_target = imported label`
- `id_tome_source = 0`
- `id_tome_target = 0`

## Import Provenance

Recommended even in Phase 1:
- store the uploaded XLSX file in a private import archive
- keep metadata:
  - original filename
  - uploader
  - timestamp
  - target folder
  - row count

This can be done either by:
- a small `chapter_imports` table
- or a private JSON log plus archived file

Best option:
- `chapter_imports` table

## UI For Read-only Inspection

After import, Laravel should provide a simple tree view:
- level
- label
- source anchor
- target anchor

This view is important because it lets researchers verify the import result without opening the old public site logic.

## Test Cases To Use

Use these real imported legacy cases:

- `1mdm-2mdm`
  - rich balanced case
  - many rows
- `2as-3as`
  - asymmetric case
  - target labels populated while source labels may be empty

Reference:
- [legacy_chapter_test_cases.md](/Users/jganivet/Développement/variance2/descr/legacy_chapter_test_cases.md:1)

## Technical Notes

### Parsing library

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

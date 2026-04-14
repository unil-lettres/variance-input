# Chapters Example For `1pda-2pda`

This workbook is a real example for the `1pda-2pda` pair:

- file: [chapters_1pda_2pda.xlsx](/Users/jganivet/Développement/variance2/descr/examples/chapters_1pda_2pda.xlsx)
- source text: [demo_pda_partie1/versions/1pda.txt](/Users/jganivet/Développement/variance2/demo_pda_partie1/versions/1pda.txt:1)
- target text: [demo_pda_partie1/versions/2pda.txt](/Users/jganivet/Développement/variance2/demo_pda_partie1/versions/2pda.txt:1)
- source anchors: [demo_pda_partie1/_lignes/1pda_lignes.txt](/Users/jganivet/Développement/variance2/demo_pda_partie1/_lignes/1pda_lignes.txt:1)
- target anchors: [demo_pda_partie1/_lignes/2pda_lignes.txt](/Users/jganivet/Développement/variance2/demo_pda_partie1/_lignes/2pda_lignes.txt:1)

It models only `PREMIÈRE PARTIE` and the chapters present in this extracted pair:

| level | label | start_line_source | start_line_target |
| --- | --- | --- | --- |
| `1` | `Premiere partie` | `1a` | `1` |
| `1.1` | `I` | `1a` | `1` |
| `1.2` | `II` | `3a` | `15` |
| `1.3` | `III` | `5d` | `30` |
| `1.4` | `IV` | `8c` | `46` |
| `1.5` | `V` | `10f` | `63` |

Notes:

- `I`, `II`, and `III` align cleanly with the first obvious in-chapter markers on both sides.
- `IV` and `V` do not have a source-side marker exactly on the heading line in `1pda`, so the workbook uses the first available source marker inside the chapter (`8c` and `10f`).
- On the target side, `IV` and `V` do have direct opening anchors (`46` and `63`).

This makes the file suitable as a realistic import example for the current legacy-compatible Laravel flow.

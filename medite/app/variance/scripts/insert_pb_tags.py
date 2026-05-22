#!/usr/bin/env python3
"""
insert_pb_tags.py
-----------------
Add <pb n="…" facs="…"/> elements to a TEI/HTML/XML text, using a
tab-delimited “lines” file whose rows are

    facs<TAB>page<TAB>first words of first line

The script is tolerant of:
  • upper/lower-case differences
  • accent / diacritic differences
  • multiple spaces or line-breaks
  • duplicated line beginnings (it chooses the next occurrence AFTER the
    previous <pb>, so pages stay in order).
"""

import csv
import unicodedata
import re
import sys
from pathlib import Path
from typing import List, Tuple


# ---------------------------------------------------------------------------
# helpers
# ---------------------------------------------------------------------------

def normalise(s: str) -> str:
    """
    Case-fold, strip accents/diacritics and collapse whitespace.

    We keep only ASCII letters/numbers/punctuation to improve fuzzy matching.
    """
    s = unicodedata.normalize("NFD", s.casefold())
    s = ''.join(c for c in s if unicodedata.category(c) != 'Mn')      # strip accents
    s = re.sub(r'\s+', ' ', s)                                        # collapse ws
    return s.strip()


def load_lines_file(path: Path) -> List[Tuple[str, str, str, str]]:
    """
    Return a list of tuples: (facs, page, first_words, first_words_norm)
    – skipping blank rows and rows without a ‘first words’ column.
    """
    rows = []
    with path.open(encoding="utf-8") as tsv:
        rdr = csv.reader(tsv, delimiter='\t')
        for raw in rdr:
            if len(raw) < 3 or not raw[2].strip():
                continue
            facs, page, first = raw[0].strip(), raw[1].strip(), raw[2].strip()
            rows.append((facs, page, first, normalise(first)))
    return rows


def insert_at(text: str, pos: int, insertion: str) -> Tuple[str, int]:
    """
    Insert *insertion* at index *pos* in *text* and return
    (new_text, new_cursor_pos_after_insertion).
    """
    return text[:pos] + insertion + text[pos:], pos + len(insertion)


# ---------------------------------------------------------------------------
# main logic
# ---------------------------------------------------------------------------

def main(lines_file: str, input_xml: str, output_xml: str):
    entries = load_lines_file(Path(lines_file))

    # read the whole input as one string – keep tags as they are
    raw_text = Path(input_xml).read_text(encoding="utf-8")

    # we search in a *normalised* copy so diacritics/case don’t hurt us,
    # but keep track of the original indices so we can insert safely.
    norm_text = normalise(raw_text)

    # Map from normalised index → original-text index
    # We build this once for speed.
    norm_to_raw_idx = []
    j = 0
    for i, ch in enumerate(raw_text):
        if unicodedata.category(ch) == 'Mn':
            continue                    # skipped in normalised
        if ch.isspace():
            if norm_text[j:j+1] == ' ': # collapsed ws once
                norm_to_raw_idx.append(i)
                j += 1
            continue
        norm_to_raw_idx.append(i)
        j += 1
    # `norm_to_raw_idx[k]` is the raw-text index that produced norm_text[k]

    cursor_norm = 0                     # where the *next* search will start
    modified_text = raw_text            # we rebuild this with insertions
    offset = 0                          # keeps raw-text offset caused by inserts

    for facs, page, first, first_norm in entries:
        # Look forward from cursor_norm for best match of first_norm
        found_at = norm_text.find(first_norm, cursor_norm)

        if found_at == -1:
            # fall back: search ahead a few pages (up to 3000 norm chars)
            # using fuzzy ratio on sliding window of len≈len(first_norm)+30
            window = 3000
            search_zone = norm_text[cursor_norm:cursor_norm+window]
            best_pos = None
            best_score = 0.0
            target_len = len(first_norm)
            for i in range(len(search_zone)-target_len):
                slice_ = search_zone[i:i+target_len+10]
                # quick ratio: proportion of first_norm present in slice_
                common = sum(1 for a,b in zip(first_norm, slice_) if a == b)
                score = common / target_len
                if score > best_score:
                    best_score, best_pos = score, i
            if best_score > 0.6:                        # empirical threshold
                found_at = cursor_norm + best_pos
            else:
                print(f'⚠️  No match for page {page} (“{first}”). Skipped.')
                continue

        # Raw-text insertion point BEFORE offset modification
        raw_insert_pos = norm_to_raw_idx[found_at] + offset

        pb_tag = f'<pb n="{page}" facs="{facs}"/>'
        modified_text, new_cursor_raw = insert_at(modified_text, raw_insert_pos, pb_tag)

        # Update offset (+len(pb_tag)) & cursor_norm (skip over the inserted area)
        offset += len(pb_tag)
        cursor_norm = found_at + len(first_norm)  # next page must be later

    Path(output_xml).write_text(modified_text, encoding="utf-8")
    print(f'✅  Finished.  Output written to {output_xml}')


if __name__ == "__main__":
    if len(sys.argv) != 4:
        sys.exit("Usage:\n  python insert_pb_tags.py  lines.tsv  input.xml  output.xml")
    main(sys.argv[1], sys.argv[2], sys.argv[3])

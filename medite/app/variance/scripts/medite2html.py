#!/usr/bin/env python3
"""
medite2html.py
==============

Generate a side-by-side HTML visualisation of two texts plus Medite
change-lists (s, d, r, i).  Only the *source* and *target* files are
required; Medite lists are optional.

Written for Python 3.8+  •  2025-05-28
"""

from pathlib import Path
import re
import argparse
import html
from typing import List, Tuple, Dict


CSS = """
body      {font-family: "Helvetica Neue", Arial, sans-serif; margin:0;}
.container{display:flex; height:100vh;}
.column   {width:50%; padding:1.2em; overflow:auto; box-sizing:border-box;}
.column pre{white-space:pre-wrap; line-height:1.4;}

.del      {background:#ffd9d9;}
.ins      {background:#d9ffd9;}
.sub      {background:#d9d9ff;}
.rep      {background:#ffeed9;}

header    {padding:.6em 1.2em; background:#222; color:#fff;}
h1        {margin:0; font-size:1.1rem;}
"""

HTML_HEAD = """<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Comparaison</title>
<style>{css}</style>
</head>
<body>
<header><h1>Comparaison des textes&nbsp;: source ⟷ cible</h1></header>
<div class="container">
""".format(css=CSS)

HTML_TAIL = """
</div></body></html>
"""


# ----------------------------------------------------------------------
# 1.  Utilities
# ----------------------------------------------------------------------

def unaccent(text: str) -> str:
    """Simple accent-insensitive comparison helper (NFD)."""
    import unicodedata as ud
    return ''.join(c for c in ud.normalize("NFD", text.lower())
                   if ud.category(c) != "Mn")


ID_RE = re.compile(r'lb[dsri]_[^_]+_(\d+)_(\d+)', re.I)


def parse_medite_file(path: Path, change_type: str) -> List[Tuple[int, int]]:
    """
    Return a list of (start, end) integer ranges extracted from the <li>
    anchors inside a Medite s/d/r/i list.
    """
    ranges = []
    if not path:
        return ranges
    data = path.read_text(encoding="utf-8")
    for m in ID_RE.finditer(data):
        start, end = int(m.group(1)), int(m.group(2))
        ranges.append((start, end))
    ranges.sort()
    return ranges


def apply_spans(text: str, ranges: Dict[str, List[Tuple[int, int]]]) -> str:
    """
    Given the raw text *without* tags and four dictionaries mapping
    "del"/"ins"/"sub"/"rep" → [(s,e),…], return HTML with <span class="">
    wrappers inserted.  We work on *character indices* (as Medite does).
    No ranges are allowed to overlap.
    """
    tags_open = {i: [] for i in range(len(text)+1)}
    tags_close = {i: [] for i in range(len(text)+1)}

    for ctype, rngs in ranges.items():
        for s, e in rngs:
            tags_open[s].append(f'<span class="{ctype}">')
            tags_close[e].append('</span>')

    out = []
    for i, ch in enumerate(text):
        if tags_close[i]:                 # close first (nesting LIFO)
            out.extend(tags_close[i][::-1])
        if tags_open[i]:
            out.extend(tags_open[i])
        out.append(html.escape(ch))

    # close any tail tags at the end
    if tags_close[len(text)]:
        out.extend(tags_close[len(text)][::-1])

    return ''.join(out)


# ----------------------------------------------------------------------
# 2.  main
# ----------------------------------------------------------------------

def main():
    ap = argparse.ArgumentParser(
        description="Build a HTML side-by-side view of two texts plus Medite diff lists.")
    ap.add_argument("source", help="plain-text source file")
    ap.add_argument("target", help="plain-text target file")
    ap.add_argument("-d", "--deletions", help="Medite deletions .html/.xhtml")
    ap.add_argument("-i", "--insertions", help="Medite insertions file")
    ap.add_argument("-s", "--substitutions", help="Medite substitutions file")
    ap.add_argument("-r", "--replacements", help="Medite replacements file")
    ap.add_argument("-o", "--output", default="comparison.html",
                    help="output HTML (default: comparison.html)")
    args = ap.parse_args()

    src = Path(args.source).read_text(encoding="utf-8")
    tgt = Path(args.target).read_text(encoding="utf-8")

    ranges_src = {
        "del": parse_medite_file(Path(args.deletions), "del") if args.deletions else [],
        "sub": parse_medite_file(Path(args.substitutions), "sub") if args.substitutions else [],
        "rep": parse_medite_file(Path(args.replacements), "rep") if args.replacements else []
    }
    ranges_tgt = {
        "ins": parse_medite_file(Path(args.insertions), "ins") if args.insertions else [],
        "sub": parse_medite_file(Path(args.substitutions), "sub") if args.substitutions else [],
        "rep": parse_medite_file(Path(args.replacements), "rep") if args.replacements else []
    }

    html_left  = apply_spans(src, ranges_src)
    html_right = apply_spans(tgt, ranges_tgt)

    out_path = Path(args.output)
    with out_path.open("w", encoding="utf-8") as f:
        f.write(HTML_HEAD)
        f.write('<div class="column" id="source"><pre>')
        f.write(html_left)
        f.write('</pre></div>\n')
        f.write('<div class="column" id="target"><pre>')
        f.write(html_right)
        f.write('</pre></div>')
        f.write(HTML_TAIL)

    print(f"✔  Output written to  {out_path.resolve()}")

# ----------------------------------------------------------------------

if __name__ == "__main__":
    main()

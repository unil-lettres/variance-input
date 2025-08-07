#!/usr/bin/env python3
"""
probe_mismatch.py
─────────────────
Locate the *first* byte where Medite’s reconstructed target diverges
from the true target extracted via xml2txt(), so you can decide which
normalisation step is missing.

Usage
-----
python probe_mismatch.py  SOURCE.xml  TARGET.xml
"""
from __future__ import annotations
import sys, pathlib, textwrap

from variance.io_helpers import xml2txt
from variance.medite   import medite as md
from variance.diff_core import (
    calc_revisions,            # main runner
    _translate_medite_tuple,   # helper present in diff_core.py
    BC, S, I, DA, DB, R,
)

# ----------------------------------------------------------------------
# tiny Δ re-applier -----------------------------------------------------
def reapply_deltas(src: str, deltas) -> str:
    out, idx = [], 0
    for d in deltas:
        if isinstance(d, (S, DA)):                   # deletions in source
            out.append(src[idx:d.start]); idx = d.end
        elif isinstance(d, (I, DB)):                 # insertions / moves
            out.append(src[idx:d.start]); out.append(src[d.start:d.end]); idx = d.start
        elif isinstance(d, (BC, R)):                 # keep as-is
            pass
    out.append(src[idx:])
    return "".join(out)

# ----------------------------------------------------------------------
# CLI ------------------------------------------------------------------
if len(sys.argv) != 3:
    sys.exit("usage: probe_mismatch.py SRC.xml TGT.xml")

p_src, p_tgt = map(pathlib.Path, sys.argv[1:3])
z1, z2       = xml2txt(p_src), xml2txt(p_tgt)

params = md.Parameters(
    lg_pivot=7,
    ratio=15,
    seuil=50,
    car_mot=2,
    sep=" ",
    case_sensitive=True,
    diacri_sensitive=True,
    sep_sensitive=True,
    algo="HIS",
)

try:
    res = calc_revisions(z1.txt, z2.txt, params)
except AssertionError:
    # ------------------------------------------------------------------
    # Re-run Medite manually and translate tuples on the fly
    # ------------------------------------------------------------------
    appli   = md.DiffTexts(z1.txt, z2.txt, parameters=params)
    N       = len(z1.txt)
    deltas  = [_translate_medite_tuple(t, N, z2.txt) for t in appli.bbl.liste]
    rebuilt = reapply_deltas(z1.txt, deltas)

    # locate first differing byte
    pos = next((i for i,(a,b) in enumerate(zip(rebuilt, z2.txt)) if a!=b),
               min(len(rebuilt), len(z2.txt)))

    def ctx(s: str, p: int, w: int=40) -> str:
        frag = s[max(0,p-w):p+w].replace("\n","⏎")
        return textwrap.shorten(frag, width=2*w+8, placeholder="…")

    print(f"First mismatch at byte {pos}")
    print("rebuilt :", ctx(rebuilt, pos))
    print("target  :", ctx(z2.txt,  pos))
    sys.exit(1)

print("Probe succeeded: no reconstruction mismatch.")

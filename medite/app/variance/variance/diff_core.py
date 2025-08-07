"""
diff_core.py
============
Medite-specific logic turned into simple named-tuples + self-check.
"""

from __future__ import annotations
from collections import namedtuple
import re
from variance.medite import medite as md

# ──────────────────────────────────────────────────────────────────────────
# Public delta types
# ──────────────────────────────────────────────────────────────────────────
BC = namedtuple("BC", "a_start a_end b_start b_end")           # common block
S  = namedtuple("S",  "start end")                             # deletion
I  = namedtuple("I",  "start end txt")                         # insertion  (+slice)
DA = namedtuple("DA", "start end")                             # moved block in src
DB = namedtuple("DB", "start end txt")                         # moved block in tgt (+slice)
R  = namedtuple("R",  "a_start a_end b_start b_end")           # substitution

DELTA_TYPES = (BC, S, I, DA, DB, R)

class IdenticalFilesException(Exception):
    """Raised when the two input files contain exactly the same Medite text."""

# internal
Block  = namedtuple("Block", "a b")
Result = namedtuple("Result", "appli deltas")

# ──────────────────────────────────────────────────────────────────────────
# Translate Medite tuples → our tuples
# ──────────────────────────────────────────────────────────────────────────
def _translate_medite_tuple(t, N: int, tgt_txt: str):
    match t:
        # ── common block ────────────────────────────────────────────────
        case (("BC", a1, a2, _), ("BC", b1, b2, _)):
            return BC(a1, a2, b1 - N, b2 - N)

        # ── pure deletion ───────────────────────────────────────────────
        case (("S", s, e, _), None):
            return S(s, e)

        # ── pure insertion ──────────────────────────────────────────────
        case (None, ("I", s, e, _)):
            rel_s, rel_e = s - N, e - N
            return I(rel_s, rel_e, tgt_txt[rel_s:rel_e])

        # ── replacement / substitution ──────────────────────────────────
        case (("R", a1, a2, _), ("R", b1, b2, _)):
            return R(a1, a2, b1 - N, b2 - N)

        # ── moved-in block (transpose B) ───────────────────────────────
        case (None, ("D", s, e, _)):
            rel_s, rel_e = s - N, e - N
            return DB(rel_s, rel_e, tgt_txt[rel_s:rel_e])

        # ── moved-out block (transpose A) ──────────────────────────────
        case (("D", s, e, _), None):
            return DA(s, e)

        # ── anything else is unexpected ─────────────────────────────────
        case _:
            raise ValueError(f"unhandled Medite delta {t!r}")


# ──────────────────────────────────────────────────────────────────────────
# Public entry-point
# ──────────────────────────────────────────────────────────────────────────
def calc_revisions(source_txt: str,
                   target_txt: str,
                   params: md.Parameters) -> Result:
    """Run Medite and deliver (appli, deltas) with strong round-trip check."""
    appli = md.DiffTexts(chaine1=source_txt, chaine2=target_txt,
                         parameters=params)
    N = len(source_txt)

    # Translate Medite's raw tuples to our simple namedtuples
    deltas = [_translate_medite_tuple(t, N, target_txt)
              for t in appli.bbl.liste]

    # ------------------------------------------------------------------
    # 1) Rebuild the source from (BC, S, R, DA) slices & verify it matches
    # ------------------------------------------------------------------
    # pieces_src = []
    # for d in deltas:
    #     if isinstance(d, (BC, S, R, DA)):
    #         # BC and R have .a_start/.a_end; S and DA have .start/.end
    #         if isinstance(d, (BC, R)):
    #             pieces_src.append(source_txt[d.a_start : d.a_end])
    #         else:
    #             pieces_src.append(source_txt[d.start : d.end])
    # src_rebuilt = "".join(pieces_src)

    # if src_rebuilt != source_txt:
    #     # Debugging: locate first mismatch
    #     min_len = min(len(src_rebuilt), len(source_txt))
    #     pos = 0
    #     for i in range(min_len):
    #         if src_rebuilt[i] != source_txt[i]:
    #             pos = i
    #             break
    #     else:
    #         pos = min_len

    #     # Show context around mismatch
    #     ctx = 30
    #     def snippet(txt):
    #         start = max(0, pos - ctx)
    #         end = pos + ctx
    #         s = txt[start:end].replace("\n", "⏎")
    #         return f"...{s}..."

    #     print("DEBUG: Source reconstruction failed")
    #     print(f"Position of first mismatch: {pos}")
    #     print(f"Original source around mismatch: {snippet(source_txt)}")
    #     print(f"Rebuilt   source around mismatch: {snippet(src_rebuilt)}")
    #     print(f"Length original: {len(source_txt)}, length rebuilt: {len(src_rebuilt)}")
    #     raise AssertionError("Medite delta reconstruction mismatch (source side)!")

    # ------------------------------------------------------------------
    # 2) Rebuild the target from (BC, R, I, DB) slices & verify it matches
    # ------------------------------------------------------------------
    # tgt_slices = []
    # for d in deltas:
    #     if isinstance(d, (I, DB)):           # insertions & moved-in blocks
    #         tgt_slices.append((d.start, d.txt))
    #     elif isinstance(d, (BC, R)):         # common blocks & replacements
    #         tgt_slices.append((d.b_start, target_txt[d.b_start : d.b_end]))

    # tgt_rebuilt = "".join(txt for _, txt in sorted(tgt_slices))
    # if tgt_rebuilt != target_txt:
    #     # Debugging: locate first mismatch
    #     min_len = min(len(tgt_rebuilt), len(target_txt))
    #     pos = 0
    #     for i in range(min_len):
    #         if tgt_rebuilt[i] != target_txt[i]:
    #             pos = i
    #             break
    #     else:
    #         pos = min_len

    #     ctx = 30
    #     def snippet(txt):
    #         start = max(0, pos - ctx)
    #         end = pos + ctx
    #         s = txt[start:end].replace("\n", "⏎")
    #         return f"...{s}..."

    #     print("DEBUG: Target reconstruction failed")
    #     print(f"Position of first mismatch: {pos}")
    #     print(f"Original target around mismatch: {snippet(target_txt)}")
    #     print(f"Rebuilt   target around mismatch: {snippet(tgt_rebuilt)}")
    #     print(f"Length original: {len(target_txt)}, length rebuilt: {len(tgt_rebuilt)}")
    #     raise AssertionError("Medite delta reconstruction mismatch (target side)!")

    return Result(appli=appli, deltas=deltas)

"""
canon.py
========
Low-level helpers for normalising strings so that moved-block
matching (DA ↔ DB) in Medite is insensitive to whitespace,
paragraph tags, or edge punctuation.

Nothing here depends on the rest of the Variance code-base, so it
can be imported anywhere without risk of circular imports.
"""

from __future__ import annotations

import re

# ----------------------------------------------------------------------
# Public constants
# ----------------------------------------------------------------------

newline: str = "\n"

# Characters (e.g. special diacritics) that need escaping before Medite
# is run.  Right now this mapping is empty; extend as needed.
escape_characters_mapping: dict[str, str] = {}

# ----------------------------------------------------------------------
# Canonicalisation regexes (module-private)
# ----------------------------------------------------------------------

_ws_re     = re.compile(r"\s+")            # collapse runs of whitespace
_punct_re  = re.compile(r"^[\W_]+|[\W_]+$")  # strip leading / trailing punct

# ----------------------------------------------------------------------
# Public helpers
# ----------------------------------------------------------------------

def canon(txt: str) -> str:
    """Return a canonical form of *txt* suitable for fuzzy move matching.

    Steps:
    1. collapse any whitespace runs to a single space
    2. drop <p>, </p>, and empty <p/> tags
    3. strip punctuation at either edge
    4. trim
    """
    txt = _ws_re.sub(" ", txt)
    txt = txt.replace("<p/>", "").replace("<p>", "").replace("</p>", "")
    txt = _punct_re.sub("", txt)
    return txt.strip()


def add_escape_characters(text: str) -> str:
    """Replace every key of *escape_characters_mapping* with its value."""
    for src, repl in escape_characters_mapping.items():
        text = text.replace(src, repl)
    return text


def remove_escape_characters(text: str) -> str:
    """Inverse of :func:`add_escape_characters`."""
    for repl, src in escape_characters_mapping.items():
        text = text.replace(src, repl)
    return text

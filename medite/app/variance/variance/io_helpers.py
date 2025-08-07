# io_helpers.py
from __future__ import annotations

import pathlib
from collections import namedtuple

from bs4 import BeautifulSoup

from variance.canon import newline, add_escape_characters, remove_escape_characters
from variance import operations as op   # your existing operations.py

# ----------------------------------------------------------------------
# Data container returned by xml2txt
# ----------------------------------------------------------------------

Output = namedtuple("Output", "id txt soup rchanges path path_txt")

# ----------------------------------------------------------------------
# Helper functions
# ----------------------------------------------------------------------

def read(fp: pathlib.Path) -> BeautifulSoup:
    """Parse *fp* with the XML parser flavour of BeautifulSoup."""
    return BeautifulSoup(fp.read_text("utf-8"), "xml")


def remove_emph_tags(txt: str) -> str:
    """Turn <emph>word</emph> into \\word\\ so Medite keeps the info."""
    return txt.replace("<emph>", "\\").replace("</emph>", "\\")


def add_emph_tags(txt: str) -> str:
    """Inverse of :func:`remove_emph_tags`."""
    return txt.replace("\\", "<emph>", 1).replace("\\", "</emph>", 1)


def remove_medite_annotations(txt: str) -> str:
    """Drop escape characters and newlines from a Medite-annotated string."""
    txt = txt.replace(newline, "")
    txt = remove_escape_characters(txt)
    return txt


def xml2txt(fp: pathlib.Path) -> Output:
    """
    Extract the <body> text of *fp*, annotate it for Medite via
    variance.operations.xml2medite(), save a *.medite.txt* file next
    to the original, and return an Output tuple.

    IMPORTANT:
    — If <div> elements are present under <body>, we concatenate each <div> 
      exactly as XML (so any <lb/> or <app/> tags survive).
    — Otherwise, fall back to concatenating *each* <p>’s inner content 
      (via decode_contents()), preserving ALL newlines, spaces, and any 
      literal backslashes or dashes. This guarantees that what Medite sees 
      is byte‐for‐byte the same as what we reconstruct for its sanity‐check.
    """
    soup = read(fp)
    body = soup.find("body")

    # 1) First try: gather raw XML of every <div> under <body>
    divs = body.find_all("div")
    if divs:
        raw = "".join(str(div) for div in divs)
    else:
        # 2) Fallback: no <div> tags → gather the inner content of every <p>
        #
        #   We call decode_contents() rather than get_text() so that
        #   we pull exactly what appears between <p>…</p>—including blank lines,
        #   literal “—” or “–”, any backslashes (\Acta sanctorum\), etc.
        #
        raw_parts: list[str] = []
        for p in body.find_all("p"):
            # decode_contents() gives you the exact serialized contents
            # of <p>…</p> (no outer <p> wrappers), including newlines.
            raw_parts.append(p.decode_contents())
            # After each </p>, we append a newline, to simulate that <p> blocks
            # really did have a trailing line‐break in the original .txt.
            raw_parts.append("\n")
        raw = "".join(raw_parts)

    # 3) Now that we have the raw text‐like content, send it to Medite’s xml2medite
    medite_obj = op.xml2medite(raw)
    txt        = medite_obj.text

    # 4) Write out the *.medite.txt* debug file alongside the .xml
    txt_file   = fp.with_suffix(".medite.txt")
    txt_file.write_text(txt, "utf-8")

    return Output(
        id       = soup.find("TEI")["xml:id"],
        txt      = txt,
        soup     = soup,
        rchanges = op.reverse_transform(medite_obj),
        path     = fp,
        path_txt = txt_file,
    )

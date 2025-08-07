"""processing.py – orchestrator
Keeps Medite diff generation under ±180 lines and now **converts every raw line‑feed to an explicit `<lb/>`** so line breaks survive all later serialisations.
"""
from __future__ import annotations

import logging
import pathlib
import xml.etree.ElementTree as ET
import re
from collections import defaultdict

from tqdm import tqdm

from variance import operations as op
from variance.medite import medite as md  # Params type only
from variance.canon import canon
from variance.io_helpers import xml2txt
from variance.diff_core import (
    calc_revisions, BC, S, I, DA, DB, R, IdenticalFilesException,
)
from variance.tei_writer import (
    build_header, ops2xhtml, add_list_xml, add_list_xhtml, add_main_xhtml,
)
from variance.xhtml_writer import (
    write_xhtml_lists, write_xhtml_mains, saxon_transform,
)

logger = logging.getLogger(__name__)

# ----------------------------------------------------------------------
# helpers ---------------------------------------------------------------
# ----------------------------------------------------------------------

def _lbise(txt: str) -> str:
    """Replace every `\n` by a literal `<br/>` so line breaks are kept in
    the TEI body and downstream XHTML views.  Note: *no* additional attrs –
    the plain tag is enough for rendering and for later post‑processing."""
    return txt.replace("\n", "<br/>")

import re

# def markup_inline_tags(txt: str) -> str:
#     # 1. Replace \italics\ with <i>italics</i>
#     txt = re.sub(r'\\([^\\\n]+)\\', r'<i>\1</i>', txt)

#     # 2. Replace exponents like XIV^e^ by XIV<sup>e</sup>
#     txt = re.sub(r'\^([^^\n]+)\^', r'<sup>\1</sup>', txt)

#     # 3. Remove any stray backslashes (start or end of word)
#     txt = re.sub(r'\\(?=\s|$)', '', txt)
#     txt = re.sub(r'(?:^|\s)\\', lambda m: m.group(0).rstrip("\\"), txt)

#     return txt

# ----------------------------------------------------------------------
# process() – single public entry‑point
# ----------------------------------------------------------------------

def process(
    source_filepath: pathlib.Path,
    target_filepath: pathlib.Path,
    parameters:      md.Parameters,
    output_filepath: pathlib.Path,
    xhtml_output_dir: pathlib.Path,
) -> list[pathlib.Path]:
    """Generate a TEI diff file + companion XHTML snippets."""

    # --------------------------------------------------------------
    # 0. Load & sanity‑check
    # --------------------------------------------------------------
    z1, z2 = xml2txt(source_filepath), xml2txt(target_filepath)
    if z1.txt == z2.txt:
        raise IdenticalFilesException("source and target are identical")

    res = calc_revisions(z1.txt, z2.txt, parameters)
    moved_tgt = {canon(z2.txt[d.start : d.end]): d for d in res.deltas if isinstance(d, DB)}

    # --------------------------------------------------------------
    # 1. TEI skeleton & helper collections
    # --------------------------------------------------------------
    root = build_header(z1, z2)
    medite_data = ET.SubElement(root, "mediteData")
    ops2xml = {n: ET.SubElement(medite_data, f"list{n.title()}")
               for n in ("deletion", "addition", "transpose", "substitution")}

    xhtml_lists: dict[str, list[str]] = defaultdict(list)
    xhtml_mains: dict[str, list[str]] = defaultdict(list)
    zbody = ""  # diff‑annotated body will accumulate here

    # wrappers -----------------------------------------------------
    def add_list(z, start, end, attr, name, suffix):
        add_list_xml(ops2xml, z, start, end, attr, name)
        add_list_xhtml(xhtml_lists, z, start, end, name, suffix)

    def slice_fmt(z, start, end):
        """Extract and lb‑ise the slice in one go."""
        return _lbise(op.extract(z.rchanges, start, end))

    # --------------------------------------------------------------
    # 2. FIRST PASS – source‑side markup
    # --------------------------------------------------------------
    for d in tqdm(res.deltas, desc="first‑pass"):
        if isinstance(d, BC):
            id1, id2 = f"v1_{d.a_start}_{d.a_end}", f"v2_{d.b_start}_{d.b_end}"
            tag = z1.soup.new_tag("anchor", **{"xml:id": id1, "corresp": id2, "function": "bc"})
            txt = slice_fmt(z1, d.a_start, d.a_end)
            zbody += str(tag) + txt
            add_main_xhtml(xhtml_mains, txt, "bc", "source", id1)

        elif isinstance(d, S):
            tid = f"v1_{d.start}_{d.end}"
            tag = z1.soup.new_tag("metamark", function="del", target=tid)
            txt = slice_fmt(z1, d.start, d.end)
            zbody += str(tag) + txt
            add_list(z1, d.start, d.end, {"corresp": tid}, "deletion", tid)
            add_main_xhtml(xhtml_mains, txt, "deletion", "source", tid)

        elif isinstance(d, I):
            tid = f"v2_{d.start}_{d.end}"
            zbody += str(z1.soup.new_tag("metamark", function="add", target=tid))
            add_list(z2, d.start, d.end, {"corresp": tid}, "addition", tid)

        elif isinstance(d, DA):
            canon_key = canon(z1.txt[d.start : d.end])
            if canon_key in moved_tgt:
                # a true “move” (transpose)
                match = moved_tgt[canon_key]
                id1, id2 = f"v1_{d.start}_{d.end}", f"v2_{match.start}_{match.end}"
                tag = z1.soup.new_tag("metamark", function="trans", target=id1, corresp=id2)
                kind = "transpose"
            else:
                # fallback to deletion
                id1  = f"v1_{d.start}_{d.end}"
                tag  = z1.soup.new_tag("metamark", function="del", target=id1)
                kind = "deletion"
                tqdm.write(f"[WARN] orphan DA treated as deletion: {canon_key[:60]!r}")

            txt = slice_fmt(z1, d.start, d.end)
            zbody += str(tag) + txt

            if kind == "transpose":
                # pass (src_id, tgt_id, label) so the list writer emits dual hrefs
                label = txt.strip()
                add_list(z1, d.start, d.end, {"target": id1}, kind, (id1, id2, label))
            else:
                add_list(z1, d.start, d.end, {"target": id1}, kind, id1)

            add_main_xhtml(xhtml_mains, txt, kind, "source", id1)

        elif isinstance(d, DB):
            zbody += str(z1.soup.new_tag("metamark", function="trans", target=f"v2_{d.start}_{d.end}"))

        elif isinstance(d, R):
            # build both source- and target-side ids
            src_id = f"v1_{d.a_start}_{d.a_end}"
            tgt_id = f"v2_{d.b_start}_{d.b_end}"

            # 1) Inline source annotation (red span)
            tag = z1.soup.new_tag("metamark", function="subst",
                                  target=src_id, corresp=tgt_id)
            txt_src = slice_fmt(z1, d.a_start, d.a_end)
            zbody += str(tag) + txt_src
            add_main_xhtml(xhtml_mains, txt_src,
                           "substitution", "source", src_id)

            # 2) Single list entry “old → new”
            txt_old = slice_fmt(z1, d.a_start, d.a_end).strip()
            txt_new = slice_fmt(z2, d.b_start, d.b_end).strip()
            label   = f"{txt_old} → {txt_new}"

            # pass a tuple so add_list_xhtml knows to emit dual-href row
            list_suffix = (src_id, tgt_id, label)
            add_list(z1, d.a_start, d.a_end, {"corresp": src_id},
                     "substitution", list_suffix)


    # --------------------------------------------------------------
    # 3. SECOND PASS – target‑side main XHTML
    # --------------------------------------------------------------
    for d in sorted(res.deltas, key=lambda k: (getattr(k, "start", 0) if isinstance(k, (I, DB)) else getattr(k, "b_start", 0))):
        if isinstance(d, DB):
            tid = f"v2_{d.start}_{d.end}"
            txt = slice_fmt(z2, d.start, d.end)
            add_main_xhtml(xhtml_mains, txt, "transpose", "target", tid)
        elif isinstance(d, I):
            tid = f"v2_{d.start}_{d.end}"
            txt = slice_fmt(z2, d.start, d.end)
            add_main_xhtml(xhtml_mains, txt, "addition", "target", tid)
        elif isinstance(d, BC):
            tid = f"v2_{d.b_start}_{d.b_end}"
            txt = slice_fmt(z2, d.b_start, d.b_end)
            add_main_xhtml(xhtml_mains, txt, "bc", "target", tid)
        elif isinstance(d, R):
            tid = f"v2_{d.b_start}_{d.b_end}"
            txt = slice_fmt(z2, d.b_start, d.b_end)
            add_main_xhtml(xhtml_mains, txt, "substitution", "target", tid)

    # --------------------------------------------------------------
    # 4. Serialize TEI diff + XHTML files
    # --------------------------------------------------------------
    from bs4 import BeautifulSoup
    safe_body_xml = str(BeautifulSoup(f"<body>{zbody}</body>", "xml").body)
    root.append(ET.fromstring(safe_body_xml))
    ET.ElementTree(root).write(output_filepath, encoding="utf-8", xml_declaration=True)

    outdir = pathlib.Path(xhtml_output_dir)
    write_xhtml_lists(outdir, ops2xhtml, xhtml_lists)
    write_xhtml_mains(outdir, xhtml_mains)

    return [output_filepath, *outdir.iterdir()]

# ------------------------------------------------------------------
# legacy helpers ---------------------------------------------------

def apply_post_processing(input_filepath: pathlib.Path, output_filepath: pathlib.Path) -> None:
    output_filepath.write_text(input_filepath.read_text("utf-8"), "utf-8")


def create_xhtml(source_filepath: pathlib.Path, output_dir: pathlib.Path) -> None:
    output_dir = pathlib.Path(output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)
    saxon_transform(source_filepath, pathlib.Path("tei2xhtml/tei2xhtml.xsl"), output_dir / "out.xhtml")

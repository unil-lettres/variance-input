# tei_writer.py
# ==============
# Utilities that turn the delta stream into:
#
#  * a TEI <body> with <anchor>/<metamark> tags
#  * <listDeletion>, <listAddition>, <listTranspose>, <listSubstitution>
#    under <mediteData>
#  * XHTML snippet lists and “main” files for the viewer
#
# The heavy lifting of *looping through* deltas still lives in
# processing.py; we just provide the helper functions and template maps
# used there.

from __future__ import annotations
import xml.etree.ElementTree as ET
from collections import defaultdict
from typing import Dict, List

from variance.canon import newline
from variance.io_helpers import Output
from variance import operations as op

# ----------------------------------------------------------------------
# Shared template data
# ----------------------------------------------------------------------
#
# We keep an ops → (href‐prefix, old‐id‐prefix, file‐suffix) map,
# purely for TEI and for reference in processing.py.  But when we
# actually build the XHTML list entries (add_list_xhtml) or the inline
# spans/anchors (add_main_xhtml), we will *override* the old “l…”
# prefixes and drop them in favor of “a…”.
#
ops2xhtml: Dict[str, dict] = {
    "deletion":     dict(href="#as", id="lbs", file="s"),  # “lbs” → old id; we override later
    "addition":     dict(href="#bi", id="lai", file="i"),
    "transpose":    dict(href="#ad", id="lbd", file="d"),
    "substitution": dict(href="#ar", id="lbr", file="r"),
    "bc":           dict(href="#bc", id="ac", file="bc"),
}

# For generating the *final* XHTML IDs, we want these exact prefixes:
#
#   operation → desired XHTML‐ID prefix
#
_ID_PREFIX: Dict[str,str] = {
    "deletion":     "as",   # “a” + “s” for suppression
    "addition":     "ai",   # “a” + “i” for insertion
    "transpose":    "ad",   # “a” + “d” for déplacé (moved)
    "substitution": "ar",   # “a” + “r” for replaced
    "bc":           "bc",   # best‐common block: keep “bc”
}


# ----------------------------------------------------------------------
# TEI header builder
# ----------------------------------------------------------------------

def build_header(z1: Output, z2: Output) -> ET.Element:
    """
    Return a <TEI> element pre‐filled with `xml:id`, `corresp` and
    the <teiHeader> copied from *z1*.
    """
    root = ET.Element(
        "{http://www.tei-c.org/ns/1.0}TEI",
        {"xml:id": z1.id, "corresp": z2.id},
    )
    root.append(ET.fromstring(str(z1.soup.find("teiHeader"))))
    return root


# ----------------------------------------------------------------------
# List helpers
# ----------------------------------------------------------------------

def add_list_xml(
    ops2xml: dict,
    z: Output,
    start: int,
    end: int,
    attributes: dict,
    name: str
) -> None:
    """
    Append one <deletion|addition|transpose|substitution> element under <mediteData>.
    """
    txt = op.extract(z.rchanges, start, end)
    ET.SubElement(ops2xml[name], name, attributes).text = txt


def add_list_xhtml(
    xhtml_lists: Dict[str, List[str]],
    z: Output,
    start: int,
    end: int,
    name: str,
    id_suffix,                # str or tuple for substitutions
) -> None:
    """
    Append one <li><a …> element into xhtml_lists[name].

    • For deletions:
        href = "#as_<id_suffix>"   id = "lbs_<id_suffix>"
    • For substitutions:
        href = "#ar_src,#ar_tgt"   id = "lbr_src"
    • All others keep the same prefix for href and id.
    """
    # 1) slice raw text, strip stray tags / newlines -------------------
    txt = op.extract(z.rchanges, start, end)
    for a, b in (("\n", ""), ("<p/>", "\n"), ("<p>", ""), ("</p>", "\n"), ("</div>", "")):
        txt = txt.replace(a, b)

    # 2) lookup static prefixes ---------------------------------------
    o = ops2xhtml[name]   # e.g. {"href":"#as", "id":"lbs", "file":"s"}

    # 3) build href / id / label for each op-type ----------------------
    if name == "substitution" and isinstance(id_suffix, tuple):
        src_id, tgt_id, label = id_suffix
        href      = f"{o['href']}_{src_id},{o['href']}_{tgt_id}"  # "#ar_v1…, #ar_v2…"
        lid       = f"{o['id']}_{src_id}"                         # "lbr_v1…"
        link_text = label

    elif name == "transpose" and isinstance(id_suffix, tuple):
        src_id, tgt_id, label = id_suffix
        href      = f"{o['href']}_{src_id},{o['href']}_{tgt_id}"  # "#ar_v1…, #ar_v2…"
        lid       = f"{o['id']}_{src_id}"                         # "lbr_v1…"
        link_text = txt.strip()  # or use `label` if you want the arrow

    elif name == "deletion":
        # deletion list item must point to deletion‐span ids (“as_…”)
        href      = f"#as_{id_suffix}"
        lid       = f"lbs_{id_suffix}"
        link_text = txt.strip()

    else:
        href      = f"{o['href']}_{id_suffix}"
        lid       = f"{o['id']}_{id_suffix}"
        link_text = txt.strip()

    # 4) store the ready-made <li> snippet ----------------------------
    xhtml_lists[name].append(
        f'<li><a class="sync" href="{href}" id="{lid}" data-tags="">{link_text}</a></li>'
    )

def add_main_xhtml(
    xhtml_mains: Dict[str, List[str]],
    txt: str,
    name: str,
    main: str,
    id_suffix: str,
) -> None:
    """
    Inject the inline synced element into *xhtml_mains[main]*.

    • deletions   →  <a  class="span_s sync sync-single"  …>
    • additions   →  <span class="span_i sync sync-single" …>
    • bc / substitution / transpose keep their earlier mapping.
    """

    # ── 1. clean snippet text ─────────────────────────────────────────
    txt = txt.replace("\n", "")
    for a, b in (
        ("<p/>", "<br></br>"), ("<p>", ""), ("</p>", "<br></br>"),
        ("</div>", ""), ("<div>", "")
    ):
        txt = txt.replace(a, b)

    # ── 2. element + class to use for each op-type  ───────────────────
    el, cls = {
        "bc":           ("a",   "span_c sync sync-single"),
        "deletion":     ("a",   "span_s sync sync-single"),
        "substitution": ("a",   "sync sync-single span_r"),
        "transpose":    ("a",   "sync sync-single span_d"),
        "addition":     ("span","span_i sync sync-single"),
    }[name]

    # ── 3. build href / id  ───────────────────────────────────────────
    o       = ops2xhtml[name]          # {'href':'#as', 'id':'lbs', …}
    href    = f"{o['href']}_{id_suffix}"          # e.g. "#as_v1_142_154"
    span_id = f"{o['href'].lstrip('#')}_{id_suffix}"  # "as_v1_142_154"

    # 4) append final snippet
    attrs = f' class="{cls}" href="{href}" id="{span_id}" data-tags=""'

    # ► add corresp when this element has a partner on the other side
    if name in {"substitution", "transpose"}:
        # partner sits on the *other* version → just switch the v1/v2 prefix
        other_id = span_id.replace("v1_", "TMP_").replace("v2_", "v1_").replace("TMP_", "v2_")
        attrs += f' corresp="{other_id}"'

    xhtml_mains[main].append(f'<{el}{attrs}>{txt}</{el}>')





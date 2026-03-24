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
from typing import Dict, List, Optional

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

_LIST_NEWLINE_MARKER = "¶"

# For generating the *final* XHTML IDs, we want these exact prefixes:
#
#   operation → desired XHTML‐ID prefix
#
_ID_COUNTERS: Dict[str, int] = {name: 0 for name in ops2xhtml.keys()}
_ID_MAP: Dict[str, Dict[str, int]] = {name: {} for name in ops2xhtml.keys()}


def reset_numbering_state() -> None:
    """
    Reset ID counters so each comparison starts from 00000 again.
    """
    for name in _ID_COUNTERS:
        _ID_COUNTERS[name] = 0
        _ID_MAP[name].clear()


def _next_index(name: str, tei_id: str, counterpart_id: Optional[str] = None) -> int:
    """
    Return the legacy sequential index for *tei_id*, sharing the same value
    with *counterpart_id* when relevant (e.g. source ↔ target pairs).
    """
    mapping = _ID_MAP[name]
    if tei_id in mapping:
        return mapping[tei_id]

    if counterpart_id and counterpart_id in mapping:
        index = mapping[counterpart_id]
    else:
        index = _ID_COUNTERS[name]
        _ID_COUNTERS[name] += 1

    mapping[tei_id] = index
    if counterpart_id:
        mapping[counterpart_id] = index

    return index


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
    # 1) slice raw text, normalize structural tags, keep line breaks visible
    txt = op.extract(z.rchanges, start, end)
    for a, b in (("<p/>", "\n"), ("<p>", ""), ("</p>", "\n"), ("</div>", "")):
        txt = txt.replace(a, b)

    # 2) build href / id / label using legacy numbering ----------------
    o = ops2xhtml[name]   # e.g. {"href":"#as", "id":"lbs", "file":"s"}

    link_classes = {
        "addition": "sync",
        "deletion": "sync",
        "substitution": "sync sync-twice",
        "transpose": "sync sync-twice",
        "bc": "sync",
    }

    if name == "substitution" and isinstance(id_suffix, tuple):
        src_id, tgt_id, label = id_suffix
        index = _next_index(name, src_id, tgt_id)
        num = f"{index:05d}"
        href = f"#ar_{num}"
        lid = f"lbr_{num}"
        link_text = label.replace("\n", _LIST_NEWLINE_MARKER).strip()

    elif name == "transpose" and isinstance(id_suffix, tuple):
        src_id, tgt_id, _label = id_suffix
        index = _next_index(name, src_id, tgt_id)
        num = f"{index:05d}"
        href = f"#ad_{num}"
        lid = f"lbd_{num}"
        link_text = txt.replace("\n", _LIST_NEWLINE_MARKER).strip()

    else:
        tei_id: str
        if isinstance(id_suffix, tuple):
            tei_id = id_suffix[0]
        else:
            tei_id = id_suffix
        index = _next_index(name, tei_id)
        num = f"{index:05d}"
        href = f"{o['href']}_{num}"
        lid = f"{o['id']}_{num}"
        link_text = txt.replace("\n", _LIST_NEWLINE_MARKER).strip()

    xhtml_lists[name].append(
        f'<li><a class="{link_classes.get(name, "sync")}" href="{href}" id="{lid}" data-tags="">{link_text}</a></li>'
    )

def add_main_xhtml(
    xhtml_mains: Dict[str, List[str]],
    txt: str,
    name: str,
    main: str,
    id_suffix: str,
    counterpart_id: Optional[str] = None,
) -> None:
    """
    Inject the inline synced element into *xhtml_mains[main]* using the legacy
    “ac_00000” / “bc_00000” style identifiers.
    """

    # ── 1. clean snippet text ─────────────────────────────────────────
    txt = txt.replace("\n", "")
    for a, b in (
        ("<p/>", "<br></br>"), ("<p>", ""), ("</p>", "<br></br>"),
        ("</div>", ""), ("<div>", "")
    ):
        txt = txt.replace(a, b)

    index = _next_index(name, id_suffix, counterpart_id)
    num = f"{index:05d}"
    prefix_letter = "a" if main == "source" else "b"

    if name == "bc":
        el = "a"
        cls = "span_c sync sync-single"
        if main == "source":
            element_id = f"ac_{num}"
            href = f"#bc_{num}"
        else:
            element_id = f"bc_{num}"
            href = f"#ac_{num}"
    elif name == "deletion":
        el = "span"
        cls = "span_s"
        element_id = f"{prefix_letter}s_{num}"
        href = None
    elif name == "addition":
        el = "span"
        cls = "span_i"
        element_id = f"{prefix_letter}i_{num}"
        href = None
    elif name == "substitution":
        el = "a"
        cls = "span_r sync sync-single"
        if main == "source":
            element_id = f"ar_{num}"
            href = f"#br_{num}"
        else:
            element_id = f"br_{num}"
            href = f"#ar_{num}"
    elif name == "transpose":
        el = "a"
        cls = "span_d sync sync-single"
        if main == "source":
            element_id = f"ad_{num}"
            href = f"#bd_{num}"
        else:
            element_id = f"bd_{num}"
            href = f"#ad_{num}"
    else:
        raise ValueError(f"Unhandled operation type: {name}")

    attrs = [f'class="{cls}"', f'id="{element_id}"', 'data-tags=""']
    if href:
        attrs.insert(1, f'href="{href}"')

    xhtml_mains[main].append(f'<{el} {" ".join(attrs)}>{txt}</{el}>')

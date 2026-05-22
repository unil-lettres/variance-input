"""
xhtml_writer.py
===============
Helpers for the cosmetic XHTML files shown in the web viewer.

We keep them separate so `processing.py` only orchestrates, while all
I/O lives in dedicated modules.
"""

from __future__ import annotations

import pathlib
import subprocess
from collections import defaultdict
from typing import Dict, List

# ----------------------------------------------------------------------
# 1 — write out the <li> list files and the “main” sync snippets
# ----------------------------------------------------------------------

def write_xhtml_lists(
    outdir: pathlib.Path,
    ops2xhtml: Dict[str, dict],
    xhtml_lists: Dict[str, List[str]],
) -> None:
    """For each op-type create `<file>_py.xhtml` with collected <li> tags."""
    outdir.mkdir(parents=True, exist_ok=True)
    for name, li_list in xhtml_lists.items():
        file_name = f"{ops2xhtml[name]['file']}_py.xhtml"
        (outdir / file_name).write_text("\n".join(li_list), "utf-8")


def write_xhtml_mains(
    outdir: pathlib.Path,
    xhtml_mains: Dict[str, List[str]],
) -> None:
    """Dump the synced ‘main’ files (source_py.xhtml, target_py.xhtml)."""
    outdir.mkdir(parents=True, exist_ok=True)
    for name, main_list in xhtml_mains.items():
        (outdir / f"{name}_py.xhtml").write_text("".join(main_list), "utf-8")

# ----------------------------------------------------------------------
# 2 — optional: TEI → XHTML transformation via Saxon
# ----------------------------------------------------------------------

def saxon_transform(
    tei_file: pathlib.Path,
    xsl_file: pathlib.Path,
    output_file: pathlib.Path,
    saxon_jar: pathlib.Path = pathlib.Path("tei2xhtml/lib/SaxonHE12-5J/saxon-he-12.5.jar"),
) -> None:
    """
    Run Saxon-HE to transform *tei_file* with *xsl_file* into *output_file*.
    """
    cmd = [
        "java", "-jar", str(saxon_jar),
        "-s:"  + str(tei_file),
        "-xsl:"+ str(xsl_file),
        "-o:"  + str(output_file),
    ]
    subprocess.run(cmd, check=True)

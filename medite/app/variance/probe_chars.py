# probe_chars.py
import xml.etree.ElementTree as ET
from pathlib import Path

TEI_NS = "http://www.tei-c.org/ns/1.0"
ns = {"tei": TEI_NS}

def extract_p_text(fn: Path) -> str:
    """
    Parse “fn” as TEI and return the inner text of the first <text><body><p>…</p></body></text> block.
    """
    tree = ET.parse(str(fn))
    root = tree.getroot()
    # find the <p> element under <text>/<body>
    p = root.find(".//tei:text/tei:body/tei:p", ns)
    return p.text or ""


# Adjust these paths to wherever your XML files live.
pda1 = Path("tests/data/PagedAmour/1pda.xml")
pda2 = Path("tests/data/PagedAmour/2pda.xml")
csb1 = Path("tests/data/CrimeDeSylvestreBonnard/1csb.xml")
csb2 = Path("tests/data/CrimeDeSylvestreBonnard/2csb.xml")

text_pda = extract_p_text(pda1) + extract_p_text(pda2)
text_csb = extract_p_text(csb1) + extract_p_text(csb2)

chars_pda = set(text_pda)
chars_csb = set(text_csb)

# “CSB-only” characters = ones in CSB but not in PDA
only_in_csb = sorted(chars_csb - chars_pda)

print("Characters present in CSB but not in PDA:")
print("----------------------------------------")
for ch in only_in_csb:
    # show the character, its codepoint, and a repr()
    print(f" U+{ord(ch):04X}   {repr(ch)}")

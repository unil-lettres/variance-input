from variance.io_helpers import xml2txt
from pathlib import Path

src = xml2txt(Path("tests/data/PagedAmour/1pda.xml"))
tgt = xml2txt(Path("tests/data/PagedAmour/2pda.xml"))

print("len(src) =", len(src.txt))
print("len(tgt) =", len(tgt.txt))
print("identical:", src.txt == tgt.txt)
print("\n⟨src first 120 chars⟩\n", src.txt[:120].replace('\n','␤'))
print("\n⟨tgt first 120 chars⟩\n", tgt.txt[:120].replace('\n','␤'))

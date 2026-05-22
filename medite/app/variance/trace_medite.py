# trace_medite.py ------------------------------------------------
import sys, pathlib, itertools
from variance.io_helpers import xml2txt
from variance.medite import medite as md
from variance.diff_core import _translate_medite_tuple

src, tgt = map(pathlib.Path, sys.argv[1:3])
z1, z2   = xml2txt(src), xml2txt(tgt)
N        = len(z1.txt)
# correct way – every field spelt-out
params = md.Parameters(
    lg_pivot         = 7,
    ratio            = 15,
    seuil            = 50,
    car_mot          = 2,
    sep              = " ",      # the token separator
    case_sensitive   = True,
    diacri_sensitive = True,
    sep_sensitive    = True,
    algo             = "HIS",    # <-- Medite’s « Highest » algorithm
)

appli = md.DiffTexts(z1.txt, z2.txt, parameters=params)

print("---- raw Medite tuples ----")
for t in itertools.islice(appli.bbl.liste, 10):
    print(t)

print("\n---- after translation ----")
for t in itertools.islice(appli.bbl.liste, 10):
    print(_translate_medite_tuple(t, N, z2.txt))
# ---------------------------------------------------------------

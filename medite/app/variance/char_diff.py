#!/usr/bin/env python3
# char_diff.py
#
# Usage:
#   python char_diff.py path/to/1csb.xml path/to/2csb.xml
#
# (Or point it at any two text/XML files.)

import sys
import unicodedata
import pathlib

def all_chars(path):
    text = path.read_text("utf-8")
    # If you want to compare only the <p> contents (i.e. strip tags), you can
    # do something like:
    #
    #    from bs4 import BeautifulSoup
    #    soup = BeautifulSoup(text, "xml")
    #    text = soup.get_text()
    #
    # But here we’ll just compare the raw file as-is:
    return set(text)

def describe(cp):
    # give a printable description of the codepoint: “U+XXXX NAME”
    name = unicodedata.name(cp, "<unassigned>")
    return f"U+{ord(cp):04X}   '{cp}'   {name}"

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python char_diff.py file1 file2")
        sys.exit(1)

    p1 = pathlib.Path(sys.argv[1])
    p2 = pathlib.Path(sys.argv[2])
    s1 = all_chars(p1)
    s2 = all_chars(p2)

    only1 = sorted(s1 - s2, key=lambda c: ord(c))
    only2 = sorted(s2 - s1, key=lambda c: ord(c))

    if not only1 and not only2:
        print("No character‐level differences found.")
        sys.exit(0)

    if only1:
        print("Characters present in “%s” but not in “%s”:" % (p1.name, p2.name))
        print("------------------------------------------------")
        for c in only1:
            print(" ", describe(c))
        print()

    if only2:
        print("Characters present in “%s” but not in “%s”:" % (p2.name, p1.name))
        print("------------------------------------------------")
        for c in only2:
            print(" ", describe(c))
        print()

from types import SimpleNamespace

from variance import operations as op
from variance.tei_writer import (
    add_list_xhtml,
    add_main_xhtml,
    render_inline_tei_for_xhtml,
    reset_numbering_state,
)


def test_render_inline_tei_for_xhtml_converts_emph_to_em():
    assert render_inline_tei_for_xhtml("Un <emph>mot</emph>.") == "Un <em>mot</em>."


def test_add_main_xhtml_renders_emph_as_html_em():
    reset_numbering_state()
    xhtml_mains = {"source": []}

    add_main_xhtml(xhtml_mains, "Un <emph>mot</emph>.", "deletion", "source", "v1_0_1")

    assert '<span class="span_s" id="as_00000" data-tags="">Un <em>mot</em>.</span>' in xhtml_mains["source"]


def test_add_list_xhtml_renders_emph_as_html_em():
    reset_numbering_state()
    xhtml_lists = {"deletion": []}
    rchanges = op.Text("<emph>mot</emph>", (), ())
    output = SimpleNamespace(rchanges=rchanges)

    add_list_xhtml(xhtml_lists, output, 0, len(rchanges.text), "deletion", "v1_0_1")

    assert '<a class="sync" href="#as_00000" id="lbs_00000" data-tags=""><em>mot</em></a>' in xhtml_lists["deletion"][0]

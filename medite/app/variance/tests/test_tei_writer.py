from types import SimpleNamespace

from variance import operations as op
from variance.tei_writer import (
    add_list_xhtml,
    add_main_xhtml,
    render_substitution_label_for_xhtml,
    render_inline_tei_for_xhtml,
    render_list_label_for_xhtml,
    reset_numbering_state,
)


def test_render_inline_tei_for_xhtml_converts_emph_to_em():
    assert render_inline_tei_for_xhtml("Un <emph>mot</emph>.") == "Un <em>mot</em>."


def test_render_inline_tei_for_xhtml_balances_partial_emph_fragments():
    assert render_inline_tei_for_xhtml("<emph>La") == "<em>La</em>"
    assert render_inline_tei_for_xhtml("caisse</emph>.") == "<em>caisse</em>."


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


def test_render_substitution_label_for_xhtml_balances_each_side():
    assert (
        render_substitution_label_for_xhtml("<emph>la", "<emph>La")
        == "<em>la</em> → <em>La</em>"
    )
    assert (
        render_substitution_label_for_xhtml("versa</emph>", "versâ</emph>. Aussitôt")
        == "<em>versa</em> → <em>versâ</em>. Aussitôt"
    )


def test_add_list_xhtml_renders_substitution_sides_independently():
    reset_numbering_state()
    xhtml_lists = {"substitution": []}
    rchanges = op.Text("<emph>la", (), ())
    output = SimpleNamespace(rchanges=rchanges)

    add_list_xhtml(
        xhtml_lists,
        output,
        0,
        len(rchanges.text),
        "substitution",
        ("v1_0_1", "v2_0_1", "<emph>la", "<emph>La"),
    )

    assert "<em>la</em> → <em>La</em>" in xhtml_lists["substitution"][0]


def test_render_list_label_for_xhtml_suppresses_invisible_space():
    assert render_list_label_for_xhtml("   ") == ""
    assert render_list_label_for_xhtml("&nbsp;") == ""


def test_render_list_label_for_xhtml_marks_explicit_line_break_as_pilcrow():
    assert render_list_label_for_xhtml("<br/>") == "¶"
    assert render_list_label_for_xhtml("\n") == "¶"


def test_render_list_label_for_xhtml_trims_punctuation_spacing():
    assert render_list_label_for_xhtml("hello ,") == "hello,"
    assert render_list_label_for_xhtml(" hello ") == "hello"


def test_render_substitution_label_for_xhtml_marks_space_to_line_break_as_pilcrow():
    assert render_substitution_label_for_xhtml(" ", "<br/>") == "¶"


def test_add_list_xhtml_does_not_emit_space_only_change():
    reset_numbering_state()
    xhtml_lists = {"addition": []}
    rchanges = op.Text(" ", (), ())
    output = SimpleNamespace(rchanges=rchanges)

    add_list_xhtml(xhtml_lists, output, 0, len(rchanges.text), "addition", "v2_0_1")

    assert xhtml_lists["addition"] == []

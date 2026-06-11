from types import SimpleNamespace

from variance import operations as op
from variance.tei_writer import (
    add_list_xhtml,
    add_main_xhtml,
    add_plain_main_xhtml,
    apply_emphasis_context_for_xhtml,
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


def test_apply_emphasis_context_wraps_fragment_inside_emph_range():
    medite = op.xml2medite("Le concierge dit: <emph>La Caisse est fermée</emph>.")
    rchanges = op.reverse_transform(medite)
    start = medite.text.index("Caisse")
    end = start + len("Caisse")

    assert op.extract(rchanges, start, end) == "Caisse"
    assert (
        apply_emphasis_context_for_xhtml("Caisse", rchanges, start, end)
        == "<emph>Caisse</emph>"
    )


def test_add_main_xhtml_renders_emph_as_html_em():
    reset_numbering_state()
    xhtml_mains = {"source": []}

    add_main_xhtml(xhtml_mains, "Un <emph>mot</emph>.", "deletion", "source", "v1_0_1")

    assert '<span class="span_s" id="as_00000" data-tags="">Un <em>mot</em>.</span>' in xhtml_mains["source"]


def test_add_main_xhtml_renders_italic_context_for_inner_fragment():
    reset_numbering_state()
    xhtml_mains = {"target": []}
    medite = op.xml2medite("Mot secret: <emph>Sésame ouvre-toi?</emph>")
    rchanges = op.reverse_transform(medite)
    start = medite.text.index("ouvre-toi")
    end = start + len("ouvre-toi?")

    add_main_xhtml(
        xhtml_mains,
        op.extract(rchanges, start, end),
        "substitution",
        "target",
        "v2_0_1",
        rchanges=rchanges,
        start=start,
        end=end,
    )

    assert '<a class="span_r sync sync-single" href="#ar_00000" id="br_00000" data-tags=""><em>ouvre-toi?</em></a>' in xhtml_mains["target"]


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

    emitted = add_list_xhtml(xhtml_lists, output, 0, len(rchanges.text), "addition", "v2_0_1")

    assert emitted is False
    assert xhtml_lists["addition"] == []


def test_add_plain_main_xhtml_preserves_space_without_transformation_id():
    xhtml_mains = {"target": []}

    add_plain_main_xhtml(xhtml_mains, " ", "target")

    assert xhtml_mains["target"] == [" "]

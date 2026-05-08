from variance.xhtml_writer import write_xhtml_lists, write_xhtml_mains


def test_write_xhtml_mains_preserves_inline_adjacency(tmp_path):
    write_xhtml_mains(
        tmp_path,
        {
            "source": [
                "d'",
                '<a class="span_r">enfants</a>',
                ", et",
            ],
        },
    )

    assert (
        tmp_path / "source_py.xhtml"
    ).read_text("utf-8") == 'd\'<a class="span_r">enfants</a>, et'


def test_write_xhtml_lists_keeps_one_item_per_line(tmp_path):
    write_xhtml_lists(
        tmp_path,
        {"substitution": {"file": "r"}},
        {"substitution": ["<li>one</li>", "<li>two</li>"]},
    )

    assert (tmp_path / "r_py.xhtml").read_text("utf-8") == "<li>one</li>\n<li>two</li>"

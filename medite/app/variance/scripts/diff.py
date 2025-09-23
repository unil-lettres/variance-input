import logging
logging.basicConfig(
    level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s"
)

import pathlib
import shutil
from os.path import dirname
from pathlib import Path

import click

from variance import processing as p
from variance.medite import medite as md

logger = logging.getLogger(__name__)

default = md.DEFAULT_PARAMETERS


@click.command()
@click.argument("source_filename", type=click.Path(exists=True))
@click.argument("target_filename", type=click.Path(exists=True))
@click.option("--lg_pivot", default=default.lg_pivot)
@click.option("--ratio", default=default.ratio)
@click.option("--seuil", default=default.seuil)
@click.option("--sep", default=default.sep)
@click.option("--case-sensitive/--no-case-sensitive", default=default.case_sensitive)
@click.option("--diacri-sensitive/--no-diacri-sensitive", default=default.diacri_sensitive)
@click.option("--output-xml", type=click.Path(), default="informations.xml")
@click.option(
    "--xhtml-output-dir",
    type=click.Path(file_okay=False, dir_okay=True),
    help="Directory to generate XHTML output files",
)
def run(
    source_filename,
    target_filename,
    lg_pivot,
    ratio,
    seuil,
    sep,
    case_sensitive,
    diacri_sensitive,
    output_xml,
    xhtml_output_dir,
):
    for c in sep:
        logger.info(f"using sep={repr(c)}")

    parameters = md.Parameters(
        lg_pivot=lg_pivot,
        ratio=ratio,
        seuil=seuil,
        car_mot=default.car_mot,
        case_sensitive=case_sensitive,
        sep_sensitive=default.sep_sensitive,
        diacri_sensitive=diacri_sensitive,
        algo=default.algo,
        sep=sep,
    )

    src_fp = Path(source_filename)
    tgt_fp = Path(target_filename)
    if src_fp.suffix != ".xml" or tgt_fp.suffix != ".xml":
        raise ValueError("Both source and target must be .xml files")
    if dirname(source_filename) != dirname(target_filename):
        raise ValueError("source and target files must be in the same directory")

    # --------------------------------------------------------------
    # run the core pipeline
    # --------------------------------------------------------------
    raw_out = Path(output_xml).with_suffix(".raw.xml")
    debug_paths = p.process(
        source_filepath=src_fp,
        target_filepath=tgt_fp,
        parameters=parameters,
        output_filepath=raw_out,
        xhtml_output_dir=xhtml_output_dir,
    )

    # TEI post-processing
    p.apply_post_processing(raw_out, Path(output_xml))

    # extra XHTML pass via Saxon (still writes out.xhtml)
    if xhtml_output_dir:
        outdir = Path(xhtml_output_dir)
        p.create_xhtml(Path(output_xml), outdir)

        # ----------------------------------------------------------
        # rename _py.xhtml files to their final names
        # ----------------------------------------------------------
        renames = {
            "d_py.xhtml":      "d.xhtml",
            "i_py.xhtml":      "i.xhtml",
            "r_py.xhtml":      "r.xhtml",
            "s_py.xhtml":      "s.xhtml",
            "source_py.xhtml": "source.xhtml",
            "target_py.xhtml": "target.xhtml",
        }
        for src_name, dst_name in renames.items():
            src = outdir / src_name
            dst = outdir / dst_name
            if not src.exists():
                logger.warning(f"expected {src_name} not found")
                continue
            logger.info(f"renaming {src_name} → {dst_name}")
            # replace empty stub if it exists
            if dst.exists():
                dst.unlink()
            src.rename(dst)

        # remove the Saxon artefact if you don't need it
        saxon_out = outdir / "out.xhtml"
        if saxon_out.exists():
            logger.info("deleting out.xhtml (not needed for publication)")
            saxon_out.unlink()

        # optional: copy the final TEI diff into the same folder
        final_tei = Path(output_xml)
        dest = outdir / final_tei.name
        try:
            if final_tei.resolve() != dest.resolve():
                shutil.copy(final_tei, dest)
        except FileNotFoundError:
            # if dest parent vanished just skip the copy
            logger.warning("skipping TEI copy; destination missing", extra={"dest": str(dest)})


if __name__ == "__main__":
    run()

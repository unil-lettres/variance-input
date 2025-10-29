from flask import Flask, request, jsonify, render_template, redirect, url_for, send_from_directory
from celery import Celery
import subprocess
import redis
import os
import sys
import time
import html
from pathlib import Path

SCRIPT_DIFF = Path(__file__).resolve().parent / "variance" / "scripts" / "diff.py"

app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = 'uploads'
os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)

# ✅ Use new-style keys only to avoid Celery config conflict
celery = Celery(app.name)
celery.conf.update(
    broker_url='redis://redis:6379/0',
    result_backend='redis://redis:6379/0',
    task_soft_time_limit=1800,  # Allow up to 30 minutes for heavy comparisons
    task_time_limit=2100        # Hard-stop after 35 minutes
)

redis_conn = redis.StrictRedis(host='redis', port=6379, db=0)

@app.route('/uploads/<path:filename>')
def uploaded_file(filename):
    """Serve uploaded files."""
    return send_from_directory(app.config['UPLOAD_FOLDER'], filename)


@celery.task
def run_diff_script(
    source_filename,
    target_filename,
    lg_pivot,
    ratio,
    seuil,
    case_sensitive,
    diacri_sensitive,
    output_xml,
    sep=None,
    xhtml_output_dir=None,
    comparison_id=None,
):
    """Run the diff script asynchronously."""
    def _compact(log: str) -> str:
        if not log:
            return ''
        lines = log.splitlines()
        filtered = [
            ln for ln in lines
            if 'INFO -' not in ln or any(key in ln for key in ('sim =', 'yINS', 'yREMP', 'yDEP'))
        ]
        if not filtered:
            filtered = lines
        keep = 25
        if len(filtered) > keep:
            omitted = len(filtered) - keep
            filtered = ['(... {} lines omitted ...)'.format(omitted)] + filtered[-keep:]
        return '\n'.join(filtered).strip()

    try:
        command = [
            sys.executable,
            str(SCRIPT_DIFF),
            source_filename,
            target_filename,
            "--lg_pivot", str(lg_pivot),
            "--ratio", str(ratio),
            "--seuil", str(seuil),
            "--case-sensitive" if case_sensitive else "--no-case-sensitive",
            "--diacri-sensitive" if diacri_sensitive else "--no-diacri-sensitive",
            "--output-xml", output_xml,
        ]

        if sep:
            command.extend(["--sep", sep])

        if xhtml_output_dir:
            Path(xhtml_output_dir).mkdir(parents=True, exist_ok=True)
            command.extend(["--xhtml-output-dir", xhtml_output_dir])

        print(f"[run_diff_script] Running command: {' '.join(command)}")

        # Run the script and capture stdout/stderr
        result = subprocess.run(
            command,
            check=True,
            capture_output=True,
            text=True,
            cwd=str(SCRIPT_DIFF.parent.parent),
        )

        import shutil

        # === Determine folder based on output_xml path ===
        output_path = Path(output_xml)  # e.g. /app/uploads/lvf/comparisons/42-17.xml
        comparisons_folder = output_path.parent  # e.g. /app/uploads/lvf/comparisons

        # === Extract source and target IDs from source/target filenames ===
        source_id = Path(source_filename).stem  # "42"
        target_id = Path(target_filename).stem  # "17"
        base_name = f"{source_id}-{target_id}"  # "42-17"

        comparisons_folder.mkdir(parents=True, exist_ok=True)

        produced_files = []
        meta = {}

        # --- Ensure TEI diff is recorded ----------------------------------
        if output_path.exists():
            produced_files.append(str(output_path))
        else:
            print(f"[run_diff_script] Warning: expected XML at {output_path} but not found")

        if xhtml_output_dir:
            out_dir = Path(xhtml_output_dir)
            if out_dir.exists():
                for candidate in sorted(out_dir.glob('*.xhtml')):
                    produced_files.append(str(candidate))
            else:
                print(f"[run_diff_script] Warning: XHTML dir {out_dir} missing")

        public_urls = {}
        shared_files = []
        component_names = sorted({Path(path).name for path in produced_files if path.endswith('.xhtml')})
        if component_names:
            meta['xhtml_components'] = component_names

        component_counts = {}
        component_files = sorted({Path(path) for path in produced_files if path.endswith('.xhtml')})
        for comp_path in component_files:
            try:
                text = comp_path.read_text(encoding='utf-8', errors='ignore')
            except Exception as err:
                print(f"[run_diff_script] Could not read {comp_path} for counting: {err}")
                continue
            key = comp_path.name
            if key.lower() in {'d.xhtml', 'i.xhtml', 'r.xhtml', 's.xhtml'}:
                component_counts[key] = text.count('<li')

        if component_counts:
            meta['component_counts'] = component_counts

        html_candidates = []
        if comparison_id:
            public_root = Path('/app/storage_public/uploads/comparisons')
            public_root.mkdir(parents=True, exist_ok=True)

            if output_path.exists():
                public_xml = public_root / f"{comparison_id}.xml"
                shutil.copy2(output_path, public_xml)
                shared_files.append(str(public_xml))
                public_urls['xml'] = f"/storage/uploads/comparisons/{comparison_id}.xml"

            # copy a primary HTML view if we can locate one
            fallback_sources = []
            if xhtml_output_dir:
                out_dir = Path(xhtml_output_dir)
                if out_dir.exists():
                    html_candidates.extend(sorted(out_dir.glob('*.html')))
                    fallback_sources.extend(str(p) for p in sorted(out_dir.glob('*.xhtml')))

            for candidate in html_candidates:
                if candidate.suffix.lower() not in {'.html', '.xhtml'}:
                    continue
                public_html = public_root / f"{comparison_id}{candidate.suffix.lower()}"
                shutil.copy2(candidate, public_html)
                shared_files.append(str(public_html))
                public_urls['html'] = f"/storage/uploads/comparisons/{comparison_id}{candidate.suffix.lower()}"
                break

            if 'html' not in public_urls:
                fallback = public_root / f"{comparison_id}.html"
                try:
                    artifact_names = sorted(
                        {Path(path).name for path in (*produced_files, *fallback_sources)}
                    )
                    fallback.write_text(
                        "<!doctype html><meta charset='utf-8'><title>Medite results"\
                        "</title><body><h1>Medite outputs available</h1>"\
                        "<p>No primary HTML view was generated. Available artifacts:</p><ul>" +
                        ''.join(
                            f"<li><code>{html.escape(name)}</code></li>"
                            for name in artifact_names
                        ) +
                        "</ul></body>",
                        encoding='utf-8'
                    )
                    public_urls['html'] = f"/storage/uploads/comparisons/{comparison_id}.html"
                    shared_files.append(str(fallback))
                    meta['html_fallback'] = True
                except Exception as err:
                    print(f"[run_diff_script] Could not write fallback HTML: {err}")
            else:
                meta['html_fallback'] = False

            # Mirror XHTML components to shared storage so Laravel can access them directly
            if xhtml_output_dir:
                out_dir = Path(xhtml_output_dir)
                try:
                    rel = out_dir.relative_to(Path('/app/uploads'))
                except ValueError:
                    rel = None

                if rel:
                    shared_dir = Path('/app/storage_public/uploads') / rel
                    shared_dir.mkdir(parents=True, exist_ok=True)
                    print(f"[run_diff_script] Mirroring components to {shared_dir}")
                    components = ['d.xhtml', 'i.xhtml', 'r.xhtml', 's.xhtml', 'source.xhtml', 'target.xhtml']
                    for name in components:
                        src = out_dir / name
                        if not src.exists():
                            print(f"[run_diff_script] Component missing at source for mirror: {src}")
                            continue
                        dest = shared_dir / name
                        try:
                            shutil.copy2(src, dest)
                            print(f"[run_diff_script] Copied {src} -> {dest}")
                        except Exception as err:
                            print(f"[run_diff_script] Could not copy {src} to {dest}: {err}")

        if 'html_fallback' not in meta and html_candidates:
            meta['html_fallback'] = False

        return {
            "status": "success",
            "output": produced_files,
            "public_urls": public_urls,
            "shared_files": shared_files,
            "stdout": _compact(result.stdout),
            "stderr": _compact(result.stderr),
            "meta": meta,
        }

    except subprocess.CalledProcessError as e:
        combined = (e.stdout or '') + (e.stderr or '')
        details = _compact(combined)
        error_output = details + ("\n" if details else "") + str(e)
        print("[run_diff_script] ERROR:", error_output)
        return {
            "status": "error",
            "error": error_output
        }



@app.route('/')
def home():
    return render_template('index.html')


@app.route('/run_diff', methods=['POST'])
def run_diff():
    """Endpoint to launch the diff script (via direct file upload)."""
    source_file = request.files['source_file']
    target_file = request.files['target_file']

    if not source_file or not target_file:
        return jsonify({"error": "Source or target file is missing"}), 400

    # Extract parameters
    lg_pivot = int(request.form.get('lg_pivot', 7))
    ratio = int(request.form.get('ratio', 15))
    seuil = int(request.form.get('seuil', 50))
    case_sensitive = request.form.get('case_sensitive', 'off') == 'on'
    diacri_sensitive = request.form.get('diacri_sensitive', 'off') == 'on'
    output_xml = os.path.join(app.config['UPLOAD_FOLDER'], 'result.xml')

    # Save uploaded files
    source_path = os.path.join(app.config['UPLOAD_FOLDER'], source_file.filename)
    target_path = os.path.join(app.config['UPLOAD_FOLDER'], target_file.filename)
    try:
        source_file.save(source_path)
        target_file.save(target_path)
    except Exception as e:
        return jsonify({"error": f"File upload failed: {e}"}), 500

    print(
        f"[run_diff] => source_path={source_path}, target_path={target_path}, "
        f"lg_pivot={lg_pivot}, ratio={ratio}, seuil={seuil}, "
        f"case_sensitive={case_sensitive}, diacri_sensitive={diacri_sensitive}, output_xml={output_xml}"
    )

    # Submit the task to Celery
    task = run_diff_script.apply_async(kwargs={
        "source_filename": source_path,
        "target_filename": target_path,
        "lg_pivot": lg_pivot,
        "ratio": ratio,
        "seuil": seuil,
        "case_sensitive": case_sensitive,
        "diacri_sensitive": diacri_sensitive,
        "output_xml": output_xml
    })

    return redirect(url_for('task_status_page', task_id=task.id))


@app.route('/run_diff2', methods=['POST'])
def run_diff2():
    """Endpoint for calls from Laravel"""
    author_id = request.form.get('author_id')
    work_id = request.form.get('work_id')
    comparison_id = request.form.get('comparison_id')

    source_filename = request.form.get('source_filename')
    target_filename = request.form.get('target_filename')
    lg_pivot = request.form.get('lg_pivot', 7)
    ratio = request.form.get('ratio', 15)
    seuil = request.form.get('seuil', 50)
    case_sensitive = request.form.get('case_sensitive', 'on') == 'on'
    diacri_sensitive = request.form.get('diacri_sensitive', 'on') == 'on'
    output_xml = request.form.get('output_xml')
    sep = request.form.get('sep')
    xhtml_output_dir = request.form.get('xhtml_output_dir')

    task = run_diff_script.apply_async(
        kwargs={
            "source_filename": source_filename,
            "target_filename": target_filename,
            "lg_pivot": int(lg_pivot),
            "ratio": int(ratio),
            "seuil": int(seuil),
            "case_sensitive": case_sensitive,
            "diacri_sensitive": diacri_sensitive,
            "output_xml": output_xml,
            "sep": sep,
            "xhtml_output_dir": xhtml_output_dir,
            "comparison_id": comparison_id,
        },
        time_limit=2100,        # Hard timeout (seconds)
        soft_time_limit=1800    # Graceful shutdown before hard timeout
    )

    return jsonify({"task_id": task.id}), 200



@app.route('/task_status_page/<task_id>')
def task_status_page(task_id):
    """Render the task status HTML page (for the manual upload workflow)."""
    return render_template('task_status.html', task_id=task_id)

@app.route('/task_status/<task_id>', methods=['GET'])
def task_status(task_id):
    """Check the status of a Celery task."""
    task = run_diff_script.AsyncResult(task_id)

    if task.state == 'PENDING':
        return jsonify({"status": "pending"}), 200

    elif task.state == 'SUCCESS':
        return jsonify({"status": "completed", "task_id": task.id, "result": task.result}), 200

    elif task.state == 'FAILURE':
        error_msg = "Unknown error"
        if task.info:
            # Now .info is our Exception’s message, a string
            error_msg = str(task.info)
        return jsonify({"status": "failed", "error": error_msg}), 200

    # For states like STARTED, RETRY, etc.
    return jsonify({"status": task.state}), 200


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)

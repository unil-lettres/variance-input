from flask import Flask, request, jsonify, redirect, url_for, send_from_directory
from celery import Celery
import subprocess
import redis
import os
import sys
import time
import html
import json
from datetime import datetime
from pathlib import Path
import tempfile
import platform
import importlib.metadata

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
        started_at = time.perf_counter()
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

        result = subprocess.run(
            command,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            cwd=str(SCRIPT_DIFF.parent.parent),
        )
        runtime_seconds = time.perf_counter() - started_at

        stdout, stderr = result.stdout, result.stderr
        if result.returncode != 0:
            raise subprocess.CalledProcessError(result.returncode, command, output=stdout, stderr=stderr)

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
            "stdout": _compact(stdout),
            "stderr": _compact(stderr),
            "meta": meta,
            "metrics": {
                "runtime_seconds": runtime_seconds,
                "comparison_id": comparison_id,
            },
        }

    except subprocess.CalledProcessError as e:
        combined = (e.stdout or '') + (e.stderr or '')
        details = _compact(combined)
        error_output = details + ("\n" if details else "") + str(e)
        print("[run_diff_script] ERROR:", error_output)
        return {
            "status": "error",
            "error": error_output,
            "metrics": {
                "comparison_id": comparison_id,
            },
        }



def _iter_task_meta(limit=20):
    keys = []
    cursor = 0
    pattern = 'celery-task-meta-*'
    while True:
        cursor, batch = redis_conn.scan(cursor=cursor, match=pattern, count=200)
        keys.extend(batch)
        if cursor == 0 or len(keys) >= limit * 5:
            break
    items = []
    for key in keys:
        try:
            raw = redis_conn.get(key)
            if not raw:
                continue
            payload = json.loads(raw)
        except Exception:
            continue
        task_id = key.decode().replace('celery-task-meta-', '') if isinstance(key, (bytes, bytearray)) else str(key).replace('celery-task-meta-', '')
        result = payload.get('result') or {}
        metrics = result.get('metrics') or {}
        date_done = payload.get('date_done') or metrics.get('date_done')
        try:
            parsed_date = datetime.fromisoformat(date_done.replace('Z', '+00:00')) if isinstance(date_done, str) else None
        except Exception:
            parsed_date = None
        items.append({
            'task_id': task_id,
            'status': payload.get('status') or '',
            'comparison_id': metrics.get('comparison_id'),
            'runtime_seconds': metrics.get('runtime_seconds'),
            'date_done': date_done,
            'parsed_date': parsed_date,
            'error': payload.get('traceback') or (result.get('error') if isinstance(result, dict) else None),
        })
    items.sort(key=lambda x: x['parsed_date'] or datetime.min, reverse=True)
    return items[:limit]

@app.route('/')
def home():
    items = _iter_task_meta(limit=20)
    rows = []
    for item in items:
        runtime = item['runtime_seconds']
        runtime_label = f"{runtime:.2f}s" if isinstance(runtime, (int, float)) else ''
        rows.append(
            f"<tr><td><code>{html.escape(str(item['task_id']))}</code></td>"
            f"<td>{html.escape(str(item['status']))}</td>"
            f"<td>{html.escape(str(item['comparison_id'] or ''))}</td>"
            f"<td>{html.escape(runtime_label)}</td>"
            f"<td>{html.escape(str(item['date_done'] or ''))}</td></tr>"
        )
    table = "".join(rows) if rows else "<tr><td colspan='5'>Aucune tâche récente.</td></tr>"
    return f"""<!doctype html>
<html><head><meta charset="utf-8"><title>Medite status</title>
<style>
body{{font-family:system-ui,Arial,sans-serif;margin:24px;color:#222}}
table{{border-collapse:collapse;width:100%}}
th,td{{border:1px solid #ddd;padding:8px;font-size:14px}}
th{{background:#f4f4f4;text-align:left}}
.btn-link{{display:inline-block;padding:6px 12px;border:1px solid #adb5bd;border-radius:6px;color:#495057;text-decoration:none;font-size:13px}}
.btn-link:hover{{background:#f8f9fa}}
.top-bar{{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}}
.page-title{{margin:0;font-size:24px}}
</style>
</head><body>
<div class="top-bar">
  <h1 class="page-title">Medite — dernières tâches</h1>
  <a class="btn-link" href="http://localhost:8080/admin/">Retour</a>
</div>
<p>Affiche les tâches récentes présentes dans Redis (backend Celery).</p>
<table>
<thead><tr><th>Task ID</th><th>Statut</th><th>Comparaison</th><th>Durée</th><th>Terminé</th></tr></thead>
<tbody>{table}</tbody>
</table>
</body></html>"""


@app.route('/health')
def health():
    checks = {}
    status = 'ok'
    http_status = 200

    checks['versions'] = {
        'python': platform.python_version(),
        'flask': getattr(Flask, '__version__', None) or getattr(__import__('flask'), '__version__', None),
        'celery': getattr(Celery, '__version__', None) or getattr(__import__('celery'), '__version__', None),
        'redis_py': getattr(redis, '__version__', None),
        'variance': None,
    }
    try:
        checks['versions']['variance'] = importlib.metadata.version('variance')
    except Exception:
        pass

    try:
        upload_root = Path(app.config['UPLOAD_FOLDER'])
        upload_root.mkdir(parents=True, exist_ok=True)
        probe = upload_root / '.healthcheck'
        probe.write_text('ok', encoding='utf-8')
        probe.unlink(missing_ok=True)
        checks['filesystem'] = {'ok': True, 'path': str(upload_root)}
    except Exception as exc:
        checks['filesystem'] = {'ok': False, 'error': str(exc)}
        status = 'degraded'
        http_status = 503

    checks['diff_script'] = {
        'ok': SCRIPT_DIFF.exists(),
        'path': str(SCRIPT_DIFF),
    }
    if not SCRIPT_DIFF.exists():
        status = 'degraded'
        http_status = 503

    try:
        ping_responses = celery.control.ping(timeout=1.0)
        worker_count = len(ping_responses) if ping_responses else 0
        checks['celery'] = {
            'ok': worker_count > 0,
            'workers': worker_count,
        }
        if worker_count == 0:
            status = 'degraded'
            http_status = 503
    except Exception as exc:
        checks['celery'] = {'ok': False, 'error': str(exc)}
        status = 'degraded'
        http_status = 503

    try:
        redis_ok = bool(redis_conn.ping())
        redis_info = None
        try:
            redis_info = redis_conn.info()
        except Exception:
            redis_info = None
        checks['redis'] = {
            'ok': redis_ok,
            'server_version': redis_info.get('redis_version') if isinstance(redis_info, dict) else None,
        }
        if not redis_ok:
            status = 'degraded'
            http_status = 503
    except Exception as exc:
        checks['redis'] = {'ok': False, 'error': str(exc)}
        status = 'degraded'
        http_status = 503

    try:
        recent_tasks = _iter_task_meta(limit=5)
        checks['tasks'] = {'recent': len(recent_tasks)}
    except Exception as exc:
        checks['tasks'] = {'error': str(exc)}
        if status != 'fail':
            status = 'degraded'
            http_status = 503

    if request.args.get('probe') == '1':
        probe_ok = False
        probe_error = None
        try:
            with tempfile.TemporaryDirectory() as tmpdir:
                src_path = Path(tmpdir) / "source.xml"
                tgt_path = Path(tmpdir) / "target.xml"
                out_path = Path(tmpdir) / "out.xml"
                xhtml_dir = Path(tmpdir) / "xhtml"
                header = (
                    "<teiHeader>"
                    "<fileDesc>"
                    "<titleStmt><title>Probe</title></titleStmt>"
                    "<publicationStmt><p>Probe</p></publicationStmt>"
                    "<sourceDesc><p>Probe</p></sourceDesc>"
                    "</fileDesc>"
                    "</teiHeader>"
                )
                src_path.write_text(
                    f"<TEI xml:id=\"probe-src\">{header}<text><body><div>Bonjour le monde.</div></body></text></TEI>\n",
                    encoding="utf-8",
                )
                tgt_path.write_text(
                    f"<TEI xml:id=\"probe-tgt\">{header}<text><body><div>Bonjour le monde !</div></body></text></TEI>\n",
                    encoding="utf-8",
                )

                command = [
                    sys.executable,
                    str(SCRIPT_DIFF),
                    str(src_path),
                    str(tgt_path),
                    "--lg_pivot", "7",
                    "--ratio", "15",
                    "--seuil", "50",
                    "--no-case-sensitive",
                    "--diacri-sensitive",
                    "--output-xml", str(out_path),
                    "--xhtml-output-dir", str(xhtml_dir),
                ]
                result = subprocess.run(
                    command,
                    stdout=subprocess.PIPE,
                    stderr=subprocess.PIPE,
                    text=True,
                    cwd=str(SCRIPT_DIFF.parent.parent),
                    timeout=20,
                )
                probe_ok = result.returncode == 0 and out_path.exists()
                if not probe_ok:
                    probe_error = (result.stderr or result.stdout)[:500]
        except Exception as exc:
            probe_error = str(exc)

        checks['probe'] = {
            'ok': probe_ok,
            'error': probe_error,
        }
        if not probe_ok:
            status = 'degraded'
            http_status = 503

    return jsonify({
        'status': status,
        'timestamp': datetime.utcnow().isoformat() + 'Z',
        'checks': checks,
    }), http_status


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

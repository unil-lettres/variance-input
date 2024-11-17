from flask import Flask, request, jsonify, render_template, redirect, url_for, send_from_directory
from celery import Celery
import subprocess
import redis
import os

app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = 'uploads'
os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)

app.config['CELERY_BROKER_URL'] = 'redis://redis:6379/0'
app.config['CELERY_RESULT_BACKEND'] = 'redis://redis:6379/0'
celery = Celery(app.name, broker=app.config['CELERY_BROKER_URL'])
celery.conf.update(app.config)

redis_conn = redis.StrictRedis(host='redis', port=6379, db=0)

@app.route('/uploads/<path:filename>')
def uploaded_file(filename):
    """Serve uploaded files."""
    return send_from_directory(app.config['UPLOAD_FOLDER'], filename)

import time

@celery.task
def run_diff_script(source_file, target_file, lg_pivot, ratio, seuil, case_sensitive, diacri_sensitive, output_xml):
    """Run the diff script asynchronously."""
    try:
        command = [
            "poetry", "run", "python", "/app/scripts/diff.py",
            source_file, target_file,
            "--lg_pivot", str(lg_pivot),
            "--ratio", str(ratio),
            "--seuil", str(seuil),
            "--case-sensitive" if case_sensitive else "--no-case-sensitive",
            "--diacri-sensitive" if diacri_sensitive else "--no-diacri-sensitive",
            "--output-xml", output_xml
        ]
        result = subprocess.run(command, capture_output=True, text=True, check=True)

        return {"status": "success", "output": result.stdout}
    except subprocess.CalledProcessError as e:
        return {"status": "error", "error": e.stderr}

@app.route('/')
def home():
    return render_template('index.html')


@app.route('/run_diff', methods=['POST'])
def run_diff():
    """Endpoint to launch the diff script."""
    source_file = request.files['source_file']
    target_file = request.files['target_file']
    lg_pivot = request.form.get('lg_pivot', 7)
    ratio = request.form.get('ratio', 15)
    seuil = request.form.get('seuil', 50)
    case_sensitive = request.form.get('case_sensitive', 'on') == 'on'
    diacri_sensitive = request.form.get('diacri_sensitive', 'on') == 'on'
    output_xml = os.path.join(app.config['UPLOAD_FOLDER'], 'result.xml')

    # Save uploaded files
    source_path = os.path.join(app.config['UPLOAD_FOLDER'], source_file.filename)
    target_path = os.path.join(app.config['UPLOAD_FOLDER'], target_file.filename)
    source_file.save(source_path)
    target_file.save(target_path)

    # Submit the task to Celery
    task = run_diff_script.apply_async(kwargs={
        "source_file": source_path,
        "target_file": target_path,
        "lg_pivot": int(lg_pivot),
        "ratio": int(ratio),
        "seuil": int(seuil),
        "case_sensitive": case_sensitive,
        "diacri_sensitive": diacri_sensitive,
        "output_xml": output_xml
    })

    return redirect(url_for('task_status_page', task_id=task.id))

@app.route('/task_status_page/<task_id>')
def task_status_page(task_id):
    """Render the task status HTML page."""
    return render_template('task_status.html', task_id=task_id)

@app.route('/task_status/<task_id>', methods=['GET'])
def task_status(task_id):
    """Check the status of a Celery task."""
    task = run_diff_script.AsyncResult(task_id)
    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
        if task.state == 'PENDING':
            return jsonify({"status": "pending"})
        elif task.state == 'SUCCESS':
            return jsonify({"status": "completed", "task_id": task_id})
        elif task.state == 'FAILURE':
            return jsonify({"status": "failed", "error": str(task.info)})
        else:
            return jsonify({"status": task.state})
    return "Invalid request", 400

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)

from flask import Flask, request, jsonify
from celery import Celery
import subprocess

app = Flask(__name__)

# Configure Celery
app.config['CELERY_BROKER_URL'] = 'redis://redis:6379/0'
app.config['CELERY_RESULT_BACKEND'] = 'redis://redis:6379/0'
celery = Celery(app.name, broker=app.config['CELERY_BROKER_URL'])
celery.conf.update(app.config)


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
    return "Welcome to the Medite Flask API! Use /run_diff to start the script."


@app.route('/run_diff', methods=['POST'])
def run_diff():
    """Endpoint to launch the diff script."""
    data = request.json
    task = run_diff_script.apply_async(kwargs={
        "source_file": data['source_file'],
        "target_file": data['target_file'],
        "lg_pivot": data.get('lg_pivot', 7),
        "ratio": data.get('ratio', 15),
        "seuil": data.get('seuil', 50),
        "case_sensitive": data.get('case_sensitive', True),
        "diacri_sensitive": data.get('diacri_sensitive', True),
        "output_xml": data.get('output_xml', "result.xml")
    })
    return jsonify({"task_id": task.id, "status_url": f"/task_status/{task.id}"}), 202


@app.route('/task_status/<task_id>', methods=['GET'])
def task_status(task_id):
    """Check the status of a Celery task."""
    task = run_diff_script.AsyncResult(task_id)
    if task.state == 'PENDING':
        response = {"status": "pending"}
    elif task.state == 'SUCCESS':
        response = {"status": "completed", "result": task.result}
    elif task.state == 'FAILURE':
        response = {"status": "failed", "error": str(task.info)}
    else:
        response = {"status": task.state}
    return jsonify(response)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)

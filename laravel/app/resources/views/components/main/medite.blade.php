<div class="card">
    <div class="card-header">
        Medite Script
    </div>
    <div class="card-body">
        <form id="medite-form">
            @csrf
            <div class="mb-3">
                <label for="source_version" class="form-label">Source Version</label>
                <select id="source_version" name="source_version" class="form-control" required>
                    <option value="">Select Source Version</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="target_version" class="form-label">Target Version</label>
                <select id="target_version" name="target_version" class="form-control" required>
                    <option value="">Select Target Version</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="lg_pivot" class="form-label">Pivot Length</label>
                <input type="number" id="lg_pivot" name="lg_pivot" class="form-control" value="7" required>
            </div>
            <div class="mb-3">
                <label for="ratio" class="form-label">Ratio</label>
                <input type="number" id="ratio" name="ratio" class="form-control" value="15" required>
            </div>
            <div class="mb-3">
                <label for="seuil" class="form-label">Threshold</label>
                <input type="number" id="seuil" name="seuil" class="form-control" value="50" required>
            </div>
            <div class="form-check">
                <input type="checkbox" id="case_sensitive" name="case_sensitive" class="form-check-input" checked>
                <label class="form-check-label" for="case_sensitive">Case Sensitive</label>
            </div>
            <div class="form-check">
                <input type="checkbox" id="diacri_sensitive" name="diacri_sensitive" class="form-check-input" checked>
                <label class="form-check-label" for="diacri_sensitive">Diacritical Sensitive</label>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Run Medite</button>
        </form>

        <!-- Progress Indicator -->
        <div id="progress-indicator" class="mt-4" style="display: none;">
            <p>Processing... <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span></p>
        </div>

        <!-- Results Section -->
        <div id="results" class="mt-4" style="display: none;">
            <h5>Results</h5>
            <a id="result-html" href="#" target="_blank">View HTML Result</a><br>
            <a id="result-xml" href="#" target="_blank">Download XML Result</a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sourceVersionDropdown = document.getElementById('source_version');
        const targetVersionDropdown = document.getElementById('target_version');
        const progressIndicator = document.getElementById('progress-indicator');
        const resultsDiv = document.getElementById('results');
        const resultHtml = document.getElementById('result-html');
        const resultXml = document.getElementById('result-xml');

        // Listen for workSelected event
        document.addEventListener('workSelected', async (event) => {
            const { workId } = event.detail;
            sourceVersionDropdown.innerHTML = '<option value="">Select Source Version</option>';
            targetVersionDropdown.innerHTML = '<option value="">Select Target Version</option>';

            try {
                const response = await fetch(`/versions?work_id=${workId}`);
                if (!response.ok) throw new Error(`Failed to fetch versions: ${response.statusText}`);

                const versions = await response.json();

                if (versions.length === 0) {
                    const noVersionOption = document.createElement('option');
                    noVersionOption.textContent = 'No versions available';
                    noVersionOption.disabled = true;
                    sourceVersionDropdown.appendChild(noVersionOption.cloneNode(true));
                    targetVersionDropdown.appendChild(noVersionOption.cloneNode(true));
                    return;
                }

                versions.forEach(version => {
                    const option = document.createElement('option');
                    option.value = version.id;
                    option.textContent = version.name;
                    sourceVersionDropdown.appendChild(option);

                    const targetOption = option.cloneNode(true);
                    targetVersionDropdown.appendChild(targetOption);
                });
            } catch (error) {
                console.error('Error fetching versions:', error);
            }
        });

        // Handle form submission
        document.getElementById('medite-form').addEventListener('submit', async function (event) {
            event.preventDefault();

            if (!sourceVersionDropdown.value || !targetVersionDropdown.value) {
                alert('Please select both source and target versions.');
                return;
            }

            const formData = new FormData(this);

            // Show progress indicator and hide results
            progressIndicator.style.display = 'block';
            resultsDiv.style.display = 'none';

            try {
                const response = await fetch('/api/run_medite', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                });

                if (!response.ok) throw new Error('Failed to submit Medite task');

                const data = await response.json();
                const taskId = data.task_id;

                // Poll for task status
                let retryCount = 0;
                const maxRetries = 10;

                const poll = async () => {
                    if (retryCount >= maxRetries) {
                        progressIndicator.textContent = 'Task timed out. Please try again.';
                        return;
                    }

                    retryCount++;

                    const taskResponse = await fetch(`/api/task_status/${taskId}`);
                    const taskData = await taskResponse.json();

                    if (taskData.status === 'pending') {
                        setTimeout(poll, 2000); // Poll every 2 seconds
                    } else if (taskData.status === 'completed') {
                        progressIndicator.style.display = 'none';
                        resultsDiv.style.display = 'block';
                        resultHtml.href = `/uploads/result.html`;
                        resultHtml.textContent = "View HTML Result";
                        resultXml.href = `/uploads/result.xml`;
                        resultXml.textContent = "Download XML Result";

                        await fetch('/api/create_comparison', {
                            method: 'POST',
                            body: JSON.stringify({
                                source_id: formData.get('source_version'),
                                target_id: formData.get('target_version'),
                                folder: 'uploads',
                                number: Math.random(),
                                prefix_label: 'Comparison between versions',
                            }),
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Content-Type': 'application/json',
                            },
                        });
                    } else if (taskData.status === 'failed') {
                        progressIndicator.textContent = 'Task failed. Please try again.';
                    }
                };

                poll();
            } catch (error) {
                console.error(error);
                progressIndicator.textContent = 'An error occurred. Please try again.';
            }
        });
    });
</script>
@endpush

{{--  components.main.saxon  --}}
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Conversion TEI → XHTML (Saxon)</span>
        <button id="run-xhtml-btn" class="btn btn-sm btn-primary" disabled>
            Générer XHTML
        </button>
    </div>

    <div class="card-body">
        <p class="text-muted mb-2">
            Sélectionnez une ligne dans le tableau des comparaisons puis cliquez sur
            <em>Générer XHTML</em>.  
            Les fichiers générés seront placés dans le même dossier que le XML de la comparaison
            (<code>uploads/comparisons/{id}</code>).
        </p>

        <div id="xhtml-log" class="small" style="white-space: pre;"></div>

        <ul id="xhtml-links" class="list-unstyled mt-3"></ul>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btn    = document.getElementById('run-xhtml-btn');
    const logBox = document.getElementById('xhtml-log');
    const linkUL = document.getElementById('xhtml-links');

    let selectedComparisonId = null;

    /* ------------------------------------------------------------------
     * Pick up click on a row in the comparisons table (zone-5 component)
     * ----------------------------------------------------------------*/
    document.addEventListener('click', e => {
        const row = e.target.closest('#comparisons-table tbody tr');
        if (!row) return;

        // highlight selection
        document
            .querySelectorAll('#comparisons-table tbody tr')
            .forEach(r => r.classList.remove('table-primary'));
        row.classList.add('table-primary');

        selectedComparisonId = row.cells[0].textContent.trim(); // first column = id
        btn.disabled = false;
        logBox.textContent = '';
        linkUL.innerHTML   = '';
    });

    /* ------------------------------------------------------------------
     * Call /api/run_xhtml when user clicks the button
     * ----------------------------------------------------------------*/
    btn.addEventListener('click', async () => {
        if (!selectedComparisonId) return;

        btn.disabled = true;
        logBox.textContent = '⏳ Lancement de Saxon…';
        linkUL.innerHTML   = '';

        const payload = {
            input_xml : `uploads/comparisons/${selectedComparisonId}.xml`,
            output_dir: `uploads/comparisons/${selectedComparisonId}`
        };

        try {
            const res = await fetch('/api/run_xhtml', {
                method : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body   : JSON.stringify(payload)
            });

            const data = await res.json();

            if (!res.ok || data.error) {
                throw new Error(data.error || 'Erreur inconnue');
            }

            logBox.textContent = '✅ Conversion réussie :';
            data.files.forEach(f => {
                const li = document.createElement('li');
                li.innerHTML =
                    `<a href="/storage/uploads/comparisons/${selectedComparisonId}/${f}"
                        target="_blank" class="link-secondary">${f}</a>`;
                linkUL.appendChild(li);
            });

        } catch (err) {
            logBox.textContent = '❌ ' + err.message;
        } finally {
            btn.disabled = false;
        }
    });
});
</script>
@endpush

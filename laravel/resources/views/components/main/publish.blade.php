<div class="card mb-3">
    <div class="card-header">Publier les XHTML</div>

    <div class="card-body">
        <button id="publish-btn" class="btn btn-success btn-sm" disabled>
            Publier vers le site
        </button>

        <div id="publish-spinner"
             class="spinner-border spinner-border-sm ms-2"
             style="display:none;"></div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {

    const publishBtn  = document.getElementById('publish-btn');
    const spinner     = document.getElementById('publish-spinner');
    const tbody       = document.querySelector('#comparisons-table tbody');

    /** Identifiant de la comparaison courante */
    let comparisonId = null;

    /* ───────────────────────────────────────────────────────────
     * 1. Sélection d’une ligne du tableau des comparaisons
     * ─────────────────────────────────────────────────────────── */
    if (tbody) {
        tbody.addEventListener('click', (e) => {
            const row = e.target.closest('tr');
            if (!row || !row.dataset.id) return;

            /* mise en surbrillance de la ligne cliquée */
            tbody.querySelectorAll('tr.table-active')
                 .forEach(tr => tr.classList.remove('table-active'));
            row.classList.add('table-active');

            comparisonId        = row.dataset.id;
            publishBtn.disabled = false;
        });
    }

    /* ───────────────────────────────────────────────────────────
     * 2. Envoi vers /api/publish_xhtml
     * ─────────────────────────────────────────────────────────── */
    publishBtn.addEventListener('click', async () => {
        if (!comparisonId) return;

        publishBtn.disabled   = true;
        spinner.style.display = 'inline-block';

        try {
            const res  = await fetch('/api/publish_xhtml', {
                method : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept'      : 'application/json'
                },
                body   : JSON.stringify({ comparison_id: comparisonId })
            });

            const raw  = await res.text();
            let   json = {};
            try { json = JSON.parse(raw); } catch { /* HTML ou texte brut */ }

            /* 404 renvoyé par TransformServer → XML absent */
            if (res.status === 404) {
                throw new Error('Le fichier XML source est introuvable.');
            }

            /* toute autre erreur HTTP ou réponse JSON avec "error" */
            if (!res.ok || json.error || json.status !== 'ok') {
                throw new Error(json.error ?? 'Erreur inconnue');
            }

            /* Succès : json.files contient la liste générée */
            alert('Fichiers publiés dans :\n' + json.published_to);

        } catch (err) {
            console.error(err);
            alert('Publication échouée : ' + err.message);
        } finally {
            spinner.style.display = 'none';
            /* on ré-active le bouton si une ligne reste sélectionnée */
            publishBtn.disabled = !comparisonId;
        }
    });

});
</script>
@endpush

function exportDatabase() {
    showToast('Export en cours...', { title: 'Base de données', type: 'info', duration: 3000 });

    $.ajax({
        url: URL_APP + 'adminSecurity/databaseExport',
        type: 'POST',
        xhrFields: { responseType: 'blob' },   // indispensable
        success: function (blob, status, xhr) {
            // Nom de fichier depuis l'en-tête Content-Disposition
            let filename = 'export.sql';
            const disposition = xhr.getResponseHeader('Content-Disposition');
            if (disposition) {
                const m = disposition.match(/filename="?([^"]+)"?/);
                if (m) filename = m[1];
            }

            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            showToast('Export terminé', { title: 'Base de données', type: 'success' });
        },
        error: function (xhr) {
            // La réponse d'erreur est aussi un blob -> on la lit en texte
            if (xhr.response instanceof Blob) {
                xhr.response.text().then(function (txt) {
                    showToast(txt || 'Échec de l\'export', { title: 'Erreur', type: 'error', duration: 5000 });
                });
            } else {
                showToast('Échec de l\'export', { title: 'Erreur', type: 'error' });
            }
        }
    });
}

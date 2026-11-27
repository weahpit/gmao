/* Enregistrement unique ; renouvellement du CSRF uniquement apres un refus explicite avant sauvegarde.
   Aucun rechargement automatique et aucune repetition apres une erreur reseau ou un succes ambigu. */
(() => {
    'use strict';
    const form = document.getElementById('export-form');
    if (!form || form.dataset.saveHandler === '1') return;
    form.dataset.saveHandler = '1';
    const zone = document.getElementById('export-erreur');
    const afficher = texte => { if (zone) zone.textContent = texte; };
    const adresse = () => new URL(form.action || location.href, location.href);

    function erreurHTTP(response, data) {
        const textes = [data?.message, data?.error, data?.detail, data?.title];
        const texte = textes.find(v => typeof v === 'string' && v.trim() !== '');
        if (texte) return texte.slice(0, 1500);
        const codes = {
            401: 'Connexion expiree : reconnectez-vous dans un autre onglet puis reessayez ici. La saisie reste sur cette page.',
            403: 'Acces refuse (HTTP 403). Verifiez votre connexion et vos droits logistiques. La saisie reste sur cette page.',
            404: 'Adresse d’enregistrement introuvable (HTTP 404). Verifiez le deploiement des routes.',
            405: 'La route n’accepte pas cet envoi (HTTP 405). Verifiez le deploiement des routes.',
            413: 'Envoi trop volumineux (HTTP 413). Reduisez les photos ou le rapport ; verifiez les limites PHP et du serveur web.',
            422: 'Saisie non valide (HTTP 422). Verifiez les champs obligatoires, les lots et les poids.',
            500: 'Erreur serveur (HTTP 500). Consultez le journal a l’heure de cet essai. Votre saisie reste sur cette page.'
        };
        return codes[response.status] || `Le serveur a retourne HTTP ${response.status}. Votre saisie est conservee sur cette page.`;
    }

    async function envoyer(data) {
        const url = adresse();
        if (url.origin !== location.origin) throw new Error('Adresse d’enregistrement invalide.');
        const response = await fetch(url, {
            method: 'POST', body: data, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        const texte = await response.text();
        let result = null;
        try { result = JSON.parse(texte); } catch { /* Une page HTML n'est pas un succes JSON. */ }
        return { response, result };
    }

    async function renouvelerJeton(data) {
        afficher('Formulaire expire : renouvellement du jeton, sans effacer votre saisie…');
        const response = await fetch(adresse(), {
            credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'text/html' }
        });
        if (!response.ok || response.redirected) {
            throw new Error('Session expiree : reconnectez-vous dans un autre onglet, puis reessayez ici. Votre saisie reste conservee.');
        }
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const token = doc.querySelector('#export-form input[name="_token"]')?.value;
        if (!token) throw new Error('Impossible de renouveler le formulaire. Reconnectez-vous dans un autre onglet, puis reessayez ici.');
        const champ = form.querySelector('input[name="_token"]');
        if (champ) champ.value = token;
        data.set('_token', token);
    }

    form.addEventListener('submit', async event => {
        if (!window.fetch || !window.FormData) return; // POST classique si AJAX indisponible
        event.preventDefault();
        if (form.dataset.busy === '1') return;
        if (!form.reportValidity()) return;
        form.dataset.busy = '1';
        const bouton = document.getElementById('export-enregistrer');
        const data = new FormData(form); // avant de desactiver le bouton : garder tous les champs
        const etaitDesactive = bouton?.disabled;
        if (bouton) bouton.disabled = true;
        afficher('Enregistrement en cours…');
        try {
            let { response, result } = await envoyer(data);
            // Ce code est emis uniquement AVANT la transaction et les televersements.
            if (response.status === 403 && result?.code === 'FORMULAIRE_EXPIRE') {
                await renouvelerJeton(data);
                ({ response, result } = await envoyer(data)); // un seul nouvel essai
            }
            if (!response.ok) throw new Error(erreurHTTP(response, result));
            if (response.redirected || !result || typeof result !== 'object') {
                throw new Error('Reponse inattendue : ne renvoyez pas immediatement le dossier. Verifiez d’abord la liste des OT pour savoir s’il a ete enregistre. Votre saisie reste sur cette page.');
            }
            if (result.redirection) {
                const cible = new URL(result.redirection, location.href);
                if (cible.origin !== location.origin) throw new Error('Dossier enregistre, mais adresse de suivi invalide. Consultez la liste des OT.');
                location.assign(cible.href);
                return;
            }
            afficher(result.message || 'Enregistrement confirme par le serveur.');
        } catch (error) {
            afficher(error instanceof TypeError
                ? 'Connexion interrompue. Avant de reessayer, verifiez la liste des OT dans un autre onglet : le serveur a peut-etre termine l’enregistrement. Votre saisie reste sur cette page.'
                : error.message);
        } finally {
            delete form.dataset.busy;
            if (bouton) bouton.disabled = !!etaitDesactive;
        }
    });
})();

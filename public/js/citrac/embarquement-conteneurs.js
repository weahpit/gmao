/* CITRAC : ajout des lots et des conteneurs au formulaire d'embarquement. */
(function () {
    'use strict';
    const lotsTc = [];
    const conteneurs = [];
    const $id = id => document.getElementById(id);
    let chargementLot = 0;
    let lotCourant = null;
    let produitLots = '';
    // Etat disponible pour le code d'enregistrement existant, sans changer son API.
    window.citracConteneurs = conteneurs;
    const avertir = msg => showToast(msg, 'warning');
    function nombre(valeur) {
        const texte = String(valeur ?? '').trim().replace(/\s|\u00a0|\u202f/g, '').replace(/(?:kg|tonnes?|sacs?|cartons?|bigbag)$/i, '').replace(',', '.');
        return texte !== '' && /^\d+(\.\d+)?$/.test(texte) ? Number(texte) : NaN;
    }
    const format = n => Number(n).toLocaleString('fr-FR', { maximumFractionDigits: 2 });
    function cellule(ligne, valeur) {
        const td = ligne.insertCell();
        td.textContent = String(valeur);
        return td;
    }
    function boutonRetirer(ligne, action) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-danger btn-sm';
        btn.textContent = 'Retirer';
        btn.addEventListener('click', action);
        ligne.insertCell().appendChild(btn);
    }
    function reserve(id) {
        return conteneurs.flatMap(tc => tc.lots).concat(lotsTc)
            .filter(l => String(l.id) === String(id))
            .reduce((s, l) => s + l.qteExporter, 0);
    }
    function viderChampsLot() {
        lotCourant = null;
        ['nb_cartons', 'pb', 'pn', 'qte_a_exporter'].forEach(id => { $id(id).value = ''; });
    }
    function afficherLots() {
        const tbody = $id('select_lot_tbody');
        tbody.replaceChildren();
        lotsTc.forEach((lot, index) => {
            const tr = tbody.insertRow();
            [lot.numero, format(lot.nbCartons), format(lot.pb), format(lot.pn), format(lot.qteExporter), format(lot.qteRestante)].forEach(v => cellule(tr, v));
            boutonRetirer(tr, () => {
                lotsTc.splice(index, 1);
                afficherLots();
                $id('select_lot').dispatchEvent(new Event('change'));
            });
        });
    }
    function afficherConteneurs() {
        const tbody = $id('tbody_tcs');
        tbody.replaceChildren();
        conteneurs.forEach((tc, index) => {
            const tr = tbody.insertRow();
            cellule(tr, index + 1);
            cellule(tr, tc.tc + ' (' + tc.typeTc + ')' + (tc.vrac ? ' — Vrac' : ''));
            cellule(tr, tc.plomb || '—');
            const detail = tr.insertCell();
            tc.lots.forEach(l => {
                const ligne = document.createElement('div');
                ligne.textContent = l.numero + ' : ' + format(l.qteExporter) + ' kg';
                detail.appendChild(ligne);
            });
            cellule(tr, format(tc.lots.reduce((s, l) => s + l.nbCartons, 0)));
            cellule(tr, format(tc.lots.reduce((s, l) => s + l.pbExporter, 0)));
            cellule(tr, format(tc.lots.reduce((s, l) => s + l.qteExporter, 0)));
            boutonRetirer(tr, () => {
                conteneurs.splice(index, 1);
                afficherConteneurs();
                $id('select_lot').dispatchEvent(new Event('change'));
            });
        });
    }
    $id('ajt_tc').addEventListener('click', () => {
        const produit = $id('code_produit').value;
        if (!produit || produit === '0' || produit === 'null') {
            avertir('Sélectionnez d’abord un produit.');
            return;
        }
        if (produitLots && produitLots !== produit && (conteneurs.length || lotsTc.length)) {
            $id('code_produit').value = produitLots;
            avertir('Retirez les conteneurs et les lots avant de changer de produit.');
            return;
        }
        produitLots = produit;
        viderChampsLot();
        getAllLotsByTypeProduits($id('select_lot'), 2, produit);
        afficherLots();
    });
    $id('formTc').addEventListener('submit', event => event.preventDefault());
    $id('select_lot').addEventListener('change', async () => {
        const id = $id('select_lot').value;
        const numeroRequete = ++chargementLot;
        viderChampsLot();
        if (!id || id === '0' || id === 'null') return;
        const form = new FormData();
        form.append('id_lot', id);
        try {
            const lot = await getSingleLot(form);
            if (numeroRequete !== chargementLot || $id('select_lot').value !== id) return;
            const pnTotal = nombre(lot.pn_disponible ?? lot.pn_th);
            const pnReference = nombre(lot.pn_th);
            const pbTotal = nombre(lot.pb_th);
            const restant = Math.max(0, pnTotal - reserve(id));
            if (!Number.isFinite(pnTotal) || !Number.isFinite(pbTotal) || !Number.isFinite(pnReference) || pnReference <= 0 || pnTotal <= 0) {
                avertir('Ce lot ne possède pas de poids disponible valide.');
                return;
            }
            lotCourant = { id, pnTotal, pnReference, pbTotal, restant };
            $id('nb_cartons').value = Number.isFinite(nombre(lot.nbSacs)) ? nombre(lot.nbSacs) : '';
            $id('pb').value = (pbTotal * restant / pnReference).toFixed(2);
            $id('pn').value = restant.toFixed(2);
            $id('qte_a_exporter').focus();
        } catch (_) {
            if (numeroRequete === chargementLot) avertir('Impossible de charger le lot. Réessayez ou reconnectez-vous.');
        }
    });
    $id('add-lot').addEventListener('click', () => {
        const id = $id('select_lot').value;
        if (!lotCourant || lotCourant.id !== id) return avertir('Sélectionnez un lot et attendez son chargement.');
        if (lotsTc.some(l => l.id === id)) return avertir('Ce lot est déjà ajouté à ce conteneur.');
        const qte = nombre($id('qte_a_exporter').value);
        const nb = nombre($id('nb_cartons').value);
        const disponible = Math.max(0, lotCourant.pnTotal - reserve(id));
        if (!Number.isFinite(qte) || qte <= 0 || qte > disponible + 0.000001) return avertir('La quantité à exporter doit être positive et ne pas dépasser le poids net disponible.');
        if (!Number.isSafeInteger(nb) || nb < 0) return avertir('Renseignez un nombre entier de cartons / sacs / big bags (0 pour du vrac).');
        lotsTc.push({
            id, numero: $id('select_lot').selectedOptions[0].textContent,
            nbCartons: nb, pb: lotCourant.pbTotal * disponible / lotCourant.pnReference,
            pn: disponible, qteExporter: qte, qteRestante: disponible - qte,
            pbExporter: lotCourant.pbTotal * qte / lotCourant.pnReference
        });
        afficherLots();
        $id('select_lot').selectedIndex = 0;
        viderChampsLot();
    });
    $id('btn-add-tc').addEventListener('click', () => {
        if ($id('code_produit').value !== produitLots) return avertir('Le produit a changé. Rétablissez le produit sélectionné avant de valider les lots.');
        const tc = $id('tc').value.trim().toUpperCase();
        const typeTc = $id('type_tc').value;
        if (!tc) return avertir('Le numéro du conteneur est obligatoire.');
        if (!['Tc20', 'Tc40'].includes(typeTc)) return avertir('Sélectionnez le type de conteneur.');
        if (!lotsTc.length) return avertir('Ajoutez au moins un lot au conteneur.');
        if (conteneurs.some(c => c.tc === tc)) return avertir('Ce conteneur a déjà été ajouté.');
        conteneurs.push({ tc, typeTc, plomb: $id('plomb').value.trim().toUpperCase(), vrac: $id('vrac').checked, lots: lotsTc.map(l => ({ ...l })) });
        lotsTc.splice(0);
        afficherConteneurs();
        afficherLots();
        $id('formTc').reset();
        viderChampsLot();
        if (window.bootstrap && bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance($id('modalTc'));
            if (modal) modal.hide();
        }
    });
})();

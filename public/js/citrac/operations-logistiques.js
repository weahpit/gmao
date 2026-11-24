/* CITRAC : formulaire d'embarquement, volumes en grammes entiers. */
(function () {
    'use strict';
    const init = () => {
        const form = document.getElementById('export-form');
        if (!form || form.dataset.exportReady) return;
        form.dataset.exportReady = '1';
        const el = id => document.getElementById(id);
        const parse = id => { try { const v = JSON.parse(el(id).value || '[]'); return Array.isArray(v) ? v : []; } catch (_) { return []; } };
        const lots = parse('export-lots');
        const conteneurs = parse('export-conteneurs');
        const initial = parse('export-affectations-initiales');
        const format = grammes => (grammes / 1000).toLocaleString('fr-FR', {minimumFractionDigits: 3, maximumFractionDigits: 3});
        const grams = v => {
            const s = String(v == null ? '' : v).trim().replace(/\s/g, '').replace(',', '.');
            if (s === '') return 0;
            const m = /^(\d{1,10})(?:\.(\d{1,3}))?$/.exec(s);
            return m ? Number(m[1]) * 1000 + Number((m[2] || '').padEnd(3, '0')) : NaN;
        };
        const node = (tag, text, cls) => { const n = document.createElement(tag); if (text != null) n.textContent = text; if (cls) n.className = cls; return n; };
        const button = (text, action) => { const b = node('button', text, 'exp-btn'); b.type = 'button'; b.addEventListener('click', action); return b; };
        const input = (type, value, action) => { const n = node('input'); n.type = type; n.value = value == null ? '' : value; n.addEventListener('input', action); return n; };
        const field = (parent, label, control) => { const f = node('div', null, 'exp-field'); const l = node('label', label); l.appendChild(control); f.appendChild(l); parent.appendChild(f); };
        const ancienneReservation = {};
        parse('export-reservations-originales').forEach(tc => (tc.lots || []).forEach(l => { ancienneReservation[l.id] = (ancienneReservation[l.id] || 0) + (grams(l.qteExporter) || 0); }));
        let compteur = 0;
        function totalTc() { return conteneurs.reduce((s, tc) => s + (tc.lots || []).reduce((v, l) => v + (grams(l.qteExporter) || 0), 0), 0); }
        function totalFormules() { return Array.from(el('export-formules').querySelectorAll('input')).reduce((s, n) => s + (grams(n.value) || 0), 0); }
        function recalculer() {
            const tc = totalTc(), f = totalFormules();
            el('export-conteneurs').value = JSON.stringify(conteneurs);
            el('export-total').textContent = format(tc); el('export-couvert').textContent = format(f);
            el('export-ecart').textContent = tc === 0 ? (f > 0 ? 'Volumes préparés — aucune consommation avant chargement' : 'À compléter ultérieurement') : f === tc ? 'Couverture complète' : (f < tc ? 'Reste à couvrir : ' + format(tc - f) + ' kg' : 'Excédent : ' + format(f - tc) + ' kg');
            el('export-ecart').className = f > tc && tc > 0 ? 'exp-error' : '';
        }
        function afficherTc() {
            el('export-tcs').replaceChildren();
            conteneurs.forEach((tc, i) => {
                const card = node('div', null, 'exp-tc'); const grid = node('div', null, 'exp-grid');
                card.appendChild(node('h3', 'Conteneur ' + (i + 1)));
                const numero = input('text', tc.tc, e => { tc.tc = e.target.value.toUpperCase(); recalculer(); }); numero.maxLength = 50; 
                field(grid, 'N° conteneur', numero);
                const type = node('select'); ['Tc20','Tc40'].forEach(t => { const o = node('option', t === 'Tc20' ? '20 pieds' : '40 pieds'); o.value = t; type.appendChild(o); }); type.value = tc.typeTc || 'Tc20'; type.addEventListener('change', () => { tc.typeTc = type.value; recalculer(); }); field(grid, 'Type', type);
                const plomb = input('text', tc.plomb, e => { tc.plomb = e.target.value; recalculer(); }); plomb.maxLength = 100; field(grid, 'N° plomb', plomb);
                const vrac = input('checkbox', '', e => { tc.vrac = e.target.checked; recalculer(); }); vrac.checked = Boolean(tc.vrac); field(grid, 'Chargement en vrac', vrac);
                card.appendChild(grid);
                const scroll = node('div', null, 'exp-scroll'); const table = node('table', null, 'exp-table'); table.dataset.pagination = 'off';
                const head = node('thead'); const htr = node('tr'); ['Lot / stock disponible', 'Nombre de colis', 'Poids net chargé (kg)', 'Action'].forEach(t => htr.appendChild(node('th', t))); head.appendChild(htr); table.appendChild(head); const body = node('tbody');
                (tc.lots || []).forEach((l, j) => {
                    const tr = node('tr'), td = node('td'), select = node('select');  select.appendChild(node('option', 'Sélectionnez un lot')); select.firstChild.value = '';
                    lots.filter(lot => lot.produit === el('produit').value || lot.id === l.id).forEach(lot => { const o = node('option', lot.numero + ' — ' + format((grams(lot.pn) || 0) + (ancienneReservation[lot.id] || 0)) + ' kg'); o.value = lot.id; select.appendChild(o); });
                    select.value = l.id || ''; select.addEventListener('change', () => { l.id = select.value; recalculer(); }); td.appendChild(select); tr.appendChild(td);
                    const colis = input('number', l.nbCartons, e => { l.nbCartons = e.target.value; recalculer(); }); colis.min = '0'; colis.max = '10000000'; colis.step = '1'; 
                    const poids = input('number', l.qteExporter, e => { l.qteExporter = e.target.value; recalculer(); }); poids.min = '0'; poids.step = '0.001'; 
                    [colis,poids].forEach(n => { const c = node('td'); c.appendChild(n); tr.appendChild(c); });
                    const rm = node('td'); rm.appendChild(button('Retirer le lot', () => { tc.lots.splice(j,1); afficherTc(); recalculer(); })); tr.appendChild(rm); body.appendChild(tr);
                });
                table.appendChild(body); scroll.appendChild(table); card.appendChild(scroll);
                const actions = node('div', null, 'exp-actions'); actions.appendChild(button('+ Ajouter un lot', () => { tc.lots.push({id:'',nbCartons:0,qteExporter:''}); afficherTc(); recalculer(); })); actions.appendChild(button('Retirer le conteneur', () => { conteneurs.splice(i,1); afficherTc(); recalculer(); })); card.appendChild(actions); el('export-tcs').appendChild(card);
            });
        }
        function filtreFormules() {
            el('export-formules').querySelectorAll('select').forEach(select => { Array.from(select.options).forEach(o => { if (o.value) { const autre = o.dataset.proprietaire !== el('proprietaire').value; o.hidden = autre; o.disabled = autre; } }); });
        }
        function ajouterFormule(a) {
            const tr = node('tr'); const select = node('select'); 
            select.appendChild(el('export-formule-options').content.cloneNode(true)); select.name = 'formules[' + compteur + '][id]'; select.value = a && a.id || '';
            const poids = input('number', a && a.volume || '', recalculer); poids.name = 'formules[' + compteur + '][volume]'; poids.min = '0'; poids.step = '0.001'; 
            select.addEventListener('change', () => { const o = select.selectedOptions[0]; poids.max = o && o.dataset.volume || ''; recalculer(); });
            if (select.selectedOptions[0]) poids.max = select.selectedOptions[0].dataset.volume || '';
            [select,poids].forEach(n => { const c = node('td'); c.appendChild(n); tr.appendChild(c); }); const c = node('td'); c.appendChild(button('Retirer', () => { tr.remove(); recalculer(); })); tr.appendChild(c); el('export-formules').appendChild(tr); compteur++; filtreFormules(); recalculer();
        }
        let derniereRequete = 0;
        async function documents() {
            const sequence = ++derniereRequete;
            el('export-doc-liste').replaceChildren();
            if (!el('produit').value || !el('client').value) { el('export-doc-modele').textContent = 'Sélectionnez un produit et un client.'; return; }
            el('export-doc-modele').textContent = 'Chargement des documents attendus…';
            try {
                const url = new URL(form.dataset.documentsUrl, window.location.origin); url.searchParams.set('produit', el('produit').value); url.searchParams.set('client', el('client').value); if(form.dataset.dossier) url.searchParams.set('dossier',form.dataset.dossier);
                const r = await fetch(url, {credentials:'same-origin', headers:{'Accept':'application/json'}});
                if (!r.ok) throw new Error('Lecture impossible'); const data = await r.json(); if (sequence !== derniereRequete) return;
                el('export-doc-modele').textContent = data.documents.length ? (data.dossier ? 'Documents suivis pour ce dossier' : data.personnalise ? 'Liste spécifique au client' : 'Liste standard du produit') : 'Aucun document configuré : vous pouvez enregistrer et définir la liste plus tard.';
                data.documents.forEach(d => {
                    const ligne=node('li',d.libelle);
                    if(d.statut) ligne.appendChild(node('span',' — '+d.statut));
                    if(data.dossier && !d.fichier) ligne.appendChild(node('span',' — Aucun fichier chargé'));
                    [['visualiser','Visualiser'],['telecharger','Télécharger']].forEach(([cle,texte])=>{
                        if(!d[cle])return;
                        const lien=new URL(d[cle],window.location.origin);
                        if(lien.origin!==window.location.origin || !['http:','https:'].includes(lien.protocol))return;
                        const a=node('a',texte,'exp-btn');a.href=lien.href;a.target='_blank';a.rel='noopener noreferrer';
                        a.setAttribute('aria-label',texte+' — '+d.libelle);a.style.marginLeft='.5rem';ligne.appendChild(a);
                    });
                    el('export-doc-liste').appendChild(ligne);
                });
            } catch (_) { if (sequence === derniereRequete) el('export-doc-modele').textContent = 'Impossible de charger les documents. Rechargez la page ou vérifiez le modèle.'; }
        }
        const rubriqueDocuments=el('export-doc-rubrique');
        if(rubriqueDocuments) rubriqueDocuments.addEventListener('toggle',()=>{if(rubriqueDocuments.open)documents();});
        el('export-add-tc').addEventListener('click', () => { conteneurs.push({tc:'',typeTc:'Tc20',plomb:'',vrac:false,lots:[{id:'',nbCartons:0,qteExporter:''}]}); afficherTc(); recalculer(); });
        el('export-add-formule').addEventListener('click', () => ajouterFormule(null));
        el('produit').addEventListener('change', () => { afficherTc(); documents(); }); el('client').addEventListener('change', documents);
        el('proprietaire').addEventListener('change', () => { Array.from(el('produit').options).forEach(o => { if (o.value) { o.hidden = Boolean(o.dataset.proprietaire && o.dataset.proprietaire !== el('proprietaire').value); o.disabled = o.hidden; } }); if (el('produit').selectedOptions[0]?.disabled) { el('produit').value = ''; afficherTc(); documents(); } filtreFormules(); });
        form.addEventListener('submit', e => {
            recalculer(); let erreur = ''; const total = totalTc(); const affecte = totalFormules();
            if (total > 0 && affecte > total) erreur = 'Les volumes imputés aux formules dépassent le poids net chargé.';
            const vus = new Set(); conteneurs.forEach(tc => {
                const num = String(tc.tc || '').trim().toUpperCase();
                if (num && vus.has(num)) erreur = 'Numéro de conteneur en doublon.';
                if (num) vus.add(num);
                (tc.lots || []).forEach(l => {
                    if (!l.id && (grams(l.qteExporter) > 0 || Number(l.nbCartons) > 0)) erreur = 'Choisissez le lot correspondant au poids ou aux colis saisis.';
                    if (!Number.isFinite(grams(l.qteExporter))) erreur = 'Poids du lot invalide.';
                });
            });
            const formulesVues = new Set(); el('export-formules').querySelectorAll('tr').forEach(row => {
                const s=row.querySelector('select'), p=row.querySelector('input');
                if (!Number.isFinite(grams(p.value))) erreur = 'Volume de formule invalide.';
                if (!s.value && grams(p.value) > 0) erreur = 'Choisissez la formule correspondant au volume saisi.';
                if (s.value && formulesVues.has(s.value)) erreur = 'Choisissez des formules distinctes.';
                if (s.value) formulesVues.add(s.value);
                const maximum=s.selectedOptions[0] && s.selectedOptions[0].dataset.volume;
                if (maximum && grams(p.value) > grams(maximum)) erreur = 'Le volume dépasse la formule sélectionnée.';
            });
            const reserves = {}; conteneurs.forEach(tc => tc.lots.forEach(l => { reserves[l.id] = (reserves[l.id] || 0) + (grams(l.qteExporter) || 0); }));
            lots.forEach(l => { if ((reserves[l.id] || 0) > (grams(l.pn) || 0) + (ancienneReservation[l.id] || 0)) erreur = 'Le poids demandé dépasse le stock du lot ' + l.numero + '.'; });
            if (erreur) { e.preventDefault(); el('export-erreur').textContent = erreur; el('export-erreur').scrollIntoView({block:'center'}); }
            else { el('export-erreur').textContent = ''; el('export-enregistrer').disabled = true; }
        });
        form.addEventListener('invalid', event => { const details=event.target.closest('details'); if (details) details.open=true; }, true);
        afficherTc(); if (initial.length) initial.forEach(ajouterFormule); recalculer(); documents();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

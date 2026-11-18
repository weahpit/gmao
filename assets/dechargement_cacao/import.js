/* ==========================================================================
 * Import de dechargement CACAO  (version 2)
 *
 *  1. "Verifier la structure" : le serveur lit le fichier et renvoie ce qu'il
 *     voit (ligne d'en-tete, colonne -> champ, apercu). Rien n'est enregistre.
 *  2. "Analyser et comparer"   : rapport complet compare a la base.
 *  3. "Enregistrer"            : ecriture uniquement sur validation.
 *
 * Aucune dependance externe.
 * ========================================================================== */
(function () {
    'use strict';

    var config = {};
    try {
        config = JSON.parse(document.getElementById('dcConfig').textContent || '{}');
    } catch (e) {
        config = {};
    }

    var $ = function (id) { return document.getElementById(id); };
    var el = {
        drop: $('dcDrop'),
        input: $('dcInput'),
        dropIdle: $('dcDropIdle'),
        dropFile: $('dcDropFile'),
        fileName: $('dcFileName'),
        fileMeta: $('dcFileMeta'),
        retirer: $('dcRetirer'),
        analyser: $('dcAnalyser'),
        structure: $('dcVerifier'),
        uploadErr: $('dcUploadErr'),
        uploadErrMsg: $('dcUploadErrMsg'),
        cardUpload: $('dcCardUpload'),
        cardDiag: $('dcCardDiagnostic'),
        diagMeta: $('dcDiagMeta'),
        diagBody: $('dcDiagBody'),
        diagAlerte: $('dcDiagAlerte'),
        diagAlerteMsg: $('dcDiagAlerteMsg'),
        diagEntete: $('dcDiagEntete'),
        diagCorrespondance: $('dcDiagCorrespondance'),
        diagApercu: $('dcDiagApercu'),
        cardRapport: $('dcCardRapport'),
        cardResultat: $('dcCardResultat'),
        rapportMeta: $('dcRapportMeta'),
        stats: $('dcStats'),
        filters: $('dcFilters'),
        tbody: $('dcTbody'),
        table: $('dcTable'),
        checkAll: $('dcCheckAll'),
        recherche: $('dcRecherche'),
        tout: $('dcToutSelectionner'),
        rien: $('dcRienSelectionner'),
        resumeSelection: $('dcResumeSelection'),
        resumeDetail: $('dcResumeDetail'),
        annuler: $('dcAnnuler'),
        enregistrer: $('dcEnregistrer'),
        overlay: $('dcOverlay'),
        overlayTitle: $('dcOverlayTitle'),
        overlayMsg: $('dcOverlayMsg'),
        toast: $('dcToast'),
        steps: $('dcSteps'),
        resultTitle: $('dcResultTitle'),
        resultMsg: $('dcResultMsg'),
        nouvelImport: $('dcNouvelImport')
    };

    var etat = {
        fichier: null,
        structure: null,
        rapport: null,
        empreinte: null,
        jeton: null,
        filtre: 'tous',
        recherche: '',
        selection: new Set(),
        occupe: false,
        enteteForce: 0,
        renommages: {}   // { lettre: nouveau libelle }
    };

    var totalAffiche = 0;

    var LIBELLES_VERDICT = {
        nouveau: 'Nouveau',
        modifie: 'À mettre à jour',
        existant: 'Déjà en base',
        rejete: 'À contrôler',
        invalide: 'À contrôler',
        doublon_fichier: 'Doublon fichier',
        conflit_fichier: 'Conflit fichier'
    };

    /* ------------------------------------------------------------------
     * Verrou d'interface / messages
     * ------------------------------------------------------------------ */
    function verrouiller(titre, message) {
        etat.occupe = true;
        el.overlayTitle.textContent = titre || 'Traitement en cours…';
        el.overlayMsg.textContent = message || 'Merci de patienter.';
        el.overlay.classList.add('show');
        el.overlay.style.display = 'flex';
        document.body.classList.add('dc-busy');
        el.overlay.focus();
    }

    function debloquer() {
        etat.occupe = false;
        el.overlay.classList.remove('show');
        el.overlay.style.display = 'none';
        document.body.classList.remove('dc-busy');
    }

    function toast(message, type) {
        el.toast.className = 'dc-toast show ' + (type === 'ok' ? 'ok' : 'err');
        el.toast.textContent = message;
        clearTimeout(toast._t);
        toast._t = setTimeout(function () { el.toast.className = 'dc-toast'; }, 7000);
    }

    function etape(n) {
        if (!el.steps) { return; }
        Array.prototype.forEach.call(el.steps.querySelectorAll('.dc-step'), function (s) {
            var num = parseInt(s.getAttribute('data-step'), 10);
            s.classList.toggle('active', num === n);
            s.classList.toggle('done', num < n);
        });
    }

    /* ------------------------------------------------------------------
     * Formatage
     * ------------------------------------------------------------------ */
    function nombre(v, decimales) {
        if (v === null || v === undefined || v === '') { return '-'; }
        if (typeof v === 'number') {
            var d = typeof decimales === 'number' ? decimales : (v % 1 === 0 ? 0 : 2);
            return v.toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });
        }
        var n = Number(v);
        if (isNaN(n)) { return String(v); }
        return nombre(n, decimales);
    }

    function taille(octets) {
        if (octets < 1024) { return octets + ' o'; }
        if (octets < 1048576) { return (octets / 1024).toFixed(0) + ' Ko'; }
        return (octets / 1048576).toFixed(2) + ' Mo';
    }

    function echapper(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function dateLisible(v) {
        if (!v) { return '-'; }
        var p = String(v).split(' ');
        var d = p[0].split('-');
        if (d.length !== 3) { return v; }
        return d[2] + '/' + d[1] + '/' + d[0] + (p[1] ? ' ' + p[1].slice(0, 5) : '');
    }

    function icone(nom) {
        var d = {
            ok: '<path d="M20 6 9 17l-5-5"/>',
            warn: '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            err: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
        };
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + (d[nom] || d.err) + '</svg>';
    }

    /* ------------------------------------------------------------------
     * Selection du fichier
     * ------------------------------------------------------------------ */
    function fichierValide(f) {
        if (!f) { return 'Aucun fichier sélectionné.'; }
        var ext = (f.name.split('.').pop() || '').toLowerCase();
        if (['xlsx', 'xls', 'csv', 'txt'].indexOf(ext) === -1) {
            return 'Format non accepté (.' + ext + '). Utilisez un fichier .xlsx, .xls ou .csv.';
        }
        if (config.tailleMax && f.size > config.tailleMax) {
            return 'Le fichier dépasse ' + taille(config.tailleMax) + ' (fichier : ' + taille(f.size) + ').';
        }
        if (f.size === 0) { return 'Le fichier est vide.'; }
        return null;
    }

    function choisirFichier(f) {
        var erreur = fichierValide(f);
        if (erreur) {
            afficherErreur(erreur);
            return;
        }
        masquerErreur();
        etat.fichier = f;
        etat.structure = null;
        etat.enteteForce = 0;
        etat.renommages = {};
        el.drop.classList.add('has-file');
        el.dropIdle.style.display = 'none';
        el.dropFile.style.display = 'flex';
        el.fileName.textContent = f.name;
        el.fileMeta.textContent = taille(f.size) + ' - ' + (f.name.split('.').pop() || '').toUpperCase() + ' · prêt pour la vérification';
        el.analyser.disabled = false;
        if (el.structure) { el.structure.disabled = false; }
        if (el.cardDiag) { el.cardDiag.style.display = 'none'; }
        if (el.cardRapport) { el.cardRapport.style.display = 'none'; }
    }

    function retirerFichier() {
        etat.fichier = null;
        etat.structure = null;
        etat.enteteForce = 0;
        etat.renommages = {};
        el.input.value = '';
        el.drop.classList.remove('has-file');
        el.dropIdle.style.display = '';
        el.dropFile.style.display = 'none';
        el.analyser.disabled = true;
        if (el.structure) { el.structure.disabled = true; }
        if (el.cardDiag) { el.cardDiag.style.display = 'none'; }
        masquerErreur();
    }

    function afficherErreur(msg) {
        el.uploadErrMsg.textContent = msg;
        el.uploadErr.style.display = 'flex';
        if (el.uploadErr.scrollIntoView) {
            el.uploadErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function masquerErreur() {
        el.uploadErr.style.display = 'none';
    }

    if (el.input) {
        el.input.addEventListener('change', function () {
            if (etat.occupe) { return; }
            if (el.input.files && el.input.files[0]) { choisirFichier(el.input.files[0]); }
        });
    }

    ['dragenter', 'dragover'].forEach(function (ev) {
        el.drop.addEventListener(ev, function (e) {
            e.preventDefault(); e.stopPropagation();
            if (!etat.occupe) { el.drop.classList.add('drag'); }
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        el.drop.addEventListener(ev, function (e) {
            e.preventDefault(); e.stopPropagation();
            el.drop.classList.remove('drag');
        });
    });
    el.drop.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (etat.occupe) { return; }
        var dt = e.dataTransfer;
        if (dt && dt.files && dt.files[0]) { choisirFichier(dt.files[0]); }
    });
    el.retirer.addEventListener('click', function (e) {
        e.stopPropagation();
        if (!etat.occupe) { retirerFichier(); }
    });

    /* ------------------------------------------------------------------
     * Appels serveur
     * ------------------------------------------------------------------ */
    function poster(url, donnees) {
        return fetch(url, {
            method: 'POST',
            body: donnees,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (reponse) {
            return reponse.text().then(function (texte) {
                var json = null;
                try { json = JSON.parse(texte); } catch (e) { json = null; }
                if (!json) {
                    throw new Error('Réponse inattendue du serveur (code ' + reponse.status + '). Consultez var/log/.');
                }
                if (!reponse.ok || json.code === 'error') {
                    throw new Error(json.msg || ('Erreur serveur (code ' + reponse.status + ').'));
                }
                return json;
            });
        });
    }

    /** Construit le corps de la requete : fichier (1re fois) + corrections demandees. */
    function corpsRequete(avecFichier) {
        var corps = new FormData();
        corps.append('_token', config.token);
        if (avecFichier && etat.fichier) {
            corps.append('fichier', etat.fichier, etat.fichier.name);
        }
        if (etat.enteteForce > 0) {
            corps.append('entete', String(etat.enteteForce));
        }
        Object.keys(etat.renommages).forEach(function (lettre) {
            corps.append('colonnes[' + lettre + ']', etat.renommages[lettre]);
        });
        return corps;
    }

    /* ------------------------------------------------------------------
     * Etape 1 : verification de la structure (lecture seule)
     * ------------------------------------------------------------------ */
    function verifierStructure() {
        if (etat.occupe || !etat.fichier) { return; }
        var erreur = fichierValide(etat.fichier);
        if (erreur) { afficherErreur(erreur); return; }

        masquerErreur();
        verrouiller('Lecture du fichier…', 'Le serveur lit la ligne d\'en-tête et les premières lignes.');
        etape(1);

        poster(config.structure, corpsRequete(true)).then(function (json) {
            debloquer();
            etat.structure = json.structure;
            etat.enteteForce = 0;
            etat.renommages = {};
            afficherStructure(json.structure);
            etape(2);
            el.cardDiag.style.display = '';
            el.cardDiag.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (json.structure.colonnesAbsentes.length) {
                toast('Colonnes obligatoires non reconnues : ' + json.structure.colonnesAbsentes.join(', '), 'err');
            } else {
                toast('Structure lue : en-tête ligne ' + json.structure.ligneEntete + ', '
                    + json.structure.correspondance.length + ' colonne(s) reconnue(s).', 'ok');
            }
        }).catch(function (e) {
            debloquer();
            etape(1);
            afficherErreur(e.message);
            toast(e.message, 'err');
        });
    }

    function afficherStructure(s) {
        el.diagMeta.textContent = s.format + ' · feuille « ' + (s.feuille || '-') + ' » · '
            + nombre(s.nbLignes) + ' ligne(s) de données · en-tête ligne ' + s.ligneEntete
            + (s.nbFeuilles > 1 ? ' · ' + s.nbFeuilles + ' feuilles' : '');

        // Alerte : colonnes obligatoires manquantes
        if (s.colonnesAbsentes && s.colonnesAbsentes.length) {
            el.diagAlerte.className = 'dc-alert dc-alert-error';
            el.diagAlerteMsg.innerHTML = '<strong>Colonne(s) obligatoire(s) non reconnue(s) : ' + echapper(s.colonnesAbsentes.join(', '))
                + '.</strong><br>Le fichier ne sera pas importé en l\'état. Vérifiez la ligne d\'en-tête'
                + ' ou renommez la colonne ci-dessous.';
            el.diagAlerte.style.display = 'flex';
        } else {
            el.diagAlerte.className = 'dc-alert dc-alert-ok';
            el.diagAlerteMsg.innerHTML = 'Les colonnes obligatoires (<strong>Code du bon</strong>, <strong>Date</strong>, <strong>PoidsNet</strong>,'
                + ' <strong>Date de déchargement</strong>) sont bien reconnues. Vous pouvez analyser le fichier.';
            el.diagAlerte.style.display = 'flex';
        }

        // Table detaillee : chaque colonne du fichier + champ associe (renommable)
        var champs = s.champs || {};
        var lignes = s.apercu || [];

        // Tableau 1 : correspondance champ -> colonne
        var htmlCorr = '';
        var obligatoires = { 'Code du bon': 1, 'Date': 1, 'PoidsNet': 1, 'Date de déchargement': 1 };
        Object.keys(champs).forEach(function (cle) {
            var libelle = champs[cle];
            var trouve = null;
            (s.correspondance || []).forEach(function (c) {
                if (c.champ === libelle) { trouve = c; }
            });
            var estObligatoire = !!obligatoires[libelle];
            var etatCls = trouve ? 'ok' : (estObligatoire ? 'manquant' : 'facultatif');
            var etatTexte = trouve ? 'Colonne ' + trouve.colonne : (estObligatoire ? 'ABSENT' : 'non trouvé');

            htmlCorr += '<tr class="' + etatCls + '">'
                + '<td class="dc-diag-champ">' + echapper(libelle)
                + (estObligatoire ? ' <span class="dc-diag-star">*</span>' : '') + '</td>'
                + '<td>' + (trouve ? '<span class="dc-diag-pill">' + echapper(trouve.colonne) + '</span> ' + echapper(trouve.libelle) : '<span class="dc-diag-vide">-</span>') + '</td>'
                + '<td class="dc-diag-etat">' + (trouve ? icone('ok') : icone(estObligatoire ? 'err' : 'warn')) + ' ' + etatTexte + '</td>'
                + '</tr>';
        });
        el.diagCorrespondance.innerHTML = htmlCorr;

        // Tableau 2 : apercu brut + renommage par lettre de colonne
        var htmlApercu = '';
        if (lignes.length) {
            // Ligne d'en-tete : renommage possible de chaque colonne
            var entete = lignes[0];
            htmlApercu += '<tr class="dc-diag-entete"><th>Renommer</th>';
            entete.valeurs.forEach(function (v) {
                htmlApercu += '<th><input type="text" class="dc-diag-input" data-lettre="' + echapper(v.colonne) + '" '
                    + 'value="' + echapper(v.valeur) + '" aria-label="Renommer la colonne ' + echapper(v.colonne) + '"></th>';
            });
            htmlApercu += '</tr>';
            htmlApercu += '<tr class="dc-diag-lettres"><th>Colonne</th>';
            entete.valeurs.forEach(function (v) {
                htmlApercu += '<th>' + echapper(v.colonne) + '</th>';
            });
            htmlApercu += '</tr>';
            for (var i = 1; i < lignes.length; i++) {
                htmlApercu += '<tr><td class="dc-diag-num">Ligne ' + (lignes[i].rangee) + '</td>';
                lignes[i].valeurs.forEach(function (v) {
                    htmlApercu += '<td>' + (v.valeur === '' ? '<span class="dc-diag-vide">-</span>' : echapper(v.valeur)) + '</td>';
                });
                htmlApercu += '</tr>';
            }
        } else {
            htmlApercu = '<tr><td colspan="2"><div class="dc-empty">Aucun aperçu disponible.</div></td></tr>';
        }
        el.diagApercu.innerHTML = htmlApercu;

        // Renommages saisis -> memorises
        el.diagApercu.querySelectorAll('.dc-diag-input').forEach(function (input) {
            input.addEventListener('change', function () {
                var lettre = input.getAttribute('data-lettre');
                var valeur = input.value.trim();
                if (!valeur) { delete etat.renommages[lettre]; return; }
                etat.renommages[lettre] = valeur;
            });
        });

        // Choix de la ligne d'en-tete : on propose les 12 premieres lignes vues
        if (el.diagEntete) {
            var options = ['<option value="0">Detectee automatiquement (ligne ' + s.ligneEntete + ')</option>'];
            (s.apercu || []).forEach(function (r) {
                options.push('<option value="' + r.rangee + '">Ligne ' + r.rangee + '</option>');
            });
            el.diagEntete.innerHTML = options.join('');
            el.diagEntete.value = '0';
        }
    }

    /* ------------------------------------------------------------------
     * Etape 2 : analyse complete
     * ------------------------------------------------------------------ */
    function analyser() {
        if (etat.occupe || !etat.fichier) { return; }
        var erreur = fichierValide(etat.fichier);
        if (erreur) { afficherErreur(erreur); return; }

        masquerErreur();
        verrouiller('Analyse du fichier…', 'Lecture et comparaison avec la base. Merci de patienter.');
        etape(3);

        poster(config.analyser, corpsRequete(true)).then(function (json) {
            debloquer();
            etat.rapport = json.rapport;
            etat.empreinte = json.empreinte;
            etat.jeton = json.jeton;
            construireRapport(json.rapport);
            el.cardRapport.style.display = '';
            el.cardRapport.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (json.code === 'warning') {
                toast(json.msg || 'Colonnes obligatoires non reconnues.', 'err');
                return;
            }
            var c = json.rapport.compteurs;
            if (c.total === 0) {
                toast('Le fichier ne contient aucune ligne exploitable.', 'err');
            } else if (c.enregistrables === 0 && c.rejete + c.invalide === 0) {
                toast('Aucune ligne nouvelle ou à mettre à jour : tout est déjà conforme.', 'ok');
            } else if (c.nouveau + c.modifie === 0) {
                toast('Toutes les lignes sont à contrôler : ouvrez la liste des anomalies.', 'err');
            }
        }).catch(function (e) {
            debloquer();
            etape(1);
            afficherErreur(e.message);
            toast(e.message, 'err');
        });
    }

    /* ------------------------------------------------------------------
     * Construction du rapport
     * ------------------------------------------------------------------ */
    function construireRapport(rapport) {
        var c = rapport.compteurs;
        var f = rapport.fichier || {};
        el.rapportMeta.textContent = (f.format || '') + ' · feuille « ' + (f.feuille || '-') + ' » · en-tête ligne ' + (f.ligneEntete || 1)
            + ' · ' + nombre(c.total) + ' ligne(s) lue(s)'
            + ((f.colonnesIgnorees && f.colonnesIgnorees.length) ? ' · colonnes ignorées : ' + f.colonnesIgnorees.join(', ') : '');

        var cartes = [
            { cls: 'total', lbl: 'Lignes lues', val: nombre(c.total), sub: 'dans le fichier' },
            { cls: 'nouveau', lbl: 'Nouveaux', val: nombre(c.nouveau), sub: nombre(c.poids_nouveau) + ' kg' },
            { cls: 'modifie', lbl: 'À mettre à jour', val: nombre(c.modifie), sub: nombre(c.poids_modifie) + ' kg' },
            { cls: 'existant', lbl: 'Déjà conformes', val: nombre(c.existant), sub: nombre(c.poids_existant) + ' kg' },
            { cls: 'rejete', lbl: 'À contrôler', val: nombre(c.rejete + c.invalide + c.conflit_fichier), sub: 'non enregistrees' },
            { cls: '', lbl: 'Écarts de poids', val: nombre(c.ecarts_base), sub: nombre(Math.abs(c.poids_ecart)) + ' kg d\'écart cumulé' }
        ];
        el.stats.innerHTML = cartes.map(function (k) {
            return '<div class="dc-stat ' + k.cls + '"><div class="lbl">' + k.lbl + '</div><div class="val">' + k.val + '</div><div class="sub">' + k.sub + '</div></div>';
        }).join('');

        var filtres = [
            { v: 'tous', l: 'Tous', n: c.total },
            { v: 'nouveau', l: 'Nouveaux', n: c.nouveau },
            { v: 'modifie', l: 'À mettre à jour', n: c.modifie },
            { v: 'existant', l: 'Déjà en base', n: c.existant },
            { v: 'rejete', l: 'À contrôler', n: c.rejete + c.invalide + c.conflit_fichier }
        ];
        el.filters.innerHTML = filtres.map(function (fd) {
            return '<button type="button" class="dc-filter' + (fd.v === 'tous' ? ' on' : '') + '" data-verdict="' + fd.v + '">'
                + '<span class="dot"></span>' + fd.l + '<span class="n">' + nombre(fd.n) + '</span></button>';
        }).join('');

        etat.selection = new Set();
        rapport.lignes.forEach(function (ligne) {
            if (ligne.verdict === 'nouveau' || ligne.verdict === 'modifie') {
                etat.selection.add(ligne.code);
            }
        });

        etat.filtre = 'tous';
        etat.recherche = '';
        el.recherche.value = '';
        rendreLignes();
        majSelection();
    }

    function verdictNormalise(v) {
        if (v === 'invalide' || v === 'conflit_fichier') { return 'rejete'; }
        return v;
    }

    function rendreLignes() {
        if (!etat.rapport) { return; }
        var q = etat.recherche.toLowerCase();
        var html = [];
        totalAffiche = 0;

        etat.rapport.lignes.forEach(function (l) {
            var v = verdictNormalise(l.verdict);
            if (etat.filtre !== 'tous' && v !== etat.filtre) { return; }
            if (q) {
                var sac = [l.code, l.cooperative, l.immat, l.transporteur, l.traitant, l.categorie, l.site].join(' ').toLowerCase();
                if (sac.indexOf(q) === -1) { return; }
            }

            var selectionnable = (l.verdict === 'nouveau' || l.verdict === 'modifie');
            var coche = selectionnable && etat.selection.has(l.code);

            var controles = [];
            (l.anomalies || []).forEach(function (a) { controles.push('<span class="dc-note">' + echapper(a) + '</span>'); });
            (l.modifications || []).forEach(function (m) { controles.push('<span class="dc-note muted">' + echapper(m) + '</span>'); });
            if (!controles.length) {
                controles.push('<span style="font-size:.76rem;color:#a89b8c;">' + echapper(l.statut || '-') + '</span>');
            }

            var ecart = '';
            if (l.ecart !== null && l.ecart !== undefined && Math.abs(l.ecart) > 0.004) {
                ecart = '<span class="dc-ecart ' + (l.ecart > 0 ? 'pos' : 'neg') + '">' + (l.ecart > 0 ? '+' : '') + nombre(l.ecart) + '</span>';
            } else if (l.ecart !== null && l.ecart !== undefined) {
                ecart = '<span class="dc-ecart" style="color:#a89b8c;">0</span>';
            }

            totalAffiche++;
            html.push('<tr data-code="' + echapper(l.code) + '" data-verdict="' + v + '"' + (coche ? '' : ' class="off"') + '>'
                + '<td class="check"><input type="checkbox" class="dc-check dc-row-check" data-code="' + echapper(l.code) + '"'
                + (selectionnable ? '' : ' disabled') + (coche ? ' checked' : '') + ' aria-label="Selectionner ' + echapper(l.code) + '"></td>'
                + '<td data-label="Ligne" class="num" style="text-align:left;color:#a89b8c;">' + l.ligne + '</td>'
                + '<td data-label="Code" class="code">' + echapper(l.code || '-') + '</td>'
                + '<td data-label="Date">' + dateLisible(l.date) + '</td>'
                + '<td data-label="Cooperative"><span class="trunc" title="' + echapper(l.cooperative) + '">' + echapper(l.cooperative || '-') + '</span></td>'
                + '<td data-label="Immat">' + echapper(l.immat || '-') + '</td>'
                + '<td data-label="Transporteur"><span class="trunc" title="' + echapper(l.transporteur) + '">' + echapper(l.transporteur || '-') + '</span></td>'
                + '<td data-label="Sacs" class="num">' + nombre(l.nbre_sacs) + '</td>'
                + '<td data-label="PoidsNet" class="num">' + nombre(l.poids_net) + '</td>'
                + '<td data-label="Ecart" class="num">' + ecart + '</td>'
                + '<td data-label="Controles">' + controles.join('') + '</td>'
                + '<td data-label="Verdict"><span class="dc-badge ' + l.verdict + '"><span class="dot"></span>' + (LIBELLES_VERDICT[l.verdict] || l.verdict) + '</span></td>'
                + '</tr>');
        });

        if (!html.length) {
            html.push('<tr><td colspan="12"><div class="dc-empty"><svg xmlns="http://www.w3.org/2000/svg" width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><div>Aucune ligne ne correspond à ce filtre.</div></div></td></tr>');
        }

        el.tbody.innerHTML = html.join('');
        el.tbody.querySelectorAll('.dc-row-check').forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (etat.occupe) { return; }
                var code = cb.getAttribute('data-code');
                if (cb.checked) { etat.selection.add(code); } else { etat.selection.delete(code); }
                var tr = cb.closest('tr');
                if (tr) { tr.classList.toggle('off', !cb.checked); }
                majSelection();
            });
        });
        majCheckAll();
    }

    function majCheckAll() {
        if (!el.checkAll) { return; }
        var visibles = Array.prototype.slice.call(el.tbody.querySelectorAll('.dc-row-check:not(:disabled)'));
        var coches = visibles.filter(function (c) { return c.checked; });
        el.checkAll.checked = visibles.length > 0 && coches.length === visibles.length;
        el.checkAll.indeterminate = coches.length > 0 && coches.length < visibles.length;
    }

    function majSelection() {
        if (!etat.rapport) { return; }
        var n = 0, sacs = 0, poids = 0, maj = 0;
        etat.rapport.lignes.forEach(function (l) {
            if (!etat.selection.has(l.code)) { return; }
            n++;
            sacs += (l.nbre_sacs || 0);
            poids += (l.poids_net || 0);
            if (l.verdict === 'modifie') { maj++; }
        });

        el.resumeSelection.textContent = n === 0
            ? 'Aucune ligne sélectionnée'
            : n + ' ligne' + (n > 1 ? 's' : '') + ' sélectionnée' + (n > 1 ? 's' : '');
        el.resumeDetail.innerHTML = n === 0
            ? '<span style="color:#b3402f;">Cochez au moins une ligne pour enregistrer.</span>'
            : nombre(n - maj) + ' création(s) · ' + nombre(maj) + ' mise(s) à jour · '
              + nombre(sacs) + ' sacs · ' + nombre(poids) + ' kg';
        el.enregistrer.disabled = n === 0 || etat.occupe;
    }

    /* ------------------------------------------------------------------
     * Interactions rapport
     * ------------------------------------------------------------------ */
    el.filters.addEventListener('click', function (e) {
        var b = e.target.closest('.dc-filter');
        if (!b || etat.occupe) { return; }
        etat.filtre = b.getAttribute('data-verdict');
        Array.prototype.forEach.call(el.filters.querySelectorAll('.dc-filter'), function (x) { x.classList.remove('on'); });
        b.classList.add('on');
        rendreLignes();
    });

    var rechercheTimer = null;
    el.recherche.addEventListener('input', function () {
        clearTimeout(rechercheTimer);
        var valeur = el.recherche.value;
        rechercheTimer = setTimeout(function () {
            etat.recherche = valeur.trim();
            rendreLignes();
        }, 180);
    });

    el.checkAll.addEventListener('change', function () {
        if (etat.occupe) { return; }
        var cocher = el.checkAll.checked;
        el.tbody.querySelectorAll('.dc-row-check:not(:disabled)').forEach(function (cb) {
            cb.checked = cocher;
            var code = cb.getAttribute('data-code');
            if (cocher) { etat.selection.add(code); } else { etat.selection.delete(code); }
            var tr = cb.closest('tr');
            if (tr) { tr.classList.toggle('off', !cocher); }
        });
        majSelection();
    });

    el.tout.addEventListener('click', function () {
        if (etat.occupe || !etat.rapport) { return; }
        etat.rapport.lignes.forEach(function (l) {
            if (l.verdict === 'nouveau' || l.verdict === 'modifie') { etat.selection.add(l.code); }
        });
        rendreLignes();
        majSelection();
    });

    el.rien.addEventListener('click', function () {
        if (etat.occupe) { return; }
        etat.selection.clear();
        rendreLignes();
        majSelection();
    });

    el.annuler.addEventListener('click', function () {
        if (etat.occupe) { return; }
        var ok = window.confirm('Annuler l\'import ?\n\nAucune donnée ne sera enregistrée en base et le rapport sera effacé.');
        if (!ok) { return; }
        etat.rapport = null;
        etat.selection.clear();
        el.cardRapport.style.display = 'none';
        el.cardResultat.style.display = 'none';
        retirerFichier();
        etape(1);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        toast('Import annulé : aucune donnée écrite en base.', 'ok');
    });

    /* ------------------------------------------------------------------
     * Enregistrement (apres validation explicite)
     * ------------------------------------------------------------------ */
    el.enregistrer.addEventListener('click', function () {
        if (etat.occupe || !etat.rapport) { return; }

        var lignes = etat.rapport.lignes.filter(function (l) {
            return etat.selection.has(l.code) && (l.verdict === 'nouveau' || l.verdict === 'modifie');
        });
        if (!lignes.length) {
            toast('Aucune ligne sélectionnée à enregistrer.', 'err');
            return;
        }

        var creer = lignes.filter(function (l) { return l.verdict === 'nouveau'; }).length;
        var maj = lignes.length - creer;
        var ok = window.confirm(
            'Confirmer l\'enregistrement en base ?\n\n'
            + '- ' + creer + ' nouveau(x) bon(s) de déchargement\n'
            + '- ' + maj + ' bon(s) à mettre à jour\n\n'
            + 'Cette opération est définitive. Cliquez sur OK pour confirmer, ou sur Annuler pour revenir au rapport.'
        );
        if (!ok) { return; }

        var corps = new FormData();
        corps.append('_token', config.token);
        corps.append('jeton', etat.jeton);
        corps.append('empreinte', etat.empreinte);
        lignes.forEach(function (l, i) {
            Object.keys(l).forEach(function (cle) {
                var valeur = l[cle];
                if (valeur === null || valeur === undefined) { return; }
                if (typeof valeur === 'object') { return; }
                corps.append('lignes[' + i + '][' + cle + ']', valeur);
            });
            corps.append('lignes[' + i + '][action]', 'enregistrer');
        });

        verrouiller('Enregistrement en base…', 'Écriture de ' + lignes.length + ' ligne(s). Ne fermez pas la page.');
        etape(4);

        poster(config.enregistrer, corps).then(function (json) {
            debloquer();
            el.cardRapport.style.display = 'none';
            el.cardResultat.style.display = '';
            var r = json.resultat || {};
            el.resultTitle.textContent = 'Import terminé';
            el.resultMsg.textContent = (r.creees || 0) + ' bon(s) créé(s) · ' + (r.mises_a_jour || 0) + ' mis à jour · '
                + (r.ignorees || 0) + ' ignoré(s).';
            el.cardResultat.scrollIntoView({ behavior: 'smooth', block: 'center' });
            toast(json.msg || 'Enregistrement effectué.', 'ok');
            etat.rapport = null;
            etat.selection.clear();
            retirerFichier();
        }).catch(function (e) {
            debloquer();
            etape(3);
            toast(e.message, 'err');
            majSelection();
        });
    });

    el.nouvelImport.addEventListener('click', function () {
        el.cardResultat.style.display = 'none';
        el.cardRapport.style.display = 'none';
        if (el.cardDiag) { el.cardDiag.style.display = 'none'; }
        retirerFichier();
        etape(1);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    /* ------------------------------------------------------------------
     * Boutons principaux
     * ------------------------------------------------------------------ */
    if (el.structure) {
        el.structure.addEventListener('click', verifierStructure);
    }
    el.analyser.addEventListener('click', analyser);

    var diagAnalyser = document.getElementById('dcDiagAnalyser');
    if (diagAnalyser) {
        diagAnalyser.addEventListener('click', analyser);
    }

    if (el.diagEntete) {
        el.diagEntete.addEventListener('change', function () {
            var n = parseInt(el.diagEntete.value, 10);
            etat.enteteForce = isNaN(n) || n < 1 ? 0 : n;
            if (etat.fichier) {
                verifierStructure();
            }
        });
    }

    /* ------------------------------------------------------------------
     * Garde-fous
     * ------------------------------------------------------------------ */
    window.addEventListener('beforeunload', function (e) {
        if (etat.occupe) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    ['click', 'submit', 'keydown'].forEach(function (ev) {
        document.addEventListener(ev, function (e) {
            if (!etat.occupe) { return; }
            if (el.overlay.contains(e.target)) { return; }
            e.preventDefault();
            e.stopPropagation();
        }, true);
    });
})();

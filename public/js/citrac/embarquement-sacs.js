/* =====================================================================
 *  CITRAC GMAO - Nouvel embarquement : sacs et disponibilite des lots
 * ---------------------------------------------------------------------
 *  Complement de operations-logistiques.js (qui n'est pas modifie).
 *
 *  1. Renomme le libelle "Nombre de colis" en "Nombre de sacs" partout
 *     dans le bloc des conteneurs (tableau, entetes, infobulles).
 *  2. A la selection d'un lot, reporte automatiquement son nombre de sacs
 *     dans le champ "Nombre de sacs" de la ligne.
 *  3. Un lot deja charge dans un conteneur n'est plus propose dans les
 *     autres conteneurs (option masquee et desactivee, suffixe "deja charge").
 *
 *  Le script s'appuie uniquement sur le DOM et sur la liste des lots
 *  fournie par le serveur (textarea #export-lots) : il ne remplace aucune
 *  fonction existante et peut etre retire en supprimant la balise <script>.
 * ===================================================================== */
(function () {
    'use strict';

    var zone = document.getElementById('export-tcs');
    var source = document.getElementById('export-lots');
    if (!zone || !source) {
        return;
    }

    var lots = {};
    try {
        var donnees = JSON.parse(source.value || '[]');
        if (Array.isArray(donnees)) {
            donnees.forEach(function (lot) {
                if (lot && lot.id) {
                    lots[String(lot.id).toLowerCase()] = lot;
                }
            });
        }
    } catch (erreur) {
        return;
    }

    if (Object.keys(lots).length === 0) {
        return;
    }

    var LIBELLES = [
        [/\bNombre\s+de\s+colis\b/gi, 'Nombre de sacs'],
        [/\bNbre\s+de\s+colis\b/gi, 'Nbre de sacs'],
        [/\bNb\.?\s*colis\b/gi, 'Nb sacs']
    ];

    /* ------------------------------------------------------------------
     *  Utilitaires
     * ------------------------------------------------------------------ */
    function estLot(valeur) {
        return !!valeur && Object.prototype.hasOwnProperty.call(lots, String(valeur).toLowerCase());
    }

    function selectsLots() {
        return Array.prototype.filter.call(zone.querySelectorAll('select'), function (select) {
            return Array.prototype.some.call(select.options, function (option) {
                return estLot(option.value);
            });
        });
    }

    /** Bloc (ligne de lot) qui contient la liste deroulante. */
    function bloc(select) {
        var noeud = select.parentElement;
        var niveau = 0;
        while (noeud && noeud !== zone && niveau < 6) {
            // Le premier ancetre qui porte d'autres champs que la liste
            // deroulante est la ligne de lot (sacs, poids, bouton retirer).
            if (noeud.querySelectorAll('input, select, textarea').length > 1) {
                return noeud;
            }
            noeud = noeud.parentElement;
            niveau += 1;
        }

        return select.parentElement;
    }

    /** Champ "nombre de sacs" de la ligne. */
    function champSacs(select) {
        var conteneur = bloc(select);
        if (!conteneur) {
            return null;
        }

        var champs = Array.prototype.filter.call(conteneur.querySelectorAll('input'), function (champ) {
            if (champ.type === 'hidden' || champ.type === 'file' || champ === select) {
                return false;
            }
            var identite = (champ.name || '') + ' ' + (champ.id || '') + ' ' + (champ.getAttribute('data-champ') || '');
            return /sac|colis|carton/i.test(identite);
        });

        if (champs.length > 0) {
            return champs[0];
        }

        // Repli : champ numerique place sous un libelle qui parle de sacs.
        var numeriques = Array.prototype.filter.call(conteneur.querySelectorAll('input'), function (champ) {
            return champ.type === 'number' || champ.inputMode === 'numeric';
        });

        for (var i = 0; i < numeriques.length; i += 1) {
            var parent = numeriques[i].parentElement;
            var texte = parent ? (parent.textContent || '') : '';
            if (/sac|colis|carton/i.test(texte)) {
                return numeriques[i];
            }
        }

        return null;
    }

    function prevenir(champ, valeur) {
        champ.value = String(valeur);
        champ.dispatchEvent(new Event('input', { bubbles: true }));
        champ.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /* ------------------------------------------------------------------
     *  1. Libelle "Nombre de sacs"
     * ------------------------------------------------------------------ */
    function renommerLibelles(racine) {
        var iterateur = document.createTreeWalker(racine, NodeFilter.SHOW_TEXT, {
            acceptNode: function (noeud) {
                var parent = noeud.parentElement;
                if (!parent) {
                    return NodeFilter.FILTER_REJECT;
                }
                if (/^(SCRIPT|STYLE|TEXTAREA|OPTION)$/.test(parent.tagName)) {
                    return NodeFilter.FILTER_REJECT;
                }
                return /colis/i.test(noeud.nodeValue || '') ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            }
        });

        var noeuds = [];
        while (iterateur.nextNode()) {
            noeuds.push(iterateur.currentNode);
        }

        noeuds.forEach(function (noeud) {
            var valeur = noeud.nodeValue;
            LIBELLES.forEach(function (regle) {
                valeur = valeur.replace(regle[0], regle[1]);
            });
            if (valeur !== noeud.nodeValue) {
                noeud.nodeValue = valeur;
            }
        });
    }

    /* ------------------------------------------------------------------
     *  2. Report automatique du nombre de sacs du lot
     * ------------------------------------------------------------------ */
    function remplirSacs(select) {
        var cle = String(select.value || '').toLowerCase();
        if (!cle || !Object.prototype.hasOwnProperty.call(lots, cle)) {
            return;
        }

        var lot = lots[cle];
        var valeur = lot.colis;
        if (valeur === null || valeur === undefined || valeur === '') {
            return;
        }

        var champ = champSacs(select);
        if (!champ) {
            return;
        }

        var nombre = Number(valeur);
        var cible = isFinite(nombre) ? String(Math.round(nombre)) : String(valeur);
        if (String(champ.value) !== cible) {
            prevenir(champ, cible);
        }
    }

    /* ------------------------------------------------------------------
     *  3. Masquer les lots deja charges ailleurs
     * ------------------------------------------------------------------ */
    var SUFFIXE = /\s*[—–-]\s*d[ée]j[àa]\s+charg[ée]\s*$/i;

    function masquerLotsUtilises() {
        var selects = selectsLots();
        var utilises = {};

        selects.forEach(function (select) {
            var cle = String(select.value || '').toLowerCase();
            if (cle) {
                utilises[cle] = (utilises[cle] || 0) + 1;
            }
        });

        selects.forEach(function (select) {
            var valeur = String(select.value || '').toLowerCase();

            Array.prototype.forEach.call(select.options, function (option) {
                if (!option.value) {
                    return;
                }

                var cle = String(option.value).toLowerCase();
                var prisAilleurs = (utilises[cle] || 0) > 0 && cle !== valeur;

                if (prisAilleurs) {
                    if (!option.dataset.charge) {
                        option.dataset.charge = '1';
                        option.textContent = option.textContent.trim() + ' — déjà chargé';
                    }
                    option.hidden = true;
                    option.disabled = true;
                } else if (option.dataset.charge) {
                    option.hidden = false;
                    option.disabled = false;
                    option.textContent = option.textContent.replace(SUFFIXE, '');
                    delete option.dataset.charge;
                }
            });
        });
    }

    /* ------------------------------------------------------------------
     *  Branchement
     * ------------------------------------------------------------------ */
    var minuteur = null;
    function rafraichir() {
        if (minuteur) {
            window.clearTimeout(minuteur);
        }
        minuteur = window.setTimeout(function () {
            renommerLibelles(document.body);
            masquerLotsUtilises();
        }, 40);
    }

    zone.addEventListener('change', function (evenement) {
        var cible = evenement.target;
        if (cible && cible.tagName === 'SELECT' && estLot(cible.value)) {
            remplirSacs(cible);
        }
        rafraichir();
    });

    zone.addEventListener('input', function (evenement) {
        if (evenement.target && evenement.target.tagName === 'SELECT') {
            rafraichir();
        }
    });

    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver(rafraichir).observe(zone, { childList: true, subtree: true });
    }

    // Premier passage : libelles et lots deja presents (dossier en modification).
    renommerLibelles(document.body);
    masquerLotsUtilises();
})();

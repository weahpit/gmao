/*!
 * Pagination automatique des tableaux - CITRAC GMAO
 * ------------------------------------------------------------------
 * Tous les tableaux de contenu de l'application recoivent une pagination
 * (10 / 25 / 50 / Tout) sans modifier les gabarits page par page.
 *
 * Pour exclure un tableau :
 *   - attribut  data-pagination="off"
 *   - classe    no-pagination
 *   - conteneur parent avec la classe no-pagination
 *
 * Pour garder une ligne toujours visible (total, message vide...) :
 *   - attribut  data-pagination-fixed sur le <tr>
 *
 * Utilisation dans une autre page (apres un filtre AJAX par exemple) :
 *   window.CitracTablePagination.refreshAll();
 *   window.CitracTablePagination.refresh(monTableau);
 */
(function () {
    'use strict';

    var TAILLES = [10, 25, 50];
    var TAILLE_DEFAUT = 10;
    var compteurInstance = 0;

    function estTableauEligible(table) {
        if (!table || table.tagName !== 'TABLE') {
            return false;
        }
        if (table.getAttribute('data-pagination') === 'off' || table.classList.contains('no-pagination')) {
            return false;
        }
        if (table.closest('.no-pagination, .dataTables_wrapper')) {
            return false;
        }
        if (table.classList.contains('dataTable') || table.id === 'example') {
            return false;
        }

        var tbody = premierTbody(table);
        if (!tbody) {
            return false;
        }

        var lignes = lignesDeDonnees(tbody);
        if (lignes.length === 0) {
            return false;
        }

        // Tableau « vide » : une seule ligne de message sur toute la largeur.
        if (lignes.length === 1) {
            var cellules = lignes[0].cells;
            if (cellules.length === 1 && (cellules[0].colSpan || 1) > 1) {
                return false;
            }
        }

        return true;
    }

    function premierTbody(table) {
        for (var i = 0; i < table.tBodies.length; i++) {
            if (table.tBodies[i].rows.length > 0) {
                return table.tBodies[i];
            }
        }

        return table.tBodies[0] || null;
    }

    function lignesDeDonnees(tbody) {
        var lignes = [];
        for (var i = 0; i < tbody.rows.length; i++) {
            var ligne = tbody.rows[i];
            // Une ligne masquee par un filtre de la page (recherche, statut...)
            // n'entre pas dans le decompte de la pagination.
            if (ligne.hidden && ligne.getAttribute('data-pagination-hidden') !== '1') {
                continue;
            }
            lignes.push(ligne);
        }

        return lignes;
    }

    function formater(nombre) {
        return String(nombre).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function creerBouton(libelle, titre, desactive, action) {
        var bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.className = 'table-pagination__bouton';
        bouton.innerHTML = libelle;
        bouton.setAttribute('aria-label', titre);
        bouton.title = titre;
        bouton.disabled = !!desactive;
        if (action) {
            bouton.addEventListener('click', action);
        }

        return bouton;
    }

    function construire(table, etat) {
        var nav = document.createElement('nav');
        nav.className = 'table-pagination no-print';
        nav.setAttribute('aria-label', 'Pagination du tableau');

        var resume = document.createElement('span');
        resume.className = 'table-pagination__resume';
        nav.appendChild(resume);

        var pages = document.createElement('span');
        pages.className = 'table-pagination__pages';
        nav.appendChild(pages);

        var taille = document.createElement('label');
        taille.className = 'table-pagination__taille';
        taille.appendChild(document.createTextNode('Lignes par page '));

        var select = document.createElement('select');
        select.className = 'form-select form-select-sm';
        select.setAttribute('aria-label', 'Nombre de lignes par page');
        TAILLES.forEach(function (valeur) {
            var option = document.createElement('option');
            option.value = String(valeur);
            option.textContent = String(valeur);
            select.appendChild(option);
        });
        var optionTout = document.createElement('option');
        optionTout.value = 'all';
        optionTout.textContent = 'Tout';
        select.appendChild(optionTout);

        select.addEventListener('change', function () {
            etat.taille = select.value === 'all' ? 'all' : parseInt(select.value, 10);
            etat.page = 1;
            etat.afficher();
        });

        taille.appendChild(select);
        nav.appendChild(taille);

        etat.noeuds = { nav: nav, resume: resume, pages: pages, select: select };

        return nav;
    }

    function rendre(table, etat) {
        var lignes = etat.lignes;
        var total = lignes.length;
        var taille = etat.taille === 'all' ? total : etat.taille;
        var nbPages = taille > 0 ? Math.max(1, Math.ceil(total / taille)) : 1;

        if (etat.page > nbPages) {
            etat.page = nbPages;
        }
        if (etat.page < 1) {
            etat.page = 1;
        }

        var debut = (etat.page - 1) * taille;
        var fin = etat.taille === 'all' ? total : Math.min(debut + taille, total);

        lignes.forEach(function (ligne, index) {
            var visible = etat.taille === 'all' || (index >= debut && index < fin);
            if (ligne.hasAttribute('data-pagination-fixed')) {
                visible = true;
            }
            ligne.hidden = !visible;
            if (visible) {
                ligne.removeAttribute('data-pagination-hidden');
            } else {
                ligne.setAttribute('data-pagination-hidden', '1');
            }
        });

        etat.noeuds.select.value = etat.taille === 'all' ? 'all' : String(etat.taille);

        var resume = total === 0
            ? 'Aucune ligne'
            : (etat.taille === 'all'
                ? formater(total) + ' ligne' + (total > 1 ? 's' : '')
                : formater(debut + 1) + ' \u2013 ' + formater(fin) + ' sur ' + formater(total));
        etat.noeuds.resume.textContent = resume;

        var pages = etat.noeuds.pages;
        pages.innerHTML = '';

        if (etat.taille === 'all' || nbPages <= 1) {
            return;
        }

        pages.appendChild(creerBouton('&laquo;', 'Première page', etat.page === 1, function () {
            etat.page = 1;
            etat.afficher();
        }));
        pages.appendChild(creerBouton('&lsaquo;', 'Page précédente', etat.page === 1, function () {
            etat.page -= 1;
            etat.afficher();
        }));

        numerosPages(etat.page, nbPages).forEach(function (valeur) {
            if (valeur === '...') {
                var points = document.createElement('span');
                points.className = 'table-pagination__points';
                points.textContent = '\u2026';
                pages.appendChild(points);

                return;
            }

            var bouton = creerBouton(String(valeur), 'Page ' + valeur, false, function () {
                etat.page = valeur;
                etat.afficher();
            });
            if (valeur === etat.page) {
                bouton.classList.add('is-active');
                bouton.setAttribute('aria-current', 'page');
            }
            pages.appendChild(bouton);
        });

        pages.appendChild(creerBouton('&rsaquo;', 'Page suivante', etat.page === nbPages, function () {
            etat.page += 1;
            etat.afficher();
        }));
        pages.appendChild(creerBouton('&raquo;', 'Dernière page', etat.page === nbPages, function () {
            etat.page = nbPages;
            etat.afficher();
        }));
    }

    function numerosPages(courante, total) {
        if (total <= 7) {
            var toutes = [];
            for (var i = 1; i <= total; i++) {
                toutes.push(i);
            }

            return toutes;
        }

        var pages = [1];
        var debut = Math.max(2, courante - 1);
        var fin = Math.min(total - 1, courante + 1);

        if (debut > 2) {
            pages.push('...');
        }
        for (var page = debut; page <= fin; page++) {
            pages.push(page);
        }
        if (fin < total - 1) {
            pages.push('...');
        }
        pages.push(total);

        return pages;
    }

    function appliquer(table) {
        if (!estTableauEligible(table)) {
            return;
        }

        var tbody = premierTbody(table);
        var etat = table.__citracPagination;

        if (!etat) {
            compteurInstance += 1;
            etat = {
                id: compteurInstance,
                taille: TAILLE_DEFAUT,
                page: 1,
                noeuds: null,
                afficher: null
            };
            table.__citracPagination = etat;
        }

        etat.lignes = lignesDeDonnees(tbody);

        if (!etat.noeuds) {
            var nav = construire(table, etat);
            var ancre = table.closest('.table-responsive') || table;
            if (ancre.parentNode) {
                ancre.parentNode.insertBefore(nav, ancre.nextSibling);
            }
            etat.noeuds = etat.noeuds || {};
            etat.noeuds.nav = nav;
        }

        etat.afficher = function () {
            rendre(table, etat);
        };

        etat.afficher();
        observer(table, tbody);
    }

    function observer(table, tbody) {
        if (table.__citracObservateur || !window.MutationObserver) {
            return;
        }

        var minuteur = null;
        var observateur = new MutationObserver(function () {
            if (minuteur) {
                clearTimeout(minuteur);
            }
            minuteur = setTimeout(function () {
                var etat = table.__citracPagination;
                if (!etat) {
                    return;
                }
                etat.lignes = lignesDeDonnees(tbody);
                if (typeof etat.afficher === 'function') {
                    etat.afficher();
                }
            }, 120);
        });

        observateur.observe(tbody, { childList: true, subtree: false });
        table.__citracObservateur = observateur;
    }

    function refresh(table) {
        if (!table) {
            return;
        }
        appliquer(table);
    }

    function refreshAll(racine) {
        var conteneur = racine || document;
        var tableaux = conteneur.querySelectorAll('table');
        for (var i = 0; i < tableaux.length; i++) {
            appliquer(tableaux[i]);
        }
    }

    function demarrer() {
        refreshAll(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrer);
    } else {
        demarrer();
    }

    // Les pages qui remplissent un tableau apres un appel AJAX peuvent
    // relancer la pagination sans recharger l'ecran.
    window.CitracTablePagination = {
        refresh: refresh,
        refreshAll: refreshAll
    };
})();

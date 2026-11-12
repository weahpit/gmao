/*!
 * Saisie semi-automatique du module Courrier
 * ------------------------------------------------------------------
 * Champs concernes : Expediteur / Destinataire, Organisme / societe,
 * Service concerne, Responsable du suivi.
 *
 * Le navigateur interroge le serveur (/courriers/suggestions) a chaque
 * frappe, apres un court delai. Le serveur renvoie les VALEURS DISTINCTES
 * deja enregistrees qui correspondent au texte tape : celles qui
 * commencent par ce texte d'abord, puis celles qui le contiennent.
 *
 * Utilisation dans un gabarit :
 *   <input type="text" name="correspondant"
 *          data-courrier-suggest="correspondant"
 *          data-sens="entrant" autocomplete="off">
 * La valeur de data-courrier-suggest est le nom du champ cote serveur :
 * correspondant | organisme | service | responsable.
 *
 * Sans JavaScript, le champ reste un champ texte normal.
 */
(function () {
    'use strict';

    var DELAI = 160;      // ms entre la derniere frappe et la requete
    var MINIMUM = 0;      // 0 = proposer aussi les valeurs existantes des le focus

    function urlBase() {
        var champ = document.querySelector('[data-courrier-suggest]');
        return (champ && champ.dataset.urlSuggestions) || '/courriers/suggestions';
    }

    /** Petite liste deroulante attachee a un champ. */
    function creerListe(champ) {
        var bloc = document.createElement('div');
        bloc.className = 'csj-box';
        bloc.setAttribute('role', 'listbox');
        bloc.hidden = true;

        var parent = champ.parentNode;
        if (getComputedStyle(parent).position === 'static') {
            parent.style.position = 'relative';
        }
        parent.appendChild(bloc);

        return bloc;
    }

    function activer(champ) {
        var nomChamp = champ.dataset.courrierSuggest;
        if (!nomChamp) {
            return;
        }

        var bloc = creerListe(champ);
        var valeurs = [];
        var index = -1;
        var minuteur = null;
        var derniereRequete = '';
        var controleur = null;

        champ.setAttribute('autocomplete', 'off');
        champ.setAttribute('role', 'combobox');
        champ.setAttribute('aria-autocomplete', 'list');
        champ.setAttribute('aria-expanded', 'false');

        function fermer() {
            bloc.hidden = true;
            bloc.innerHTML = '';
            index = -1;
            champ.setAttribute('aria-expanded', 'false');
        }

        function choisir(valeur) {
            champ.value = valeur;
            fermer();
            champ.dispatchEvent(new Event('change', { bubbles: true }));
            champ.focus();
        }

        function surligner(nouvelIndex) {
            var items = bloc.querySelectorAll('.csj-item');
            if (!items.length) {
                return;
            }
            index = (nouvelIndex + items.length) % items.length;
            for (var i = 0; i < items.length; i++) {
                items[i].classList.toggle('actif', i === index);
            }
            var actif = items[index];
            if (actif && actif.scrollIntoView) {
                actif.scrollIntoView({ block: 'nearest' });
            }
        }

        function afficher(liste) {
            valeurs = liste || [];
            bloc.innerHTML = '';
            index = -1;

            if (!valeurs.length) {
                fermer();
                return;
            }

            var texte = (champ.value || '').trim().toLowerCase();
            valeurs.forEach(function (valeur, i) {
                var item = document.createElement('div');
                item.className = 'csj-item';
                item.setAttribute('role', 'option');

                if (texte && valeur.toLowerCase().indexOf(texte) === 0) {
                    var fort = document.createElement('strong');
                    fort.textContent = valeur.slice(0, texte.length);
                    item.appendChild(fort);
                    item.appendChild(document.createTextNode(valeur.slice(texte.length)));
                } else {
                    item.textContent = valeur;
                }

                item.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    choisir(valeur);
                });
                item.addEventListener('mouseenter', function () {
                    surligner(i);
                });
                bloc.appendChild(item);
            });

            bloc.hidden = false;
            champ.setAttribute('aria-expanded', 'true');
        }

        function interroger(texte) {
            var sens = champ.dataset.sens || '';
            var cle = nomChamp + '|' + sens + '|' + texte;

            if (cle === derniereRequete) {
                return;
            }
            derniereRequete = cle;

            if (controleur) {
                controleur.abort();
            }
            controleur = ('AbortController' in window) ? new AbortController() : null;

            var params = new URLSearchParams({ champ: nomChamp, q: texte });
            if (sens) {
                params.set('sens', sens);
            }

            fetch(urlBase() + '?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controleur ? controleur.signal : undefined
            })
                .then(function (r) { return r.ok ? r.json() : { valeurs: [] }; })
                .then(function (data) {
                    if (document.activeElement === champ || bloc.contains(document.activeElement)) {
                        afficher(data && data.valeurs);
                    }
                })
                .catch(function () { /* requete annulee ou reseau indisponible */ });
        }

        champ.addEventListener('input', function () {
            clearTimeout(minuteur);
            var texte = (champ.value || '').trim();
            if (texte.length < MINIMUM) {
                fermer();
                return;
            }
            minuteur = setTimeout(function () { interroger(texte); }, DELAI);
        });

        champ.addEventListener('focus', function () {
            if ((champ.value || '').trim().length >= MINIMUM) {
                interroger((champ.value || '').trim());
            }
        });

        champ.addEventListener('keydown', function (e) {
            if (bloc.hidden) {
                if (e.key === 'ArrowDown' && (champ.value || '').trim().length >= MINIMUM) {
                    e.preventDefault();
                    interroger((champ.value || '').trim());
                }
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                surligner(index + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                surligner(index - 1);
            } else if (e.key === 'Enter') {
                if (index >= 0 && valeurs[index] !== undefined) {
                    e.preventDefault();
                    choisir(valeurs[index]);
                }
            } else if (e.key === 'Escape') {
                fermer();
            }
        });

        champ.addEventListener('blur', function () {
            setTimeout(fermer, 120);
        });

        // Un champ masque par le changement de sens (expediteur <-> destinataire)
        // ne doit pas garder de liste ouverte.
        document.addEventListener('click', function (e) {
            if (e.target !== champ && !bloc.contains(e.target)) {
                fermer();
            }
        });
    }

    function initialiser() {
        var champs = document.querySelectorAll('[data-courrier-suggest]');
        for (var i = 0; i < champs.length; i++) {
            if (!champs[i].dataset.csjPret) {
                champs[i].dataset.csjPret = '1';
                activer(champs[i]);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiser);
    } else {
        initialiser();
    }
})();

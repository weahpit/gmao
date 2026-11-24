(function () {
    'use strict';
    const cases = Array.from(document.querySelectorAll('.dc-selection'));
    const compteur = document.getElementById('dc-nb-selection');
    function actualiser() {
        if (compteur) compteur.textContent = cases.filter(c => c.checked).length + ' sélectionné(s)';
    }
    cases.forEach(c => c.addEventListener('change', actualiser));
    [['dc-tout-cocher', true], ['dc-tout-decocher', false]].forEach(([id, valeur]) => {
        const btn = document.getElementById(id);
        if (btn) btn.addEventListener('click', () => { cases.forEach(c => { c.checked = valeur; }); actualiser(); });
    });
    document.querySelectorAll('.dc-suppression-simple').forEach(form => {
        form.addEventListener('submit', event => {
            if (!window.confirm('Supprimer définitivement le bon ' + form.dataset.bon + ' ? Cette action est irréversible.')) event.preventDefault();
        });
    });
    actualiser();
})();

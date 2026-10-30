/*
 * Module Demande d'Achat (DA) - actions front.
 * Depend de : URL_APP (window.location.origin + '/') et showToast() definis
 * dans base.html.twig. Fallback inclus si absents.
 */
var DemandeAchat = (function () {
  'use strict';

  function baseUrl() {
    if (typeof URL_APP !== 'undefined' && URL_APP) { return URL_APP; }
    return window.location.origin + '/';
  }

  function toast(msg, type) {
    if (typeof showToast === 'function') { showToast(msg, type || 'info'); return; }
    alert(msg);
  }

  function post(action, id, data, okMsg) {
    var body = new FormData();
    if (data) {
      Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
    }
    fetch(baseUrl() + 'api/demande-achat/' + action + '/' + id, {
      method: 'POST',
      body: body,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (res.ok && res.j && res.j.ok) {
          toast(okMsg || 'Operation effectuee.', 'success');
          setTimeout(function () { window.location.reload(); }, 600);
        } else {
          toast((res.j && res.j.message) ? res.j.message : 'Operation impossible.', 'error');
        }
      })
      .catch(function () { toast('Erreur reseau. Reessayez.', 'error'); });
  }

  return {
    // Magasinier : enregistrer + soumettre a la direction
    soumettre: function (id) {
      if (!confirm('Enregistrer cette demande et la soumettre a la direction ?')) { return; }
      post('soumettre', id, null, 'Demande soumise a la direction.');
    },

    // Direction : valider (avec ajustements par ligne si presents)
    valider: function (id) {
      var decisions = {};
      var block = document.querySelector('.da-detail-inner[data-da="' + id + '"]');
      if (block) {
        block.querySelectorAll('.da-line').forEach(function (tr) {
          var idx = tr.getAttribute('data-idx');
          var valide = tr.querySelector('.l-valide') ? tr.querySelector('.l-valide').checked : true;
          var qvEl = tr.querySelector('.l-qv');
          var cmdEl = tr.querySelector('.l-cmd');
          decisions[idx] = {
            valide: valide,
            quantite_validee: qvEl ? parseFloat(qvEl.value || '0') || 0 : 0,
            a_commander: cmdEl ? cmdEl.checked : false
          };
        });
      }
      var c = prompt('Commentaire (facultatif) pour la validation :', '');
      if (c === null) { return; }
      post('valider', id, { commentaire: c, decisions: JSON.stringify(decisions) }, 'Demande validee.');
    },

    // Direction : refuser (motif obligatoire)
    refuser: function (id) {
      var motif = prompt('Motif du refus (obligatoire) :', '');
      if (motif === null) { return; }
      if (motif.trim() === '') { toast('Le motif de refus est obligatoire.', 'error'); return; }
      post('refuser', id, { motif: motif }, 'Demande refusee.');
    },

    // Magasinier : stock insuffisant -> commander
    commander: function (id) {
      var f = prompt('Nom du fournisseur pour la commande :', '');
      if (f === null) { return; }
      if (f.trim() === '') { toast('Indiquez le fournisseur.', 'error'); return; }
      post('commander', id, { fournisseur_nom: f }, 'Commande fournisseur enregistree.');
    },

    // Magasinier : sortir les articles
    servir: function (id) {
      var ref = prompt('Reference du bon de sortie (facultatif) :', '');
      if (ref === null) { return; }
      post('servir', id, { bon_sortie_ref: ref }, 'Articles sortis, demande servie.');
    }
  };
})();

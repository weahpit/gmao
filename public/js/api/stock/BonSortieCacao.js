/**
 * Signature des BONS DE SORTIE CACAO par le Directeur Général (interface web).
 * -------------------------------------------------------------------------
 * Appelle POST /bons-cacao/a-signer/{token}/signer  (ROLE_DG / ROLE_ADMIN).
 * Fait passer le bon de EN_ATTENTE -> VALIDE, comme les bons de sortie PDR.
 *
 * Réutilise la modal et la logique de public/js/api/stock/BonSortie.js
 * (jQuery, socket.io non requis, URL_APP, showToast, Bootstrap 5).
 */
(function () {
    'use strict';

    function appUrl() {
        return (typeof URL_APP !== 'undefined') ? URL_APP : (window.location.origin + '/');
    }

    function cleanBase() {
        // URL_APP se termine par « / » : on construit des chemins relatifs à la racine.
        return appUrl().replace(/\/+$/, '/');
    }

    function poster(bonToken, action, observation, $btn) {
        if (!bonToken) {
            showToast('Bon introuvable (jeton manquant).', { type: 'error', title: 'Bon cacao' });
            return;
        }
        if ($btn) { $btn.prop('disabled', true).css('opacity', 0.6); }

        $.ajax({
            url: cleanBase() + 'bons-cacao/a-signer/' + encodeURIComponent(bonToken) + '/' + action,
            type: 'POST',
            data: { observation: observation || '' },
            xhrFields: { withCredentials: true },
            success: function (response) {
                var data = response;
                try { if (typeof response === 'string') data = JSON.parse(response); } catch (e) {}

                if (data && data.code === 'success') {
                    showToast(data.msg || 'Opération réussie.', { type: 'success', title: 'Bon cacao', duration: 4000 });
                    var $zone = $('.bon-signature-zone[data-token="' + bonToken + '"]');
                    if (action === 'signer') {
                        $zone.html('<span class="badge" style="background:#e6f4ea;color:#1b5e20;font-weight:600;padding:8px 12px;">' +
                            '\u2713 Valid\u00e9 (sign\u00e9 par le Directeur G\u00e9n\u00e9ral)</span>');
                        $('#bsCompteur').text(Math.max(0, (parseInt($('#bsCompteur').text(), 10) || 0) - 1));
                    } else {
                        $zone.html('<span class="badge" style="background:#eceff1;color:#37474f;font-weight:600;padding:8px 12px;">Bon annul\u00e9</span>');
                    }
                } else {
                    showToast((data && data.msg) || 'Opération impossible.', { type: 'warning', title: 'Bon cacao', duration: 5000 });
                    if ($btn) { $btn.prop('disabled', false).css('opacity', 1); }
                }
            },
            error: function (xhr) {
                var msg = 'Erreur serveur (' + xhr.status + ').';
                if (xhr.status === 403) msg = 'Acc\u00e8s refus\u00e9 : la signature est r\u00e9serv\u00e9e au Directeur G\u00e9n\u00e9ral (ROLE_DG).';
                else if (xhr.status === 404) msg = 'Bon de sortie cacao introuvable.';
                else if (xhr.status === 409) msg = 'Ce bon ne peut pas \u00eatre sign\u00e9 dans son \u00e9tat actuel.';
                else if (xhr.status === 401) msg = 'Session expir\u00e9e, reconnectez-vous.';
                try {
                    var d = JSON.parse(xhr.responseText);
                    if (d && d.msg) msg = d.msg;
                } catch (e) {}
                showToast(msg, { type: 'error', title: 'Bon cacao', duration: 5000 });
                if ($btn) { $btn.prop('disabled', false).css('opacity', 1); }
            }
        });
    }

    $(document).on('click', '.btn-signer-bon-cacao', function () {
        var $btn = $(this);
        var token = $btn.data('token');
        if (!confirm('Signer ce bon de sortie cacao ? La sortie sera alors autoris\u00e9e.')) { return; }
        poster(token, 'signer', '', $btn);
    });

    $(document).on('click', '.btn-annuler-bon-cacao', function () {
        var $btn = $(this);
        var token = $btn.data('token');
        var obs = window.prompt('Motif de l\u2019annulation (facultatif) :', '');
        if (obs === null) { return; }
        poster(token, 'annuler', obs, $btn);
    });

    window.signerBonCacao = poster;
})();

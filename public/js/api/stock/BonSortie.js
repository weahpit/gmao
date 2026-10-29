/**
 * Signature d'un bon de sortie par le Directeur Général (interface web).
 * -------------------------------------------------------------------------
 * Appelle POST  {URL_APP}api/bonSortie/signer/{token}  (rôle ROLE_DG).
 * Fait passer le bon de EN_ATTENTE -> VALIDE.
 *
 * Dépendances déjà présentes dans le projet :
 *   - jQuery        (public/js/external/jquery.js)
 *   - URL_APP       (public/js/app.js : window.location.origin + '/')
 *   - showToast()   (public/js/tools/showToast.js)
 *   - Bootstrap 5   (modal de confirmation)
 *
 * À charger APRÈS jquery/showToast, par ex. dans base.html.twig :
 *   <script src="{{ asset('js/api/stock/BonSortie.js') }}"></script>
 */
(function () {
    'use strict';

    // --- Modal de confirmation (créée une seule fois) -----------------------
    function getModal() {
        var el = document.getElementById('modalSignerBon');
        if (el) return el;

        var html =
            '<div class="modal fade" id="modalSignerBon" tabindex="-1" aria-hidden="true">' +
            '  <div class="modal-dialog modal-dialog-centered">' +
            '    <div class="modal-content" style="border-radius:12px;overflow:hidden;">' +
            '      <div class="modal-header text-white" style="background:#5a371e;">' +
            '        <h5 class="modal-title">Signer le bon de sortie</h5>' +
            '        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>' +
            '      </div>' +
            '      <div class="modal-body">' +
            '        En signant ce bon, vous confirmez l\u2019autorisation de sortie des articles.<br>' +
            '        Le bon passera au statut <b>Valid\u00e9</b> et pourra ensuite \u00eatre cl\u00f4tur\u00e9 par le vigil.' +
            '      </div>' +
            '      <div class="modal-footer">' +
            '        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>' +
            '        <button type="button" id="btnConfirmerSignature" class="btn fw-bold text-white" style="background:#5a371e;">Confirmer la signature</button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>';
        document.body.insertAdjacentHTML('beforeend', html);
        return document.getElementById('modalSignerBon');
    }

    // --- Appel AJAX de signature --------------------------------------------
    function signerBonSortie(token, $btnDeclencheur) {
        if (!token) {
            showToast('Token du bon introuvable.', { type: 'error', title: 'Signature' });
            return;
        }
        if ($btnDeclencheur) {
            $btnDeclencheur.prop('disabled', true).css('opacity', 0.6);
        }

        $.ajax({
            url: URL_APP + 'api/bonSortie/signer/' + encodeURIComponent(token),
            type: 'POST',
            xhrFields: { withCredentials: true }, // session + cookie
            success: function (response) {
                var data = response;
                try { if (typeof response === 'string') data = JSON.parse(response); } catch (e) {}

                if (data && (data.code === 'success')) {
                    showToast(data.msg || 'Bon sign\u00e9 avec succ\u00e8s.', {
                        type: 'success', title: 'Bon valid\u00e9', duration: 4000
                    });
                    majZoneStatut(token, 'VALIDE');
                } else {
                    showToast((data && data.msg) || 'Signature impossible.', {
                        type: 'warning', title: 'Signature', duration: 5000
                    });
                    if ($btnDeclencheur) $btnDeclencheur.prop('disabled', false).css('opacity', 1);
                }
            },
            error: function (xhr) {
                var msg = 'Erreur serveur (' + xhr.status + ').';
                if (xhr.status === 403) msg = 'Acc\u00e8s refus\u00e9 : r\u00f4le Directeur G\u00e9n\u00e9ral (ROLE_DG) requis.';
                else if (xhr.status === 404) msg = 'Bon de sortie introuvable.';
                else if (xhr.status === 409) msg = 'Ce bon ne peut pas \u00eatre sign\u00e9 dans son \u00e9tat actuel.';
                else if (xhr.status === 401) msg = 'Session expir\u00e9e, reconnectez-vous.';
                try {
                    var d = JSON.parse(xhr.responseText);
                    if (d && d.msg) msg = d.msg;
                } catch (e) {}
                showToast(msg, { type: 'error', title: 'Signature', duration: 5000 });
                if ($btnDeclencheur) $btnDeclencheur.prop('disabled', false).css('opacity', 1);
            }
        });
    }

    // --- Met \u00e0 jour l'affichage apr\u00e8s signature -------------------------
    function majZoneStatut(token, nouveauStatut) {
        var $zones = $('.bon-signature-zone[data-token="' + token + '"]');
        if ($zones.length && nouveauStatut === 'VALIDE') {
            $zones.html(
                '<span class="badge" style="background:#e6f4ea;color:#1b5e20;font-weight:600;padding:8px 12px;">' +
                '\u2713 Valid\u00e9 (sign\u00e9 par le Directeur G\u00e9n\u00e9ral)</span>'
            );
        }
        // Si une ligne de tableau porte le statut, on peut aussi la rafra\u00eechir :
        $('tr[data-bon-token="' + token + '"] .statut-bon').text('Valid\u00e9');
    }

    // --- D\u00e9l\u00e9gation d'\u00e9v\u00e9nement : marche m\u00eame pour les boutons inject\u00e9s en AJAX ---
    $(document).on('click', '.btn-signer-bon', function () {
        var $btn = $(this);
        var token = $btn.data('token') || $btn.closest('.bon-signature-zone').data('token');

        var modalEl = getModal();
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        // (re)branche le bouton de confirmation sur ce token pr\u00e9cis
        var $confirm = $('#btnConfirmerSignature');
        $confirm.off('click').on('click', function () {
            modal.hide();
            signerBonSortie(token, $btn);
        });
        modal.show();
    });

    // Expose la fonction si besoin d'un appel direct ailleurs
    window.signerBonSortie = signerBonSortie;
})();

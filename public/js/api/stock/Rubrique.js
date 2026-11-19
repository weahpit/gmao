/* global URL_APP, showToast, enhanceTable, clearForm, ValidateOnKeypress */

function getAllRubriques(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllRubriques',
        type : 'GET',
        success: function (response){
            let reponse = response
            if (typeof reponse === 'string') { try { reponse = JSON.parse(reponse) } catch (e) { reponse = {} } }

            if (!reponse || !reponse.data) {
                ctrl_name.innerHTML = '<div class="text-danger p-3">' + ((reponse && reponse.msg) || 'Aucune donnée reçue.') + '</div>';
                return;
            }

            if (ctrl_type === 1){
                contentCtrl += ''
                    + '<table class="table table-hover align-middle">'
                    + '  <thead><tr>'
                    + '    <th>Rubrique</th>'
                    + '    <th>Active</th>'
                    + '    <th>Position</th>'
                    + '    <th>Mouvements</th>'
                    + '    <th class="text-end">Actions</th>'
                    + '  </tr></thead><tbody>'

                for (var i=0;i < reponse.data.length;i++){
                    const r = reponse.data[i]
                    const badge = r.mouvements > 0
                        ? '<span class="badge rounded-pill" style="background:#f3ece3;color:#6b4a2f;border:1px solid #e6d9c9;">' + r.mouvements + '</span>'
                        : '<span class="badge rounded-pill" style="background:#eef6ef;color:#2e6b43;border:1px solid #cfe4d4;">0</span>'

                    contentCtrl += '<tr class="a_rubrique" id="' + r.id + '" data-libelle="' + String(r.rubrique).replace(/"/g, '&quot;') + '">'
                    contentCtrl += '<td><a href="#">' + r.rubrique + '</a></td>'
                    contentCtrl += '<td><a href="#">' + r.active + '</a></td>'
                    contentCtrl += '<td><a href="#">' + r.position + '</a></td>'
                    contentCtrl += '<td>' + badge + '</td>'
                    contentCtrl += '<td class="text-end">'
                        + '<button type="button" class="btn btn-sm btn-outline-danger btn-suppr-rubrique"'
                        + ' data-id="' + r.id + '" data-libelle="' + String(r.rubrique).replace(/"/g, '&quot;') + '">'
                        + 'Supprimer</button>'
                        + '</td>'
                    contentCtrl += '</tr>'
                }
                contentCtrl += '</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Rubriques --</option>'
                for (var j=0;j < reponse.data.length;j++) {
                    contentCtrl += '<option value="' + reponse.data[j].id + '">' + reponse.data[j].rubrique + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1 && typeof enhanceTable === 'function'){
                enhanceTable(".table",{ pageSize: 10, filename: 'rubriques.csv' })
            }
        },
        error : function (response){
            showToast((response && response.msg) || 'Erreur sur le chargement des rubriques', {title: 'Rubriques', type: 'error', duration: 4000 })
        }
    })
}

function saveRubrique(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveRubrique", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'Rubrique'
            })
            if (data.code === "success") {
                clearForm("formRubrique", "rubrique")
                getAllRubriques(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Rubrique', duration: 4000}));
}

/* ------------------------------------------------------------------ */
/*  Suppression d'une rubrique (avec reaffectation des mouvements)     */
/* ------------------------------------------------------------------ */

/**
 * Etape 1 : on demande au serveur combien de mouvements utilisent la
 * rubrique. Aucun mouvement -> simple confirmation. Des mouvements ->
 * choix de la rubrique qui les recevra (ou « DIVERS »).
 */
function ouvrirSuppressionRubrique(id, libelle){
    const donnees = new FormData()
    donnees.append('id_rubrique', id)
    donnees.append('rubrique', libelle)

    fetch(URL_APP + 'api/impactRubrique', {
        method: 'POST',
        body: donnees,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.code !== 'success') {
            showToast(data.msg || 'Impossible de vérifier cette rubrique.', { type: 'warning', title: 'Rubrique', duration: 4000 })
            return
        }

        if (data.total === 0) {
            document.getElementById('supprRubriqueNom').textContent = data.rubrique
            document.getElementById('supprRubriqueId').value = data.id
            new bootstrap.Modal(document.getElementById('modalSupprRubrique')).show()
            return
        }

        // Reaffectation obligatoire
        document.getElementById('reaffNomRubrique').textContent = data.rubrique
        document.getElementById('reaffIdRubrique').value = data.id
        document.getElementById('reaffTotal').textContent = data.total
        document.getElementById('reaffDetails').textContent = (data.details || []).join(' · ')

        const select = document.getElementById('reaffCible')
        select.innerHTML = ''
        ;(data.cibles || []).forEach(function (c) {
            const opt = document.createElement('option')
            opt.value = c.libelle
            opt.textContent = c.libelle
            select.appendChild(opt)
        })
        const optDivers = document.createElement('option')
        optDivers.value = data.divers || 'DIVERS'
        optDivers.textContent = (data.divers || 'DIVERS') + ' (créée si elle n\'existe pas)'
        select.appendChild(optDivers)

        new bootstrap.Modal(document.getElementById('modalReaffecterRubrique')).show()
    })
    .catch(err => showToast('Erreur : ' + err, { type: 'error', title: 'Rubrique', duration: 4000 }))
}

/** Etape 2 : suppression effective. */
function confirmerSuppressionRubrique(id, cible, bouton){
    const donnees = new FormData()
    donnees.append('id_rubrique', id)
    if (cible) { donnees.append('cible', cible) }

    if (bouton) { bouton.disabled = true }

    fetch(URL_APP + 'api/deleteRubrique', {
        method: 'POST',
        body: donnees,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        showToast(data.msg, { type: data.code, title: 'Rubrique', duration: 4000 })
        if (data.code === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('modalSupprRubrique'))?.hide()
            bootstrap.Modal.getInstance(document.getElementById('modalReaffecterRubrique'))?.hide()
            getAllRubriques(document.querySelector('.card-body'), 1)
        }
    })
    .catch(err => showToast('Erreur : ' + err, { type: 'error', title: 'Rubrique', duration: 4000 }))
    .finally(function () { if (bouton) { bouton.disabled = false } })
}

function getSingleRubrique(formData) {
    return new Promise((resolve, reject) => {
        fetch( URL_APP + "api/getSingleRubrique", { method: "POST", body: formData })
            .then(res => res.json())
            .then(data => {
                resolve(data)
            })
            .catch(err => {
                showToast("❌ Erreur : " + err, {title: 'Rubrique', duration: 4000})
                reject(err)
            })
    })
}

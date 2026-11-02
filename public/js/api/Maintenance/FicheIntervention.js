function getAllFichesIntervention(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllFichesIntervention',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="">
                    <thead><tr>
                            <th>N°</th>
                            <th>Créée le</th>
                            <th>Type</th>
                            <th>Nature</th>
                            <th>Zone</th>
                            <th>Statut</th>
                            <th>Service</th>
                            <th>Actions</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#" class="fw-bold">' + reponse.data[i].reference + '</a></td>'
                    contentCtrl +='<td>' + reponse.data[i].date_creation + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].type_intervention + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].nature_intervention + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].zone + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].statut + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].service + '</td>'
                    contentCtrl +=`<td class="text-center">
                                                <a href="#" title="Mise à Jour de la fiche" class="btn-update-fi" id="${reponse.data[i].id}"><svg style="height: 16px;color: #2563EB" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen preview-icon"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/></svg></a>
                                                <a href="${URL_APP}PrintFicheMaintenance/${reponse.data[i].id}/pdf" target="_blank" title="Imprimer la fiche" class="btn-print-fi" id="${reponse.data[i].id}"><svg style="height: 16px;color: #1b5e20"  xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-printer-check preview-icon"><path d="M13.5 22H7a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v.5"/><path d="m16 19 2 2 4-4"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/></svg></a>
                                              </td>`
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Fiches Intervention--</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].reference + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            // if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'AlertesAnomalies.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Alerte_Anaomalies}', type: 'error', duration: 4000 })
        }
    })
}
function genererFicheIntervention(formData, ctrl_name){
    fetch( URL_APP + "api/genererFicheIntervention", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Fiche d\'intervention'
            })
            if (data.code === "success") {
                clearForm("formFI", "sujet")
                getAllFichesIntervention(ctrl_name, 1)
                Swal.fire({
                    title: 'Fiche d\'intervention Générée',
                    icon: "success",
                    draggable: true
                });
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Alertes Anomalies', duration: 4000}));
}
function deleteFicheIntervention(id_alerte_anomalie){
    fetch( URL_APP + "api/deleteAlerteAnomalie", { method: "POST", body: id_alerte_anomalie })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Alertes Anomalies'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Alertes Anomalies', duration: 4000}));
}
function getNumeroRef(ctrl) {
        $.ajax({
            url:  URL_APP + 'api/getNewRefFiche',
            type: 'POST',
            success: function(response) {
                let reponse = JSON.parse(response)

                if (reponse.code === "success") {
                   ctrl.value = reponse.ref
                } else {
                    showToast(reponse.msg, {
                        type : reponse.code,
                        duration: 4000,
                        title: 'Alertes Anomalies'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Alertes Anomalies', duration: 4000});
            }
        });
}
function printFI(id_fiche) {
    $.ajax({
        url:  URL_APP + 'PrintFicheMaintenance/' + id_fiche + '/pdf',
        type: 'GET',
        success: function(response) {

        },
        error: function(err) {
            showToast("❌ Erreur : " + err, {title: 'Alertes Anomalies', duration: 4000});
        }
    });
}

// ------------------------------------------------------------------
//  Mapping Statut -> Progression (%) + couleur + libelle d'etape
//  Normalisation : minuscules + sans accents -> tolère OUVERT / Ouverte / Cloturée…
// ------------------------------------------------------------------
function normStatut(s) {
    return (s || '')
        .toString()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // retire accents
        .toLowerCase().trim();
}

function progressionFromStatut(statut) {
    const s = normStatut(statut);
    // Chaque entrée : [motif inclus dans le statut, %, couleur, libellé d'étape]
    if (s.indexOf('clotur') !== -1)                      return { pct: 100, color: 'linear-gradient(90deg,#2e7d32,#4caf50)', badge: '#2e7d32', bg: '#e6f4ea', label: 'Clôturée' };
    if (s.indexOf('termin') !== -1)                      return { pct: 90,  color: 'linear-gradient(90deg,#43a047,#66bb6a)', badge: '#2e7d32', bg: '#e6f4ea', label: 'Terminée' };
    if (s.indexOf('attente') !== -1)                     return { pct: 45,  color: 'linear-gradient(90deg,#b45309,#d97706)', badge: '#b45309', bg: '#fdf0dd', label: 'Attente pièces' };
    if (s.indexOf('en cours') !== -1 || s === 'cours')   return { pct: 60,  color: 'linear-gradient(90deg,#C8953A,#e0b25c)', badge: '#C8953A', bg: '#fdf4e3', label: 'En cours' };
    if (s.indexOf('planifi') !== -1)                     return { pct: 30,  color: 'linear-gradient(90deg,#2563EB,#60a5fa)', badge: '#2563EB', bg: '#e8f0fe', label: 'Planifiée' };
    if (s.indexOf('ouvert') !== -1)                      return { pct: 10,  color: 'linear-gradient(90deg,#9c9c9c,#c2c2c2)', badge: '#6b6b6b', bg: '#efefef', label: 'Ouverte' };
    if (s.indexOf('annul') !== -1)                       return { pct: 0,   color: 'linear-gradient(90deg,#b91c1c,#ef4444)', badge: '#b91c1c', bg: '#fde7e7', label: 'Annulée' };
    // Statut inconnu / vide
    return { pct: 0, color: 'linear-gradient(90deg,#9c9c9c,#c2c2c2)', badge: '#6b6b6b', bg: '#efefef', label: statut || '—' };
}

function esc(v) {
    return (v == null ? '' : String(v))
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// ------------------------------------------------------------------
//  Chargement + rendu du tableau (appelle l'API existante)
// ------------------------------------------------------------------
function loadFichesIntervention() {
    const $div = $('#div_interventions');
    $div.html('<div class="fi-loading">Chargement des fiches…</div>');

    $.ajax({
        url: URL_APP +'api/getAllFichesIntervention',
        method: 'GET',
        dataType: 'json'
    }).done(function (raw) {
        // L'API renvoie JsonResponse(json_encode(...)) -> double encodage possible
        let res = raw;
        if (typeof res === 'string') { try { res = JSON.parse(res); } catch (e) { /* noop */ } }
        if (!res || res.code !== 'success' || !Array.isArray(res.data)) {
            $div.html('<div class="fi-empty">' + esc(res && res.msg ? res.msg : 'Impossible de charger les fiches.') + '</div>');
            return;
        }
        renderFichesTable(res.data);
    }).fail(function () {
        $div.html('<div class="fi-empty">Erreur réseau lors du chargement des fiches.</div>');
    });
}



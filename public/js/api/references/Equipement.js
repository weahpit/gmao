function getAllEquipements(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllEquipements',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Code</th>
                            <th>Nom</th>
                            <th>Marque</th>
                            <th>Modèle</th>
                            <th>Catégorie</th>
                            <th>Etat</th>
                            <th>Criticité</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    var nom = reponse.data[i].nom
                    contentCtrl +='<tr style="font-size: 14px;">'
                    contentCtrl +='<td class="text-danger fw-bold"><a href="#">' + reponse.data[i].code + '</a></td>'
                    contentCtrl +='<td class="text-dark fw-bold">' + nom.toUpperCase() + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].marque + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].modele + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].categorie + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].etat + '</td>'
                    if (reponse.data[i].criticite === "A"){
                        contentCtrl +='<td class="text-center"><span class="badge bg-primary">' + reponse.data[i].criticite + '</span></td>'
                    } else if (reponse.data[i].criticite === "B"){
                        contentCtrl +='<td class="text-center"><span class="badge bg-warning text-primary">' + reponse.data[i].criticite + '</span></td>'
                    } else {
                        contentCtrl +='<td class="text-center"><span class="badge bg-danger">' + reponse.data[i].criticite + '</span></td>'
                    }

                    contentCtrl +='<tr>'
                }

                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Equipements --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    var nom = reponse.data[i].nom
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + nom.toUpperCase() + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Equipements', type: 'error', duration: 4000 })
        }
    })
}

function getAllEquipementsByEquimentType(ctrl_name, id_eq_type, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllEquipementsByEquimentType/' + id_eq_type,
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Code</th>
                            <th>Nom</th>
                            <th>Marque</th>
                            <th>Modèle</th>
                            <th>Catégorie</th>
                            <th>Etat</th>
                            <th>Criticité</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    var nom = reponse.data[i].nom
                    contentCtrl +='<tr style="font-size: 14px;">'
                    contentCtrl +='<td class="text-danger fw-bold"><a href="#">' + reponse.data[i].code + '</a></td>'
                    contentCtrl +='<td class="text-dark fw-bold">' + nom.toUpperCase() + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].marque + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].modele + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].categorie + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].etat + '</td>'
                    if (reponse.data[i].criticite === "A"){
                        contentCtrl +='<td class="text-center"><span class="badge bg-primary">' + reponse.data[i].criticite + '</span></td>'
                    } else if (reponse.data[i].criticite === "B"){
                        contentCtrl +='<td class="text-center"><span class="badge bg-warning text-primary">' + reponse.data[i].criticite + '</span></td>'
                    } else {
                        contentCtrl +='<td class="text-center"><span class="badge bg-danger">' + reponse.data[i].criticite + '</span></td>'
                    }

                    contentCtrl +='<tr>'
                }

                contentCtrl +='</tbody></table>';
            } else {
                for (var i=0;i < reponse.data.length;i++) {
                    var nom = reponse.data[i].nom
                    var marque = reponse.data[i].marque
                    var code = reponse.data[i].code
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">'+ marque + ' {' + code + '}</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Equipements', type: 'error', duration: 4000 })
        }
    })
}

function saveEquipement(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/editEquipement", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Equipement'
            })

            getAllEquipementsFromTdb(ctrl_name, ctrl_type)
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Equipement', duration: 4000}));
}

function deleteEquipement(id_equipement){
    fetch( URL_APP + "api/deleteEquipement", { method: "POST", body: id_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Equipement'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Equipement', duration: 4000}));
}


function getAllEquipementsFromTdb(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllEquipements',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <div class="d-flex justify-content-between align-items-center"><input class="mb-2 input" type="text" style="width:200px;border: 1px solid #2563EB;" placeholder="Rechercher..." id="TxtInputSearch"><span class="text-danger fw-bold" style="background: #f9f1f1;" id="lbl_chargement"></span><span class="fw-lighter text-primary">${reponse.data.length} article(s)</span></div>
                <div style="height:300px; overflow-y:auto; border:1px solid #dee2e6;">
                <table class="table sticky-top" id="tbl_articles_">
                    <thead style="border: 1px #555555 solid;"><tr>
                            <th style="background: #e5e4e4;color: black;padding:8px;width:40px;"></th>
                            <th style="background: #e5e4e4;color: black;padding:8px;">Code</th>
                            <th style="background: #e5e4e4;color: black;padding:8px;">Nom</th>
                            <th style="background: #e5e4e4;color: black;padding:8px;">Marque</th>
                            <th style="background: #e5e4e4;color: black;padding:8px;">Référence</th>
                            <th style="background: #e5e4e4;color: black;padding:8px;">Catégorie</th>
                            <th style="background: #e5e4e4;color: black;padding:8px;">Etat</th>
                            <th style="background: #e5e4e4;color: black;padding:8px;">Criticité</th>
                            <th style="background: #e5e4e4;color: black;padding:8px;">Qté en stock</th>
                            <th style="background: #e5e4e4;color: black;padding:8px;">Seuil</th>
                            <th style="background: #e5e4e4;color: black;padding:8px;">Dernier mouvement</th>
                    </tr></thead>
                    <tbody id="body_eq">
                `
                for (var i=0;i < reponse.data.length;i++){
                    var nom = reponse.data[i].nom
                    if (reponse.data[i].qte === 0){
                        contentCtrl +='<tr class="tr_article" style="font-size: 14px;" id="' + reponse.data[i].id + '">'
                        contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;text-align:center;"><button type="button" class="btn btn-sm btn-toggle-mvt" data-id="' + reponse.data[i].id + '" title="Afficher les mouvements" style="border:1px solid #567e64;color:#567e64;background:#fff;line-height:1;padding:1px 7px;border-radius:4px;font-weight:bold;font-size:15px;">+</button></td>'
                        contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;width:150px;" class="text-danger fw-bold"><a href="#">' + reponse.data[i].code + '</a></td>'

                        if (nom.length > 40){
                            contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;width: 250px;" title="' + nom.toUpperCase() + '">' + nom.slice(0,40).toUpperCase() + '...</td>'
                        } else {
                            contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;width: 250px;" title="' + nom.toUpperCase() + '">' + nom.toUpperCase() + '</td>'
                        }

                        /*contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;"  class="text-dark fw-bold">' + nom.toUpperCase() + '</td>'*/
                        contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;" >' + reponse.data[i].marque + '</td>'
                        contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;width:200px;" >' + reponse.data[i].modele + '</td>'
                        contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;" >' + reponse.data[i].categorie + '</td>'
                        contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;" >' + reponse.data[i].etat + '</td>'
                        if (reponse.data[i].criticite === "A"){
                            contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;"  class="text-center"><span class="badge bg-primary">' + reponse.data[i].criticite + '</span></td>'
                        } else if (reponse.data[i].criticite === "B"){
                            contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;"  class="text-center"><span class="badge bg-warning text-primary">' + reponse.data[i].criticite + '</span></td>'
                        } else {
                            contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;"  class="text-center"><span class="badge bg-danger">' + reponse.data[i].criticite + '</span></td>'
                        }
                        contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;" >' + reponse.data[i].qte + '</td>'
                        contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;" >' + reponse.data[i].seuil + '</td>'
                        contentCtrl +='<td style="background: #ffffff;color: red;padding:2px;padding-left:5px;width: 150px;" >' + reponse.data[i].lastMvt + '</td>'
                        contentCtrl +='</tr>'
                    } else {
                        contentCtrl +='<tr class="tr_article" style="font-size: 14px;" id="' + reponse.data[i].id + '">'
                        contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;text-align:center;"><button type="button" class="btn btn-sm btn-toggle-mvt" data-id="' + reponse.data[i].id + '" title="Afficher les mouvements" style="border:1px solid #567e64;color:#567e64;background:#fff;line-height:1;padding:1px 7px;border-radius:4px;font-weight:bold;font-size:15px;">+</button></td>'
                        contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;width:150px;" class="text-danger fw-bold"><a href="#">' + reponse.data[i].code + '</a></td>'

                        if (nom.length > 40){
                            contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;width: 250px;" title="' + nom.toUpperCase() + '">' + nom.slice(0,40).toUpperCase() + '...</td>'
                        } else {
                            contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;width: 250px;" title="' + nom.toUpperCase() + '">' + nom.toUpperCase() + '</td>'
                        }

                        /*contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;"  class="text-dark fw-bold">' + nom.toUpperCase() + '</td>'*/
                        contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;" >' + reponse.data[i].marque + '</td>'
                        contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;width:200px;" >' + reponse.data[i].modele + '</td>'
                        contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;" >' + reponse.data[i].categorie + '</td>'
                        contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;" >' + reponse.data[i].etat + '</td>'
                        if (reponse.data[i].criticite === "A"){
                            contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;"  class="text-center"><span class="badge bg-primary">' + reponse.data[i].criticite + '</span></td>'
                        } else if (reponse.data[i].criticite === "B"){
                            contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;"  class="text-center"><span class="badge bg-warning text-primary">' + reponse.data[i].criticite + '</span></td>'
                        } else {
                            contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;"  class="text-center"><span class="badge bg-danger">' + reponse.data[i].criticite + '</span></td>'
                        }
                        contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;" >' + reponse.data[i].qte + '</td>'
                        contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;" >' + reponse.data[i].seuil + '</td>'
                        contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;width: 150px;" >' + reponse.data[i].lastMvt + '</td>'
                        contentCtrl +='</tr>'
                    }

                }

                contentCtrl +='</tbody></table></div>';
            } else {
                contentCtrl += '<option value="0">-- Equipements --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    var nom = reponse.data[i].nom
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + nom.toUpperCase() + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl

            if (ctrl_type === 1){
                searchValueFromTable(document.getElementById("TxtInputSearch"), "#body_eq tr")

                document.querySelectorAll('#tbl_articles_ tbody tr').forEach(row => {
                    row.addEventListener('click', function () {
                        document.getElementById('lbl_chargement').innerText = "Chargement..."
                        document.querySelectorAll('#tbl_articles_ tbody tr').forEach(r => {
                            r.classList.remove('selected-row');
                        });

                        this.classList.add('selected-row');

                    });
                });

                // Boutons (+) : dérouler les mouvements de l'article
                document.querySelectorAll('#tbl_articles_ .btn-toggle-mvt').forEach(btn => {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation(); // ne pas déclencher la sélection de ligne
                        toggleMouvementsArticle(this);
                    });
                });
            }
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Equipements', type: 'error', duration: 4000 })
        }
    })
}

function getSingleEquipement(id_equipement) {
    let formData = new FormData();
    formData.append('id_equipement', id_equipement)

    return new Promise((resolve, reject) => {
        fetch( URL_APP + "api/getSingleEquipement", { method: "POST", body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.code === "success") {
                    resolve(data); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 1000,
                        title: 'Equipement'
                    })
                }
            })
            .catch(err => showToast("❌ Erreur : " + err, {title: 'Equipement', duration: 4000}));
    });
}

/**
 * Déroule / referme la ligne d'un article pour afficher ses mouvements.
 * @param {HTMLElement} btn  Le bouton (+) cliqué (porte data-id = id de l'équipement)
 */
function toggleMouvementsArticle(btn){
    const idEquipement = btn.getAttribute('data-id');
    const tr = btn.closest('tr.tr_article');
    if (!tr) { return; }

    const detailExistante = tr.nextElementSibling;

    // Si la ligne détail est déjà ouverte -> on la referme
    if (detailExistante && detailExistante.classList.contains('mvt-detail-row')){
        detailExistante.remove();
        btn.textContent = '+';
        btn.title = 'Afficher les mouvements';
        return;
    }

    // Sinon on l'ouvre
    btn.textContent = '−';
    btn.title = 'Masquer les mouvements';

    const detailRow = document.createElement('tr');
    detailRow.className = 'mvt-detail-row';
    const detailCell = document.createElement('td');
    detailCell.colSpan = 10; // 1 (toggle) + 9 colonnes
    detailCell.style.background = '#f5f7f5';
    detailCell.style.padding = '10px 15px';
    detailCell.innerHTML = '<div class="text-muted" style="font-size:13px;">Chargement des mouvements...</div>';
    detailRow.appendChild(detailCell);
    tr.after(detailRow);

    fetch(URL_APP + 'api/getMouvementsByEquipement/' + idEquipement, { method: 'GET' })
        .then(res => res.json())
        .then(data => {
            if (data.code !== 'success'){
                detailCell.innerHTML = '<div class="text-danger" style="font-size:13px;">' + (data.msg || 'Erreur lors du chargement des mouvements.') + '</div>';
                return;
            }

            const mvts = data.data || [];
            if (mvts.length === 0){
                detailCell.innerHTML = '<div class="text-muted" style="font-size:13px;">Aucun mouvement enregistré pour cet article.</div>';
                return;
            }

            let html = ''
                + '<div style="border:1px solid #d8e0d8;border-radius:6px;overflow:hidden;">'
                + '<table class="table table-sm mb-0" style="font-size:12.5px;background:#fff;">'
                + '<thead  style="background:#567e64;color:#fff;"><tr>'
                + '<th style="background:#567e64;color:#fff;padding:6px 8px;">Nature</th>'
                + '<th style="background:#567e64;color:#fff;padding:6px 8px;">Qté</th>'
                + '<th style="background:#567e64;color:#fff;padding:6px 8px;">Date</th>'
                + '<th style="background:#567e64;color:#fff;padding:6px 8px;">Heure</th>'
                + '<th style="background:#567e64;color:#fff;padding:6px 8px;">Service</th>'
                + '<th style="background:#567e64;color:#fff;padding:6px 8px;">Emplacement</th>'
                + '<th style="background:#567e64;color:#fff;padding:6px 8px;">N° Bon</th>'
                + '<th style="background:#567e64;color:#fff;padding:6px 8px;">Demandeur</th>'
                + '<th style="background:#567e64;color:#fff;padding:6px 8px;">Stock avant</th>'
                + '<th style="background:#567e64;color:#fff;padding:6px 8px;">Stock après</th>'
                + '<th style="background:#567e64;color:#fff;padding:6px 8px;">Mention</th>'
                + '</tr></thead><tbody>';

            for (let j = 0; j < mvts.length; j++){
                const m = mvts[j];
                // Nature : 1 = Entrée, 2 = Sortie
                let badge;
                if (m.type_mvt === '1' || m.type_mvt === 1){
                    badge = '<span class="badge bg-success">Entrée</span>';
                } else {
                    badge = '<span class="badge bg-danger">Sortie</span>';
                }
                html += '<tr>'
                    + '<td style="padding:5px 8px;">' + badge + '</td>'
                    + '<td style="padding:5px 8px;">' + (m.qte || 0) + '</td>'
                    + '<td style="padding:5px 8px;">' + (m.date || '') + '</td>'
                    + '<td style="padding:5px 8px;">' + (m.heure || '') + '</td>'
                    + '<td style="padding:5px 8px;">' + (m.service || '') + '</td>'
                    + '<td style="padding:5px 8px;">' + (m.emplacement || '') + '</td>'
                    + '<td style="padding:5px 8px;">' + (m.numero_bon || '') + '</td>'
                    + '<td style="padding:5px 8px;">' + (m.demandeur || '') + '</td>'
                    + '<td style="padding:5px 8px;">' + (m.stock_avant !== null && m.stock_avant !== undefined ? m.stock_avant : '') + '</td>'
                    + '<td style="padding:5px 8px;">' + (m.stock_apres !== null && m.stock_apres !== undefined ? m.stock_apres : '') + '</td>'
                    + '<td style="padding:5px 8px;">' + (m.mention || '') + '</td>'
                    + '</tr>';
            }

            html += '</tbody></table></div>';
            detailCell.innerHTML = html;
        })
        .catch(err => {
            detailCell.innerHTML = '<div class="text-danger" style="font-size:13px;">❌ Erreur : ' + err + '</div>';
        });
}

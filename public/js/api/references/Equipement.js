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
                <div class="d-flex justify-content-between align-items-center"><input class="mb-2 input" type="text" style="width:200px;border: 1px solid #2563EB;" placeholder="Rechercher..." id="TxtInputSearch"><span class="fw-lighter text-primary">${reponse.data.length} article(s)</span></div>
                <table class="table sticky-top" id="tbl_articles_">
                    <thead style="border: 1px #555555 solid;"><tr>
                            <th style="background: #e5e4e4;color: black;padding:4px;">Code</th>
                            <th style="background: #e5e4e4;color: black;padding:4px;">Nom</th>
                            <th style="background: #e5e4e4;color: black;padding:4px;">Marque</th>
                            <th style="background: #e5e4e4;color: black;padding:4px;">Référence</th>
                            <th style="background: #e5e4e4;color: black;padding:4px;">Catégorie</th>
                            <th style="background: #e5e4e4;color: black;padding:4px;">Etat</th>
                            <th style="background: #e5e4e4;color: black;padding:4px;">Criticité</th>
                            <th style="background: #e5e4e4;color: black;padding:4px;">Qté en stock</th>
                            <th style="background: #e5e4e4;color: black;padding:4px;">Dernier mouvement</th>
                    </tr></thead>
                    <tbody id="body_eq">
                `
                for (var i=0;i < reponse.data.length;i++){
                    var nom = reponse.data[i].nom
                    contentCtrl +='<tr class="tr_article" style="font-size: 14px;" id="' + reponse.data[i].id + '">'
                    contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;" class="text-danger fw-bold"><a href="#">' + reponse.data[i].code + '</a></td>'

                    if (nom.length > 40){
                        contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;" title="' + nom.toUpperCase() + '">' + nom.slice(0,40).toUpperCase() + '...</td>'
                    } else {
                        contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;" title="' + nom.toUpperCase() + '">' + nom.toUpperCase() + '</td>'
                    }

                    /*contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;"  class="text-dark fw-bold">' + nom.toUpperCase() + '</td>'*/
                    contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;" >' + reponse.data[i].marque + '</td>'
                    contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;" >' + reponse.data[i].modele + '</td>'
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
                    contentCtrl +='<td style="background: #ffffff;color: black;padding:2px;padding-left:5px;width: 150px;" >' + reponse.data[i].lastMvt + '</td>'
                    contentCtrl +='</tr>'
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

            if (ctrl_type === 1){
                searchValueFromTable(document.getElementById("TxtInputSearch"), "#body_eq tr")

                document.querySelectorAll('#tbl_articles_ tbody tr').forEach(row => {
                    row.addEventListener('click', function () {

                        document.querySelectorAll('#tbl_articles_ tbody tr').forEach(r => {
                            r.classList.remove('selected-row');
                        });

                        this.classList.add('selected-row');
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

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
    fetch( URL_APP + "api/saveEquipement", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Equipement'
            })
            if (data.code === "success") {
                clearForm("formEquipement", "equipement")
                getAllEquipements(ctrl_name, ctrl_type)
            }
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
function getSingleEquipement(id_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleEquipement' + id_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Equipement'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Equipement', duration: 4000});
            }
        });
    });
}

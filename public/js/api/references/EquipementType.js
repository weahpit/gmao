function getAllEquipementTypes(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url: URL_APP + 'api/getAllEquipementTypes',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                            <thead><tr>
                                <th></th>
                                <th>Code</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Famille</th>
                            </tr></thead>
                            <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    const nom = reponse.data[i].nom || '';
                    contentCtrl +='<tr>'
                    if (reponse.data[i].photo) {
                        contentCtrl += '<td><img class="thumbnail" src="data/equipements/' + reponse.data[i].photo + '" alt="" height="48" id="' + reponse.data[i].photo + '" style="cursor: pointer;"></td>';
                    } else {
                        contentCtrl += '<td></td>';
                    }
                    contentCtrl +='<td><a href="#">' + reponse.data[i].code + '</a></td>'
                    contentCtrl +='<td><a href="#">' + nom.toUpperCase() + '</a></td>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].categorie + '</a></td>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].famille + '</a></td>'

                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else if (ctrl_type === 2){
                contentCtrl += '<option value="0">-- Equipements Types --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    const nom = reponse.data[i].nom || '';
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + nom.toUpperCase()+ '</option>'
                }
            }else {
                for (var i=0;i < reponse.data.length;i++) {
                    const nom = reponse.data[i].nom || '';
                    contentCtrl += '<li><a class="eq_li" href="#" id="' + reponse.data[i].id + '" style="text-transform: uppercase;">' + nom.toUpperCase() + '</a></li>'
                }
            }

            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'EquipementsTypes.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Type_Equipement}', type: 'error', duration: 4000 })
        }
    })
}
function getEquipementTypeByFamille(ctrl_name, ctrl_type, value){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getEquipementTypeByFamille/' + value,
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            const data = reponse.data || [];
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                            <thead><tr>
                                <th></th>
                                <th>Code</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Famille</th>
                            </tr></thead>
                            <tbody>
                `
                for (let i = 0; i < pageData.length; i++) {
                    const item = pageData[i];
                    const nom = item.nom || '';
                    html += '<tr style="font-size: 14px;">';
                    if (item.photo) {
                        html += '<td><img class="thumbnail" src="data/equipements/' + item.photo + '" alt="" height="48" id="' + item.photo + '"></td>';
                    } else {
                        html += '<td></td>';
                    }
                    html += '<td class="text-danger fw-bold"><a href="' + item.id + '">' + item.code + '</a></td>';
                    html += '<td class="text-dark fw-bold">' + nom.toUpperCase() + '</td>';
                    html += '<td class="text-dark fw-bold">' + item.categorie + '</td>';
                    html += '<td class="text-dark fw-bold">' + item.famille + '</td>';
                    html += '</tr>';
                }
                contentCtrl +='</tbody></table>';
            } else if (ctrl_type === 2){
                contentCtrl += '<option value="0">-- Equipements --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    const nom = data[i].nom || '';
                    contentCtrl += '<option value="' + data[i].id + '" style="text-transform: uppercase;">' + nom.toUpperCase() + '</option>';
                }
            } else {
                for (var i=0;i < reponse.data.length;i++) {
                    const nom = data[i].nom || '';
                    contentCtrl +=  '<li><a class="eq_li" href="#" id="' + data[i].id + '" style="text-transform: uppercase;">' + nom.toUpperCase() + '</a></li>';
                }
            }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Type_Equipement}', type: 'error', duration: 4000 })
        }
    })
}

function getEquipementTypeByCategorie(ctrl_name, ctrl_type, value){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getEquipementTypeByCategorie/' + value,
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            const data = reponse.data || [];
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                            <thead><tr>
                                <th></th>
                                <th>Code</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Famille</th>
                            </tr></thead>
                            <tbody>
                `
                for (let i = 0; i < pageData.length; i++) {
                    const item = pageData[i];
                    const nom = item.nom || '';
                    html += '<tr style="font-size: 14px;">';
                    if (item.photo) {
                        html += '<td><img class="thumbnail" src="data/equipements/' + item.photo + '" alt="" height="48" id="' + item.photo + '"></td>';
                    } else {
                        html += '<td></td>';
                    }
                    html += '<td class="text-danger fw-bold"><a href="' + item.id + '">' + item.code + '</a></td>';
                    html += '<td class="text-dark fw-bold">' + nom.toUpperCase() + '</td>';
                    html += '<td class="text-dark fw-bold">' + item.categorie + '</td>';
                    html += '<td class="text-dark fw-bold">' + item.famille + '</td>';
                    html += '</tr>';
                }
                contentCtrl +='</tbody></table>';
            } else if (ctrl_type === 2){
                contentCtrl += '<option value="0">-- Equipements --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    const nom = data[i].nom || '';
                    contentCtrl += '<option value="' + data[i].id + '" style="text-transform: uppercase;">' + nom.toUpperCase() + '</option>';
                }
            } else {
                for (var i=0;i < reponse.data.length;i++) {
                    const nom = data[i].nom || '';
                    contentCtrl +=  '<li><a class="eq_li" href="#" id="' + data[i].id + '" style="text-transform: uppercase;">' + nom.toUpperCase() + '</a></li>';
                }
            }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Type_Equipement}', type: 'error', duration: 4000 })
        }
    })
}

function saveEquipementType(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveEquipementType", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'Equipement Type'
            })
            if (data.code === "success") {
                clearForm("formEquipementType", "CodeEquipement")
                document.getElementById("eqPreview").src = ""
                getAllEquipementTypes(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'EquipementType', duration: 4000}));
}
function deleteEquipementType(id_equipement_type_type_type){
    fetch( URL_APP + "api/deleteEquipementType", { method: "POST", body: id_equipement_type_type_type })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'EquipementType'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'EquipementType', duration: 4000}));
}

function getSingleEquipementType(id_equipement_type) {
    let formData = new FormData();
    formData.append('id_equipement', id_equipement_type)

    return new Promise((resolve, reject) => {
        fetch( URL_APP + "api/getSingleEquipementType", { method: "POST", body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.code === "success") {
                    resolve(data); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 1000,
                        title: 'Equipement Type'
                    })
                }
            })
            .catch(err => showToast("❌ Erreur : " + err, {title: 'EquipementType', duration: 4000}));
    });
}

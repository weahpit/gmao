function getAllTypeEquipements(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllTypeEquipement',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>catégorie</th>
                            <th>Famille</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].categorie + '</a></td>'
                    contentCtrl +='<td><span class="badge p-2 bg-primary fw-light">' + reponse.data[i].famille + '</span></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Catégories Equipements --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].categorie + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Type_Equipement}', type: 'error', duration: 4000 })
        }
    })
}

function getTypeEquipementByFamille(ctrl_name, ctrl_type, value){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getTypeEquipementByFamille/' + value,
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>catégorie</th>
                            <th>Famille</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].categorie + '</a></td>'
                    contentCtrl +='<td><span class="badge p-2 bg-primary fw-light">' + reponse.data[i].famille + '</span></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Catégories Equipements --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].categorie + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Type_Equipement}', type: 'error', duration: 4000 })
        }
    })
}

function saveTypeEquipement(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveTypeEquipement", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Type Equipement'
            })
            if (data.code === "success") {
                getAllTypeEquipements(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Type Equipement', duration: 4000}));
}
function deleteTypeEquipement(id_type_equipement){
    fetch( URL_APP + "api/deleteTypeEquipement", { method: "POST", body: id_type_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Type Equipement'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Type Equipement', duration: 4000}));
}
function getSingleTypeEquipement(id_type_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleTypeEquipement' + id_type_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Type Equipement'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Type Equipement', duration: 4000});
            }
        });
    });
}

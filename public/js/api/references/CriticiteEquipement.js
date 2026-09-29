function getAllCriticiteEquipement(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllCriticites',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Criticite</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="' + reponse.data[i].id + '">' + reponse.data[i].criticite + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Etat de Criticité --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].criticite + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Etat de Criticité}', type: 'error', duration: 4000 })
        }
    })
}
function saveCriticite(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveCriticite", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Criticite Equipement'
            })
            if (data.code === "success") {
                getAllCriticiteEquipement(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Criticite Equipement', duration: 4000}));
}
function deleteCriticite(id_criticite_equipement){
    fetch( URL_APP + "api/deleteCriticite", { method: "POST", body: id_criticite_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Criticite Equipement'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Criticite Equipement', duration: 4000}));
}
function getSingleCriticiteEquipement(id_criticite_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleCriticiteEquipement' + id_criticite_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Criticite Equipement'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Criticite Equipement', duration: 4000});
            }
        });
    });
}

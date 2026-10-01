function getAllFamilles(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllFamilles',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Liste des Familles</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].famille + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Familles --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].famille + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'FamillesEquipements.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Familles', type: 'error', duration: 4000 })
        }
    })
}
function saveFamille(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveFamille", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Famille'
            })
            if (data.code === "success") {
                clearForm("formFamille", "famille")
                getAllFamilles(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Famille', duration: 4000}));
}
function deleteFamille(id_nature_equipement){
    fetch( URL_APP + "api/deleteFamille", { method: "POST", body: id_nature_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Famille'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Famille', duration: 4000}));
}
function getSingleFamille(id_nature_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleFamille' + id_nature_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Famille'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Famille', duration: 4000});
            }
        });
    });
}

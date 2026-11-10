function getAllProprietaires(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllProprietaires',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Proprietaire</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].proprietaire + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Proprietaires --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].proprietaire + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'proprietaires.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  Proprietaires}', type: 'error', duration: 4000 })
        }
    })
}
function saveProprietaire(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveProprietaire", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'Proprietaire'
            })
            if (data.code === "success") {
                clearForm("formProprietaire", "proprietaire")
                getAllProprietaires(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Proprietaire', duration: 4000}));
}
function deleteProprietaire(id_proprietaire){
    fetch( URL_APP + "api/deleteProprietaire", { method: "POST", body: id_proprietaire })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Proprietaire'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Proprietaire', duration: 4000}));
}
function getSingleProprietaire(id_proprietaire) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleProprietaire' + id_proprietaire,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Proprietaire'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Proprietaire', duration: 4000});
            }
        });
    });
}

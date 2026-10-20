function getAllTypeIntervention(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllTypeIntervention',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Libellé</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].libelle + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Types Intervention --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].libelle + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'TypesIntervention.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Types Intervention}', type: 'error', duration: 4000 })
        }
    })
}
function saveTypeIntervention(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveTypeIntervention", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Type Intervention'
            })
            if (data.code === "success") {
                getAllTypeIntervention(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Type Intervention', duration: 4000}));
}
function deleteTypeIntervention(id_type_intervention){
    fetch( URL_APP + "api/deleteTypeIntervention", { method: "POST", body: id_type_intervention })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Type Intervention'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Type Intervention', duration: 4000}));
}
function getSingleTypeIntervention(id_type_intervention) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleTypeIntervention' + id_type_intervention,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Type Intervention'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Type Intervention', duration: 4000});
            }
        });
    });
}

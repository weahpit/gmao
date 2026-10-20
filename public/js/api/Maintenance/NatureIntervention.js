function getAllNatureIntervention(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllNatureIntervention',
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
                contentCtrl += '<option value="0">-- Natures Intervention --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].libelle + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'NaturesInterventions.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Nature Intervention}', type: 'error', duration: 4000 })
        }
    })
}
function saveNatureIntervention(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveNatureIntervention", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Nature Intervention'
            })
            if (data.code === "success") {
                getAllNatureIntervention(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Nature Intervention', duration: 4000}));
}
function deleteNatureIntervention(id_nature_intervention){
    fetch( URL_APP + "api/deleteNatureIntervention", { method: "POST", body: id_nature_intervention })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Nature Intervention'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Natures Intervention', duration: 4000}));
}
function getSingleNatureIntervention(id_nature_intervention) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleNatureIntervention' + id_nature_intervention,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Nature Intervention'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Nature Intervention', duration: 4000});
            }
        });
    });
}

function getAllPriorite(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllPriorite',
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
                contentCtrl += '<option value="0">-- Origines Intervention --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].libelle + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'Priorites.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Origine Intervention}', type: 'error', duration: 4000 })
        }
    })
}
function savePriorite(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/savePriorite", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Origine Intervention'
            })
            if (data.code === "success") {
                getAllPriorite(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Origine Intervention', duration: 4000}));
}
function deletePriorite(id_priorite){
    fetch( URL_APP + "api/deletePriorite", { method: "POST", body: id_priorite })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Origine Intervention'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Origines Intervention', duration: 4000}));
}
function getSinglePriorite(id_priorite) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSinglePriorite' + id_priorite,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Origine Intervention'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Origine Intervention', duration: 4000});
            }
        });
    });
}

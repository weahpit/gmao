function getAllAlerteAnomalie(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllAlerteAnomalie',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Sujet</th>
                            <th>Equipement</th>
                            <th>Zone</th>
                            <th>Emplacement</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].sujet + '</a></td>'
                    contentCtrl +='<td>' + reponse.data[i].equipement + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].zone + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].emplacement + '</td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Alertes--</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].sujet + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'AlertesAnomalies.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Alerte_Anaomalies}', type: 'error', duration: 4000 })
        }
    })
}
function saveAlerteAnomalie(formData){
    fetch( URL_APP + "api/saveAlerteAnomalie", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Alertes Anomalies'
            })
            if (data.code === "success") {
                clearForm("formAlerte", "sujet")
                Swal.fire({
                    title: "Votre alerte a été remontée !",
                    icon: "success",
                    draggable: true
                });
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Alertes Anomalies', duration: 4000}));
}
function deleteAlerteAnomalie(id_alerte_anomalie){
    fetch( URL_APP + "api/deleteAlerteAnomalie", { method: "POST", body: id_alerte_anomalie })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Alertes Anomalies'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Alertes Anomalies', duration: 4000}));
}
function getSingleAlerteAnomalie(id_alerte_anomalie) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleAlerteAnomalie' + id_alerte_anomalie,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Alertes Anomalies'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Alertes Anomalies', duration: 4000});
            }
        });
    });
}

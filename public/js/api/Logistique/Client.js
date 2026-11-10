function getAllClients(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllClients',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Client</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].client + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Clients --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].client + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'clients.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  Clients}', type: 'error', duration: 4000 })
        }
    })
}
function saveClient(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveClient", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'Client'
            })
            if (data.code === "success") {
                clearForm("formClient", "client")
                getAllClients(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Client', duration: 4000}));
}
function deleteClient(id_client){
    fetch( URL_APP + "api/deleteClient", { method: "POST", body: id_client })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Client'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Client', duration: 4000}));
}
function getSingleClient(id_client) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleClient' + id_client,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Client'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Client', duration: 4000});
            }
        });
    });
}

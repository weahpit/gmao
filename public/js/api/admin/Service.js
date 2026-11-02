function getAllServices(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllServices',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Liste des Services</th>
                            <th>Directions</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].service + '</a></td>'
                    contentCtrl +='<td><span class="badge bg-warning p-2 text-dark">' + reponse.data[i].direction + '</span></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Services --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].service + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'menus.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Services', type: 'error', duration: 4000 })
        }
    })
}
function saveService(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveService", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Service'
            })
            if (data.code === "success") {
                clearForm("formService", "menu")
                getAllServices(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Service', duration: 4000}));
}
function deleteService(id_nature_equipement){
    fetch( URL_APP + "api/deleteService", { method: "POST", body: id_nature_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Service'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Service', duration: 4000}));
}
function getSingleService(id_nature_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleService' + id_nature_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Service'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Service', duration: 4000});
            }
        });
    });
}
function getParentService(ctrl_name){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getParentService',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
                contentCtrl += '<option value="0">-- parents Services --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].menu + '</option>'
                }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des parents Services', type: 'error', duration: 4000 })
        }
    })
}

function getAllDirections(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllDirections',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Liste des Directions</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].direction + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Directions --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].direction + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'direction.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Directions', type: 'error', duration: 4000 })
        }
    })
}
function saveDirection(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveDirection", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Direction'
            })
            if (data.code === "success") {
                clearForm("formDirection", "direction")
                getAllDirections(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Direction', duration: 4000}));
}
function deleteDirection(id_nature_equipement){
    fetch( URL_APP + "api/deleteDirection", { method: "POST", body: id_nature_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Direction'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Direction', duration: 4000}));
}
function getSingleDirection(id_nature_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleDirection' + id_nature_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Direction'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Direction', duration: 4000});
            }
        });
    });
}
function getParentDirection(ctrl_name){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getParentDirection',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
                contentCtrl += '<option value="0">-- parents Directions --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].direction + '</option>'
                }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des parents Directions', type: 'error', duration: 4000 })
        }
    })
}

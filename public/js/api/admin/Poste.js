function getAllPostes(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllPostes',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Poste</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].poste + '</a></td>'
                    contentCtrl +='<tr>'
                }

                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Postes --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].poste + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'poste.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Postes', type: 'error', duration: 4000 })
        }
    })
}

function getAllPostesByService(ctrl_name, ctrl_type, id_service){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllPostesByService/' + id_service,
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Poste</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].poste + '</a></td>'
                    contentCtrl +='<tr>'
                }

                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Postes --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].poste + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'poste.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Postes', type: 'error', duration: 4000 })
        }
    })
}

function savePoste(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/savePoste", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Poste'
            })
            if (data.code === "success") {
                clearForm("formPoste", "poste")
                getAllPostes(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Poste', duration: 4000}));
}
function deletePoste(id_nature_equipement){
    fetch( URL_APP + "api/deletePoste", { method: "POST", body: id_nature_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Poste'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Poste', duration: 4000}));
}
function getSinglePoste(id_nature_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSinglePoste' + id_nature_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Poste'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Poste', duration: 4000});
            }
        });
    });
}
function getParentPoste(ctrl_name){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getParentPoste',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
                contentCtrl += '<option value="0">-- parents Postes --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].poste + '</option>'
                }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des parents Postes', type: 'error', duration: 4000 })
        }
    })
}

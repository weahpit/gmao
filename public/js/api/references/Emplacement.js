function getAllEmplacements(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllEmplacements',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Emplacement</th>
                            <th>Type</th>
                            <th>Zone d'exploitation</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].emplacement + '</a></td>'
                    contentCtrl +='<td>' + reponse.data[i].type_emplacement + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].zone + '</td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Emplacements --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].emplacement + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'emplacements.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Emplacements}', type: 'error', duration: 4000 })
        }
    })
}
function saveEmplacement(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveEmplacement", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'Emplacement'
            })
            if (data.code === "success") {
                clearForm("formEmplacement", "emplacement")
                getAllEmplacements(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Emplacement', duration: 4000}));
}
function deleteEmplacement(id_emplacement){
    fetch( URL_APP + "api/deleteEmplacement", { method: "POST", body: id_emplacement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Emplacement'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Emplacement', duration: 4000}));
}
function getSingleEmplacement(id_emplacement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleEmplacement' + id_emplacement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Emplacement'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Emplacement', duration: 4000});
            }
        });
    });
}

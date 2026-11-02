function getAllTypeEq(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllTypeEq',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Type Equipement</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].type_eq + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Types équipements --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].type_eq + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'TypesEquipements.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Type_Equipement}', type: 'error', duration: 4000 })
        }
    })
}

function saveTypeEq(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveTypeEq", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Type Equipement'
            })
            if (data.code === "success") {
                getAllTypeEq(ctrl_name, ctrl_type)
                clearForm("formType", "Type_eq")
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Type Equipement', duration: 4000}));
}
function deleteTypeEq(id_type_equipement){
    fetch( URL_APP + "api/deleteTypeEq", { method: "POST", body: id_type_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Type Equipement'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Type Equipement', duration: 4000}));
}
function getSingleTypeEq(id_type_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleTypeEq' + id_type_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Type Equipement'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Type Equipement', duration: 4000});
            }
        });
    });
}

function getAllNatureEquipement(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllNatureEquipement',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table>
                    <thead><tr>
                            <th>Nature</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="' + reponse.data[i].id + '">' + reponse.data[i].nature + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- nature de l\'équipement --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].nature + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Nature_Equipement}', type: 'error', duration: 4000 })
        }
    })
}
function saveNatureEquipement(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveNatureEquipement", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Nature Equipement'
            })
            if (data.code === "success") {
                getAllNatureEquipement(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Nature Equipement', duration: 4000}));
}
function deleteNatureEquipement(id_nature_equipement){
    fetch( URL_APP + "api/deleteNatureEquipement", { method: "POST", body: id_nature_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Nature Equipement'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Nature Equipement', duration: 4000}));
}
function getSingleNatureEquipement(id_nature_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleNatureEquipement' + id_nature_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Nature Equipement'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Nature Equipement', duration: 4000});
            }
        });
    });
}

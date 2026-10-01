function getAllGroupes(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllGroupes',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Liste des Groupes</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#"><img class="me-2" src="icons/group-users.png" height="16" alt="">' + reponse.data[i].groupe + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Groupes --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].groupe + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'groupes.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Groupes', type: 'error', duration: 4000 })
        }
    })
}
function saveGroupe(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveGroupe", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Groupe'
            })
            if (data.code === "success") {
                clearForm("formGroupe", "groupe")
                getAllGroupes(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Groupe', duration: 4000}));
}
function deleteGroupe(id_nature_equipement){
    fetch( URL_APP + "api/deleteGroupe", { method: "POST", body: id_nature_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Groupe'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Groupe', duration: 4000}));
}
function getSingleGroupe(id_nature_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleGroupe' + id_nature_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Groupe'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Groupe', duration: 4000});
            }
        });
    });
}

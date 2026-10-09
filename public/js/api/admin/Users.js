function getAllUsers(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllUsers',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th></th>
                            <th>Mle</th>
                            <th>Nom</th>
                            <th>Prenoms</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Groupe</th>
                            <th>Service/Direction</th>
                            <th>Poste</th>
                            <th>Actions</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    if (reponse.data[i].actif ==="NON"){
                        contentCtrl +='<tr style="background-color: #f9e6e6;color: #c53030;font-weight: bold;">'
                    } else {
                        contentCtrl +='<tr>'
                    }

                    if (reponse.data[i].photo){
                        contentCtrl +='<td><img class="thumbnail" src="data/users/photos/' + reponse.data[i].photo +'" alt="" height="40" style="border-radius: 50px;border-bottom: 2px solid #4a2d1a;" id="' + reponse.data[i].photo + '"></td>'
                    } else {
                        contentCtrl +='<td><img src="icons/user.png" alt="" height="40"></td>'
                    }
                    contentCtrl +='<td>' + reponse.data[i].matricule + '</td>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].nom + '</a></td>'
                    contentCtrl +='<td>' + reponse.data[i].prenoms + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].email + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].mobile + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].groupe + '</td>'
                    if (reponse.data[i].service){
                        contentCtrl +='<td>' + reponse.data[i].service + '</td>'
                    } else {
                        contentCtrl +='<td>' + reponse.data[i].direction + '</td>'
                    }

                    contentCtrl +='<td>' + reponse.data[i].poste + '</td>'
                    contentCtrl +=`<td>
                            <button  class="btn me-2 btn-tooltip" data-tooltip="Editer les informations"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen preview-icon"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/></svg></button>
                            <button class="btn me-2 btn-tooltip" data-tooltip="Bloque l'utilisateur"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-lock preview-icon"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></button>
                        </td>`
                    contentCtrl +='<tr>'
                }

                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Utilisateurs --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].nom + " " +  reponse.data[i].prenoms + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'user.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Users', type: 'error', duration: 4000 })
        }
    })
}
function saveUser(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveUser", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'User'
            })
            if (data.code === "success") {
                clearForm("formUser", "user")
                getAllUsers(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'User', duration: 4000}));
}
function deleteUser(id_nature_equipement){
    fetch( URL_APP + "api/deleteUser", { method: "POST", body: id_nature_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'User'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'User', duration: 4000}));
}
function getSingleUser(id_nature_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleUser' + id_nature_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'User'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'User', duration: 4000});
            }
        });
    });
}
function getParentUser(ctrl_name){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getParentUser',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
                contentCtrl += '<option value="0">-- parents Users --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].user + '</option>'
                }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des parents Users', type: 'error', duration: 4000 })
        }
    })
}

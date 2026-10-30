function getAllUsers(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllUsers',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <div class="d-flex justify-content-between align-items-center p-2" style="border: 1px solid lightgrey"><input class="mb-2 input" type="text" style="width:200px;border: 1px solid #2563EB;" placeholder="Rechercher..." id="TxtInputSearch"><span class="text-danger fw-bold" style="background: #f9f1f1;" id="lbl_chargement"></span><span class="fw-lighter text-primary">${reponse.data.length} utilisateur(s)</span></div>
                <div style="height:300px; overflow-y:auto; border:1px solid #dee2e6;">
                <table class="sticky-top w-100 m-1" id="tbl_users_">
                    <thead><tr>
                            <th></th>
                            <th class="text-center" style="width: 75px;">Mle</th>
                            <th style="min-width: 125px;"">Nom</th>
                            <th>Prenoms</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Groupe</th>
                            <th>Service/Direction</th>
                            <th>Poste</th>
                    </tr></thead>
                    <tbody id="body_users">
                `
                for (var i=0;i < reponse.data.length;i++){
                    if (reponse.data[i].actif ==="NON"){
                        contentCtrl +='<tr class="edit_user" style="background-color: #f9e6e6 !important;color: #fc0202 !important;font-weight: bold;" class="edit_user" id="' + reponse.data[i].id + '">'
                    } else {
                        contentCtrl +='<tr class="edit_user" id="' + reponse.data[i].id + '">'
                    }

                    if (reponse.data[i].photo){
                        contentCtrl +='<td><img class="thumbnail p-2" src="data/users/photos/' + reponse.data[i].photo +'" alt="" height="32" style="border-radius: 50px;border-bottom: 2px solid #4a2d1a;" id="' + reponse.data[i].photo + '"></td>'
                    } else {
                        contentCtrl +='<td><img class="thumbnail p-2" src="icons/user.png" alt="" height="32"></td>'
                    }

                    contentCtrl +='<td class="text-center" >' + reponse.data[i].matricule + '</td>'
                    contentCtrl +='<td>' + reponse.data[i].nom + '</td>'
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
                    contentCtrl +='<tr>'
                }

                contentCtrl +='</tbody></table></div>';
            } else {
                contentCtrl += '<option value="0">-- Utilisateurs --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].nom + " " +  reponse.data[i].prenoms + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            /*if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'user.csv' })}*/
            if (ctrl_type === 1){
                searchValueFromTable(document.getElementById("TxtInputSearch"), "#body_users tr")

                document.querySelectorAll('#tbl_users_ tbody tr').forEach(row => {
                    /*row.addEventListener('click', function () {
                       document.querySelectorAll('#tbl_articles_ tbody tr').forEach(r => {
                            r.classList.remove('selected-row');
                        });
                        this.classList.add('selected-row');
                    });*/
                });

                // Boutons (+) : dérouler les mouvements de l'article
                document.querySelectorAll('#tbl_articles_ .btn-toggle-mvt').forEach(btn => {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation(); // ne pas déclencher la sélection de ligne
                        toggleMouvementsArticle(this);
                    });
                });
            }
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
                document.getElementById('photoPreview').src = ""
                $('#modalAddUser .btn-close').click()
                getAllUsers(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'User', duration: 4000}));
}
function deleteUser(id_user){
    fetch( URL_APP + "api/deleteUser", { method: "POST", body: id_user })
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
function getSingleUser(formData) {  // api/getSingleUser
    return new Promise((resolve, reject) => {
        fetch( URL_APP + "api/getSingleUser", { method: "POST", body: formData })
            .then(res => res.json())
            .then(data => {
                resolve(data); // renvoie l'objet au .then()
            })
            .catch(err => showToast("❌ Erreur : " + err, {title: 'Widget', duration: 4000}));

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

function getAllFournisseurs(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllFournisseurs',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table">
                    <thead style="background: rgba(133,132,132,0.61);font-weight: bold;"><tr>
                            <th>Code</th>
                            <th>Sigle</th>
                            <th>Tel / Mobile</th>
                            <th>email</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#" style="font-weight: bold;">' + reponse.data[i].code + '</a></td>'
                    contentCtrl +='<td>' + reponse.data[i].sigle + '</td>'
                    if (reponse.data[i].tel){
                        contentCtrl +='<td>' + reponse.data[i].tel +' - '  + reponse.data[i].mobile + '</td>'
                    } else {
                        contentCtrl +='<td>' + reponse.data[i].mobile + '</td>'
                    }
                    contentCtrl +='<td>' + reponse.data[i].email + '</td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Sélectionnez un fournisseur --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].sigle + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Fournisseur', type: 'error', duration: 3000})
        }
    })
}
function saveFournisseur(formData, ctrl_name, ctrl_type){
    fetch(URL_APP +"api/saveFournisseur", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Fournisseurs'
            })
            if (data.code === "success") {
                clearForm("formFournisseur", "CodeFournisseur")
                getAllFournisseurs(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Fournisseurs', duration: 4000}));
}
function deleteFournisseur(id_fournisseur){
    fetch(URL_APP +"api/deleteFournisseur", { method: "POST", body: id_fournisseur })
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
function getSingleFournisseur(id_fournisseur) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: URL_APP +'api/getSingleFournisseur' + id_fournisseur,
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

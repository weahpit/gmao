function getAllFournisseurs(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : 'api/getAllFournisseurs',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table>
                    <thead><tr>
                            <th>Code</th>
                            <th>Sigle</th>
                            <th>Tel / Mobile</th>
                            <th>email</th>
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
            showToast(response.msg, {title: 'Nature Equipement', type: 'error', duration: 3000})
        }
    })
}
function saveFournisseur(formData, ctrl_name, ctrl_type){
    fetch("api/saveFournisseur", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: this.name
            })
            if (data.code === "success") {
                getAllFournisseurs(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Nature Equipement', duration: 4000}));
}
function deleteFournisseur(id_fournisseur){
    fetch("api/deleteFournisseur", { method: "POST", body: id_fournisseur })
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
            url: 'api/getSingleFournisseur' + id_fournisseur,
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

function getAllMagasins(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllMagasins',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Magasin</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].magasin + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Magasins --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].magasin + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'magasins.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  Magasins}', type: 'error', duration: 4000 })
        }
    })
}
function saveMagasin(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveMagasin", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'Magasin'
            })
            if (data.code === "success") {
                clearForm("formMagasin", "magasin")
                getAllMagasins(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Magasin', duration: 4000}));
}
function deleteMagasin(id_magasin){
    fetch( URL_APP + "api/deleteMagasin", { method: "POST", body: id_magasin })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Magasin'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Magasin', duration: 4000}));
}
function getSingleMagasin(id_magasin) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleMagasin' + id_magasin,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Magasin'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Magasin', duration: 4000});
            }
        });
    });
}

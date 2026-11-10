function getAllPays(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllPays',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Pays</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].pays + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Pays --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].pays + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'pays.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  Pays}', type: 'error', duration: 4000 })
        }
    })
}
function savePays(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/savePays", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'Pays'
            })
            if (data.code === "success") {
                clearForm("formPays", "pays")
                getAllPays(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Pays', duration: 4000}));
}
function deletePays(id_pays){
    fetch( URL_APP + "api/deletePays", { method: "POST", body: id_pays })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Pays'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Pays', duration: 4000}));
}
function getSinglePays(id_pays) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSinglePays' + id_pays,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Pays'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Pays', duration: 4000});
            }
        });
    });
}

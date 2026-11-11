function getAllRubriques(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllRubriques',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Rubrique</th>
                            <th>Active</th>
                            <th>Position</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].rubrique + '</a></td>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].active + '</a></td>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].position + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Rubriques --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].rubrique + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'rubriques.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  Rubriques}', type: 'error', duration: 4000 })
        }
    })
}
function saveRubrique(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveRubrique", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'Rubrique'
            })
            if (data.code === "success") {
                clearForm("formRubrique", "rubrique")
                getAllRubriques(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Rubrique', duration: 4000}));
}
function deleteRubrique(id_rubrique){
    fetch( URL_APP + "api/deleteRubrique", { method: "POST", body: id_rubrique })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Rubrique'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Rubrique', duration: 4000}));
}
function getSingleRubrique(id_rubrique) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleRubrique' + id_rubrique,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Rubrique'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Rubrique', duration: 4000});
            }
        });
    });
}

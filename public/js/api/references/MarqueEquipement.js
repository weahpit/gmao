function getAllMarques(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllMarques',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Liste des Marques</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].marque + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Marques --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].marque + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'MarquesEquipements.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Marques', type: 'error', duration: 4000 })
        }
    })
}
function saveMarque(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveMarque", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Marque'
            })
            if (data.code === "success") {
                clearForm("formMarque", "marque")
                getAllMarques(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Marque', duration: 4000}));
}
function deleteMarque(id_nature_equipement){
    fetch( URL_APP + "api/deleteMarque", { method: "POST", body: id_nature_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Marque'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Marque', duration: 4000}));
}
function getSingleMarque(id_nature_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleMarque' + id_nature_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Marque'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Marque', duration: 4000});
            }
        });
    });
}

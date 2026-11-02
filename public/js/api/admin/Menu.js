function getAllMenus(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllMenus',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Liste des Menus</th>
                            <th>Classnames</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].menu + '</a></td>'
                    contentCtrl +='<td><span class="badge bg-warning p-2 text-dark">' + reponse.data[i].classname + '</span></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Menus --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].menu + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'menus.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Menus', type: 'error', duration: 4000 })
        }
    })
}
function saveMenu(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveMenu", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Menu'
            })
            if (data.code === "success") {
                clearForm("formMenu", "menu")
                getAllMenus(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Menu', duration: 4000}));
}
function deleteMenu(id_nature_equipement){
    fetch( URL_APP + "api/deleteMenu", { method: "POST", body: id_nature_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Menu'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Menu', duration: 4000}));
}
function getSingleMenu(id_nature_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleMenu' + id_nature_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Menu'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Menu', duration: 4000});
            }
        });
    });
}
function getParentMenu(ctrl_name){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getParentMenu',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
                contentCtrl += '<option value="0">-- parents Menus --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].menu + '</option>'
                }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des parents Menus', type: 'error', duration: 4000 })
        }
    })
}

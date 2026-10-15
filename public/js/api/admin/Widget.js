function getAllWidgets(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllWidgets',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Widget</th>
                            <th class="text-center fw-bold">Largeur</th>
                            <th class="text-center fw-bold">Groupes concernés</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].widget + '</a></td>'
                    contentCtrl +='<td class="text-center fw-bold">' + reponse.data[i].width + '</td>'
                    contentCtrl +='<td class="text-center fw-bold">';
                    for (var j=0;j< reponse.data[i].groupes.length;j++){
                        contentCtrl +='<span class="badge me-2 p-1" style="background: #0d2456;">' + reponse.data[i].groupes[j].groupe + '</span>'
                    }
                    contentCtrl +='</td>'
                    contentCtrl +='</tr>'
                }

                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Widgets --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].widget + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'widget.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Widgets', type: 'error', duration: 4000 })
        }
    })
}

function getAllWidgetsByService(ctrl_name, ctrl_type, id_service){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllWidgetsByService/' + id_service,
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Widget</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].widget + '</a></td>'
                    contentCtrl +='<tr>'
                }

                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Widgets --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].widget + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'widget.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Widgets', type: 'error', duration: 4000 })
        }
    })
}

function saveWidget(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveWidget", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Widget'
            })
            if (data.code === "success") {
                clearForm("formWidget", "widget")
                document.getElementById("groupe").value = null;
                getAllWidgets(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Widget', duration: 4000}));
}
function deleteWidget(id_nature_equipement){
    fetch( URL_APP + "api/deleteWidget", { method: "POST", body: id_nature_equipement })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Widget'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Widget', duration: 4000}));
}
function getSingleWidget(id_nature_equipement) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleWidget' + id_nature_equipement,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Widget'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Widget', duration: 4000});
            }
        });
    });
}
function getParentWidget(ctrl_name){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getParentWidget',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
                contentCtrl += '<option value="0">-- parents Widgets --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].widget + '</option>'
                }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des parents Widgets', type: 'error', duration: 4000 })
        }
    })
}

function getAllLots(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllLots',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){

                if (reponse.data.length > 0){
                    for(var i = 0; i< reponse.data.length; i++){
                        if (reponse.data[i].qualite === "NC"){
                            contentCtrl += '<tr class="tr p-2 fw-light"  id="' + reponse.data[i].id + '"  onclick="selected_row()" style="font-size: 12px;font-family: Arial;cursor: pointer;font-weight: lighter;background: linear-gradient(#fedcdc, #fa9b9b);">'
                        } else {
                            contentCtrl += '<tr class="tr p-2 fw-light"  id="' + reponse.data[i].id + '"  onclick="selected_row()" style="font-size: 12px;font-family: Arial;cursor: pointer;font-weight: lighter">'
                        }

                        contentCtrl += '<td class="fw-bold">' + reponse.data[i].numero_lot + '</td>'
                        contentCtrl += '<td class="fw-light">' + reponse.data[i].proprio + '</td>'
                        contentCtrl += '<td class="fw-light">' + reponse.data[i].produit + '</td>'
                        contentCtrl += '<td class="fw-light">' + reponse.data[i].date_production + '</td>'
                        contentCtrl += '<td class="fw-light">' + reponse.data[i].pn_th + '</td>'
                        contentCtrl += '<td class="fw-light">' + reponse.data[i].etat + '</td>'
                        contentCtrl += '<td class="fw-light">' + reponse.data[i].situation + '</td>'
                        contentCtrl += '<td class="fw-light">' + reponse.data[i].ot + '</td>'
                        contentCtrl += '</tr>'
                    }

                } else {
                    contentCtrl += '<h4 class="fw-light text-danger">Aucun lot disponible !</h4>'
                }
            } else {
                contentCtrl += '<option value="0">-- Lots --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].lot + '</option>'
                }
            }

            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'lots.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  Lots}', type: 'error', duration: 4000 })
        }
    })
}


function saveLot(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveLot", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'Lot'
            })
            if (data.code === "success") {
                reinitialise_form()
                getAllLots(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Lot', duration: 4000}));
}
function deleteLot(id_lot){
    fetch( URL_APP + "api/deleteLot", { method: "POST", body: id_lot })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Lot'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Lot', duration: 4000}));
}
function getSingleLot(formData) {  // api/getSingleUser
    return new Promise((resolve, reject) => {
        fetch( URL_APP + "api/getSingleLot", { method: "POST", body: formData })
            .then(res => res.json())
            .then(data => {
                resolve(data); // renvoie l'objet au .then()
            })
            .catch(err => showToast("❌ Erreur : " + err, {title: 'Widget', duration: 4000}));

    });
}

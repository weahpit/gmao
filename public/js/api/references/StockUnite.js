function getAllStockUnites(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllStockUnites',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Libellé</th>
                            <th>Symbole</th>
                            <th>Position</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].libelle + '</a></td>'
                    contentCtrl +='<td><a href="#" class="text-primary fw-bold">' + reponse.data[i].symbole + '</a></td>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].position + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- StockUnites --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].libelle + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'stock_unites.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  Unites}', type: 'error', duration: 4000 })
        }
    })
}
function saveStockUnite(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveStockUnite", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'Unite'
            })
            if (data.code === "success") {
                clearForm("formStockUnite", "stock_unite")
                getAllStockUnites(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'StockUnite', duration: 4000}));
}
function deleteStockUnite(id_stock_unite){
    fetch( URL_APP + "api/deleteStockUnite", { method: "POST", body: id_stock_unite })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Unite'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Unite', duration: 4000}));
}
function getSingleStockUnite(id_stock_unite) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleStockUnite' + id_stock_unite,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Unite'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Unite', duration: 4000});
            }
        });
    });
}

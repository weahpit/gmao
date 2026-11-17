function getAllProduits(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllProduits',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Produit</th>
                            <th>Proprietaire</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].produit + '</a></td>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].proprietaire + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Produits --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].produit + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'produits.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  Produits}', type: 'error', duration: 4000 })
        }
    })
}

function getAllProduitsByProprietaire(ctrl_name, ctrl_type, id_produit){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllProduitsByProprietaire/' + id_produit,
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Produit</th>
                            <th>Proprietaire</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].produit + '</a></td>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].proprietaire + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Produits --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].produit + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'produits.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  Produits}', type: 'error', duration: 4000 })
        }
    })
}
function saveProduit(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveProduit", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'Produit'
            })
            if (data.code === "success") {
                clearForm("formProduit", "produit")
                getAllProduits(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Produit', duration: 4000}));
}
function deleteProduit(id_produit){
    fetch( URL_APP + "api/deleteProduit", { method: "POST", body: id_produit })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Produit'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Produit', duration: 4000}));
}
function getSingleProduit(formData) {  // api/getSingleUser
    return new Promise((resolve, reject) => {
        fetch( URL_APP + "api/getSingleProduit", { method: "POST", body: formData })
            .then(res => res.json())
            .then(data => {
                resolve(data); // renvoie l'objet au .then()
            })
            .catch(err => showToast("❌ Erreur : " + err, {title: 'Widget', duration: 4000}));

    });
}


function getAllDataInventaire(ctrl_name, formData){
    fetch( URL_APP + "api/getAllData", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 500,
                title: 'Inventaire Stock'
            })
            if (data.code === "success") {
                let donnees = data.data;
                let html = `
                    <div class="d-flex mb-3 p-1" style="border: 1px solid lightgrey;background: #e8e7e7;font-size: 13px;font-weight: bold;">
                        <label style="width: 150px:"><u>Légende</u></label>
                        <div class="ms-5 me-2 tr-warning" style="width: 20px;height: 20px;border: #dcc3b3 1px solid;"></div>Alerte !

                        <div class="ms-4 me-2 tr-danger" style="width: 20px;height: 20px;border: #dcc3b3 1px solid;"></div>En rupture
                    </div>
                    <table class="w-100" id="tblInventaires">
                            <thead><tr>
                                <th>Equipements</th>
                                <th class="text-center">Stock Actuel</th>
                                <th class="text-center">Entrées</th>
                                <th class="text-center">Sorties</th>
                                <th class="text-center">Seuil</th>
                            </tr></thead>
                            <tbody>
                `

                for(var i=0; i< donnees.length; i++){
                    if (donnees[i].actuel <= donnees[i].seuil && donnees[i].actuel > 0) {
                        html += '<tr class="tr-warning tr" style="cursor: default;" id="' + donnees[i].id + '">';
                    } else if (donnees[i].actuel === 0) {
                        html += '<tr class="tr-danger tr" style="cursor: default;" id="' + donnees[i].id + '">';
                    } else {
                        html += '<tr class="tr tr-success" style="cursor: default;" id="' + donnees[i].id + '">';
                    }


                    html += '<td class="text-dark fw-bold">' + donnees[i].nom  + '</u></td>'
                    html += '<td class="text-center">' + donnees[i].actuel  + '</a></td>'
                    html += '<td class="text-center">' + donnees[i].entree  + '</a></td>'
                    html += '<td class="text-center">' + donnees[i].sortie  + '</a></td>'
                    html += '<td class="text-center">' + donnees[i].seuil  + '</a></td>'
                    html +='</tr>'
                }

                html +='</tbody></table>';
                ctrl_name.innerHTML = html;
                enhanceTable("#tblInventaires",{ pageSize: 25, filename: 'Inventaires.csv' })
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Marque', duration: 4000}));
}

function getEquipementDetails(ctrl_name, formData){
    fetch( URL_APP + "api/getEquipementDetails", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 500,
                title: 'Détails Inventaire'
            })
            if (data.code === "success") {
                let donnees = data.data;
                let html = `

                    <table class="w-75" id="tblInventaires">
                            <thead><tr  style="background: linear-gradient(65deg, #c9c9c9, #afafaf)">
                                <th class="text-center">date</th>
                                <th class="text-center">entrée</th>
                                <th class="text-center">sortie</th>
                                <th class="text-center">service</th>
                            </tr></thead>
                            <tbody>
                `

                for(var i=0; i< donnees.length; i++){
                    html += '<tr style="font-size: 16px;">';
                    html += '<td class="text-center" style="border: 0; border-right: 1px solid lightgrey;font-size: 16px;">' + donnees[i].date_mvt  + '</u></td>'
                    html += '<td class="text-center" style="border: 0; border-right: 1px solid lightgrey;font-size: 16px;">' + donnees[i].entree  + '</u></td>'
                    html += '<td class="text-center" style="border: 0; border-right: 1px solid lightgrey;font-size: 16px;">' + donnees[i].sortie  + '</a></td>'
                    html += '<td class="text-center" style="border: 0; border-right: 1px solid lightgrey;font-size: 16px;">' + donnees[i].service  + '</a></td>'
                    html +='</tr>'
                }

                html +='</tbody></table>';
                ctrl_name.innerHTML = html;
                document.getElementById("lblDetails").innerHTML = `
                    <div class="d-flex"><img class="me-2" src="data/equipements/${data.photo}" height="48" alt="" style="border: lightgrey 2px solid;">
                    <h6 class="ms-2 mt-2">${data.equipement}</h6></div>
                    <h6 class="mt-1 text-center" style="margin-left: 65px;background: #d6eee4;width:150px;border-radius: 25px;border:2px solid lightgrey;">Quantité : <span class="text-danger fw-bold">${data.qte}</span></h6>
                `
                /*enhanceTable("#tblInventaires",{ pageSize: 25, filename: 'Inventaires.csv' })*/
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Marque', duration: 4000}));
}

function printDetails_old(formData){
    fetch( URL_APP + "api/getEquipementDetails/print", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 500,
                title: 'Détails Inventaire'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Marque', duration: 4000}));
}
function printDetails(dateInventaire, critere, idEquipement){
    $.ajax({
        url: '/api/getEquipementDetails/print',
        type: 'POST',
        data: {
            date_inventaire: dateInventaire,
            critere: critere,
            id_equipement: idEquipement
        },
        xhrFields: { responseType: 'blob' },   // <-- indispensable
        success: function (blob) {
            const url = window.URL.createObjectURL(blob);
            window.open(url, '_blank');        // ouvre le PDF dans un nouvel onglet
            // ou pour forcer le téléchargement :
            // const a = document.createElement('a');
            // a.href = url; a.download = 'mouvements.pdf'; a.click();
            setTimeout(() => window.URL.revokeObjectURL(url), 10000);
        },
        error: function (xhr) {
            console.error('Erreur génération PDF', xhr);
        }
    });
}

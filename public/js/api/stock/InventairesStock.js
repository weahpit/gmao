
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
                    <div class="d-flex mb-3 p-1" style="border: 2px solid black;background: #ffffff">
                        <label style="width: 150px:"><u>Légende</u></label>
                        <div class="ms-5 me-2 tr-warning" style="width: 20px;height: 20px;border: #4a2d1a 1px solid;"></div>Alerte !

                        <div class="ms-2 me-2 tr-danger" style="width: 20px;height: 20px;border: #4a2d1a 1px solid;"></div>En rupture
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
                        html += '<tr class="tr-warning">';
                    } else if (donnees[i].actuel === 0) {
                        html += '<tr class="tr-danger">';
                    } else {
                        html += '<tr>';
                    }


                    html += '<td ><a class="text-dark fw-bold" href="#"><u>' + donnees[i].nom  + '</u></a></td>'
                    html += '<td class="text-center">' + donnees[i].actuel  + '</a></td>'
                    html += '<td class="text-center">' + donnees[i].entree  + '</a></td>'
                    html += '<td class="text-center">' + donnees[i].sortie  + '</a></td>'
                    html += '<td class="text-center">' + donnees[i].seuil  + '</a></td>'
                    html +='</tr>'
                }

                html +='</tbody></table>';
                ctrl_name.innerHTML = html;
                enhanceTable("#tblInventaires",{ pageSize: 10, filename: 'Inventaires.csv' })
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Marque', duration: 4000}));
}

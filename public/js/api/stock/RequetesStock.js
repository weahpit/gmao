function getMouvementsByDate(ctrl_name, formData){
    fetch( URL_APP + "api/getMouvementsByDate", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 500,
                title: 'Opérations Stock'
            })
            if (data.code === "success") {
                let donnees = data.data;
                let html = `
                    <table class="w-100" id="tblMvts">
                            <thead><tr>
                                <th>Equipements</th>
                                <th class="text-center">Type Mouvement</th>
                                <th class="text-center">Quantité</th>
                                <th class="text-center">Service demandeur</th>
                                <th class="text-center">Emplacement</th>
                                <th class="text-center">N° Bon</th>
                                <th class="text-center">Pièce jointe</th>
                                <th class="text-center">Stock Avant</th>
                                <th class="text-center">Stock Après</th>
                            </tr></thead>
                            <tbody>
                `

                for(var i=0; i< donnees.length; i++){

                  html += '<tr class="tr" style="cursor: default;" id="' + donnees[i].id + '">';
                  html += '<td class="text-center" style="font-size: 16px;"><img class="me-2" src="data/equipemenrs/' + donnees[i].photo + '"  alt="" style="border-radius: 999px;" height="32">' + donnees[i].eqType  + '</a></td>'
                        if (donnees[i].typeMvt === "1"){
                            html += '<td class="text-dark fw-light" style="font-size: 16px;" >ENTREE</td>'
                        } else {
                            html += '<td class="text-dark fw-light" style="font-size: 16px;" >SORTIE</td>'
                        }
                    html += '<td class="text-center" style="font-size: 16px;">' + donnees[i].qte  + '</a></td>'
                    html += '<td class="text-center" style="font-size: 16px;">' + donnees[i].service  + '</a></td>'
                    html += '<td class="text-center" style="font-size: 16px;">' + donnees[i].emplacement  + ' [' + donnees[i].emplacement2  + ']</a></td>'
                    html += '<td class="text-center" style="font-size: 16px;">' + donnees[i].numero_bon  + '</a></td>'
                    if (donnees[i].bl !== ""|| donnees[i].bl !== "null"){ html +=
                        `<td><a href="data/stock/bons/bl/${donnees[i].bl}" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-paperclip preview-icon"><path d="m16 6-8.414 8.586a2 2 0 0 0 2.829 2.829l8.414-8.586a4 4 0 1 0-5.657-5.657l-8.379 8.551a6 6 0 1 0 8.485 8.485l8.379-8.551"/></svg>
                        </a></td>
                        `
                    }
                    html += '<td class="text-center fw-bold" style="font-size: 16px;">' + donnees[i].stock_avant  + '</a></td>'
                    html += '<td class="text-center fw-bold" style="font-size: 16px;">' + donnees[i].stock_apres  + '</a></td>'
                    html +='</tr>'
                }

                html +='</tbody></table>';
                ctrl_name.innerHTML = html;
                enhanceTable("#tblMvts",{ pageSize: 15, filename: 'Inventaires.csv' })
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Marque', duration: 4000}));
}

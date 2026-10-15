function getAllAlertesPdr(render_type){
    return new Promise((resolve, reject) => {
        $.ajax({
            url : URL_APP + 'api/stockAlertesPdr',
            type : 'POST',
            success: function (response){
                let reponse = response;
                let contentCtrl = ''
                if (render_type === "1"){
                    contentCtrl +=`
                    <table class="table table-hover">
                        <thead><tr>
                                <th></th>
                                <th>Equipement</th>
                                <th>Qté</th>
                                <th>Seuil</th>
                        </tr></thead>
                        <tbody>
                    `

                    for (var i=0;i < reponse.data.length;i++){
                        contentCtrl +='<tr style="font-size: 12px;">'
                        contentCtrl +='<td style="background: #f9d9d9"><img src="data/equipements/' + reponse.data[i].photo + '" alt="" height="32"></td>'
                        if (reponse.data[i].libelle.length > 30){
                            contentCtrl +='<td style="background: #f9d9d9">' + reponse.data[i].libelle.slice(0,30) + '...</td>'
                        } else {
                            contentCtrl +='<td style="background: #f9d9d9">' + reponse.data[i].libelle + '</td>'
                        }

                        contentCtrl +='<td style="background: #f9d9d9"><a href="#">' + reponse.data[i].qte + '</a></td>'
                        contentCtrl +='<td style="background: #f9d9d9"><a href="#">' + reponse.data[i].seuil + '</a></td>'
                        contentCtrl +='</tr>'
                    }

                    contentCtrl +='</tbody></table>';
                } else {
                    contentCtrl += '<option value="0">-- Directions --</option>'
                    for (var i=0;i < reponse.data.length;i++) {
                        contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].direction + '</option>'
                    }
                }
                resolve(contentCtrl);
            },
            error : function (response){
                reject(response.msg);
            }
        });
    });
}
function getAllMouvements(render_type){
    return new Promise((resolve, reject) => {
        $.ajax({
            url : URL_APP + 'api/DerniersMouvementsStocks',
            type : 'POST',
            success: function (response){
                let reponse = response;
                let contentCtrl = ''
                if (render_type === "1"){
                    contentCtrl +=`
                <table class="table table-hover">
                    <thead style="position: sticky;top: 0;background: #f8f9fa;z-index: 2;"><tr>
                            <th></th>
                            <th>Equipement</th>
                            <th>Opération</th>
                            <th>Qté</th>
                            <th>Date</th>
                    </tr></thead>
                    <tbody>
                `
                    for (var i=0;i < reponse.data.length;i++){
                        contentCtrl +='<tr style="font-size: 12px;">'
                        contentCtrl +='<td><img src="data/equipements/' + reponse.data[i].photo + '" alt="" height="32"></td>'
                        if (reponse.data[i].equipement.length > 50){
                            contentCtrl +='<td>' + reponse.data[i].equipement.slice(0,50) + '...</td>'
                        } else {
                            contentCtrl +='<td>' + reponse.data[i].equipement + '</td>'
                        }
                        if (reponse.data[i].type === "1"){ // Entrée
                            contentCtrl +='<td class="text-center"><span class="fw-bold p-1" style="border-radius: 4px;background: linear-gradient(45deg, #78a1f9, #2563EB);color:white;box-shadow: #555555 1px 1px;">E</span></td>'
                        } else {
                            contentCtrl +='<td class="text-center"><span class="fw-bold p-1" style="border-radius: 4px;background: linear-gradient(45deg, #fd5e5e, #ff001f);color:white;box-shadow: #555555 1px 1px;">S</span></td>'
                        }

                        contentCtrl +='<td><a href="#">' + reponse.data[i].nb + '</a></td>'
                        contentCtrl +='<td><a href="#">' + reponse.data[i].date_operation + '</a></td>'
                        contentCtrl +='</tr>'
                    }
                    contentCtrl +='</tbody></table>';
                } else {
                    contentCtrl += '<option value="0">-- Mouvements --</option>'
                    for (var i=0;i < reponse.data.length;i++) {
                        contentCtrl += '<option value="' + reponse.data[i].id + '"  style="text-transform: uppercase;">' + reponse.data[i].direction + '</option>'
                    }
                }
                resolve(contentCtrl);
            },
            error : function (response){
                reject(response.msg);
            }
        });
    });
}


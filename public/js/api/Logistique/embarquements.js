const serveur = 'https://127.0.0.1:8000'
function getListDossier(ctl)  {
    let reponse = "";
    let contenu = "";
    contenu += '<table class="table table-hover" id="tbl_ot" style="width:100%;">'
    contenu += '<thead  class="sticky-top fw-bold" style="background-color: #e0dede;">'
    contenu += '<tr>'
    contenu += '<th class="text-center" style="border-right: 1px lightgray solid;font-weight: bold;border-bottom: black 2px solid;">N° OT</th>'
    contenu += '<th  class="" style="border-right: 1px lightgray solid;font-weight: bold;border-bottom: black 2px solid;">Progression</th>'
    contenu += '<th  class="" style="black;border-right: 1px lightgray solid;font-weight: bold;border-bottom: black 2px solid;">Destination</th>'
    contenu += '<th  class=" text-center" style="font-weight: bold;border-bottom: black 2px solid;">Tonnage</th>'
    contenu += '</tr>'
    contenu += '</thead>'
    contenu += '<tbody class="bg-white contenu_body">'
    $.ajax({
        url : serveur + '/api/getListOT',
        type : 'POST',
        success: function (response){

            reponse = JSON.parse(response)
            if (reponse.length > 0){
                for(var i = 0; i< reponse.length; i++){
                    contenu += '<tr class="trow w-100 p-0"  id="' + reponse[i].id_dossier + '" style="cursor: pointer;padding: 0;font-size: 11px;border-bottom: 1px #3b82f6 dashed">'
                    contenu += '<td style="border-right: 1px lightgray solid;"><a class="text-dark a_idDossier font-weight-bold" href="#">' + reponse[i].ot + '</a></td>'
                    contenu += '<td><div class="progress ">'
                    if (reponse[i].suivi <= 10){
                        contenu += '<div class="progress-bar progress-bar-striped bg-danger" role="progressbar" style="width: ' + reponse[i].suivi + '%;" aria-valuenow="' + reponse[i].suivi + '" aria-valuemin="0" aria-valuemax="100">' + reponse[i].suivi + '%</div></div></td>'
                    } else if (reponse[i].suivi > 10 && reponse[i].suivi <=33){
                        contenu += '<div class="progress-bar progress-bar-striped bg-warning" role="progressbar" style="width: ' + reponse[i].suivi + '%;" aria-valuenow="' + reponse[i].suivi + '" aria-valuemin="0" aria-valuemax="100">' + reponse[i].suivi + '%</div></div></td>'
                    } else if (reponse[i].suivi > 33 && reponse[i].suivi <=66){
                        contenu += '<div class="progress-bar progress-bar-striped bg-info" role="progressbar" style="width: ' + reponse[i].suivi + '%;" aria-valuenow="' + reponse[i].suivi + '" aria-valuemin="0" aria-valuemax="100">' + reponse[i].suivi + '%</div></div></td>'
                    }else if (reponse[i].suivi > 66){
                        contenu += '<div class="progress-bar progress-bar-striped bg-success" role="progressbar" style="width: ' + reponse[i].suivi + '%;" aria-valuenow="' + reponse[i].suivi + '" aria-valuemin="0" aria-valuemax="100">' + reponse[i].suivi + '%</div></div></td>'
                    }
                    contenu += '<td style="border-right: 1px lightgray solid;">' + reponse[i].destination + '</td>'
                    contenu += '<td><span class="fw-bold text-danger float-end">' + reponse[i].tonnage + '</span></td>'
                    contenu += '</tr>'
                }
            } else {
                contenu += '<tr><td><h5 class="text-danger">Aucun dossier</h5></td><td></td><td></td></tr>'
            }
            contenu += '</tbody>'
            contenu += '</table>'
            ctl.innerHTML = contenu;

            /*let table = new DataTable('#tbl_ot', {
                pageLength: 10,
                paging: false,
                language: {
                    processing:     "Traitement en cours...",
                    search:         "Rechercher&nbsp;:",
                    lengthMenu:    "Afficher _MENU_ &eacute;l&eacute;ments",
                    info:           "Affichage de l'&eacute;lement _START_ &agrave; _END_ sur _TOTAL_ &eacute;l&eacute;ments",
                    infoEmpty:      "Affichage de l'&eacute;lement 0 &agrave; 0 sur 0 &eacute;l&eacute;ments",
                    infoFiltered:   "(filtr&eacute; de _MAX_ &eacute;l&eacute;ments au total)",
                    infoPostFix:    "",
                    loadingRecords: "Chargement en cours...",
                    zeroRecords:    "Aucun &eacute;l&eacute;ment &agrave; afficher",
                    emptyTable:     "Aucune donnée disponible dans le tableau",
                    paginate: {
                        first:      "Premier",
                        previous:   "Pr&eacute;c&eacute;dent",
                        next:       "Suivant",
                        last:       "Dernier"
                    },
                    aria: {
                        sortAscending:  ": activer pour trier la colonne par ordre croissant",
                        sortDescending: ": activer pour trier la colonne par ordre décroissant"
                    }
                }
            });*/
        },
        error : function (erreur){
            reponse = "Erreur !";
            contenu += '</tbody>'
            contenu += '</table>'
            ctl.innerHTML = contenu;
        }
    })

    //alert(contenu_body)
}

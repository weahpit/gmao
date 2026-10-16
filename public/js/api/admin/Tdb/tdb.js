function getTdbByGroupe(id_groupe, ctrl_name){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllWidgetsByGroupe/' + id_groupe,
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<div class="col-md-' + reponse.data[i].width+ ' p-2 bg-white m-1" id="div_' + reponse.data[i].reference + '" style="border: 1px solid #d9d9d9;box-shadow: 2px 2px lightgrey">'
                    contentCtrl +='<h5 class="p-1 ps-2 fw-bold" style="border-radius: 4px;" id="h5_' + reponse.data[i].reference + '">' + reponse.data[i].widget+ '</h5>'
                    contentCtrl +='<div class="p-2" style="border-radius: 25px;background: linear-gradient(98deg, #efefef, #f8f2ee);max-height: 250px;min-height: 150px;overflow-y: scroll;" id="value_' + reponse.data[i].id + '"></div>'
                    contentCtrl +='</div>'
                }
            ctrl_name.innerHTML = contentCtrl
            setTimeout(()=>{
                for (var i=0; i < reponse.data.length; i++){
                    let id_h5 = "h5_" + reponse.data[i].reference;
                    let div = "value_" + reponse.data[i].id;
                    let app_js = reponse.data[i].app_js; // ex: "loadAlertesChart"
                    let render_type = reponse.data[i].render_type;

                    document.getElementById(id_h5).style.backgroundColor = couleurAleatoireElegante();

                    let fn = window[app_js];
                    if (typeof fn === "function") {
                        executer(fn, render_type)
                            .then(result => {
                                if (typeof result === "string") {
                                    // Cas TABLE
                                    document.getElementById(div).innerHTML = result;
                                } else if (result.chartOptions) {
                                    // Cas CHART
                                    document.getElementById(div).innerHTML = ""; // reset
                                    let chart = new ApexCharts(document.getElementById(div), result.chartOptions);
                                    chart.render();
                                }
                            })
                            .catch(err => {
                                showToast(err, {title: 'Erreur sur Chargement', type: 'error', duration: 4000 });
                            });
                    } else {
                        console.error("La fonction " + app_js + " n’existe pas ou n’est pas définie.");
                    }
                }
            }, 500);



        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Widgets', type: 'error', duration: 4000 })
        }
    })
}
function getDisplayedTdbByGroupe( ctrl_name){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getDisplayedTdbByGroupe',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            contentCtrl +='<ul class="gadget-list">'
            for (var i=0;i < reponse.data.length;i++){
                contentCtrl +='<li class="mb-3" style="/*border-bottom: 1px solid #e8e7e7;*/">'
                if (i === 0){
                    contentCtrl +=' <a class="btn mb-1 ps-2"  href="#"></a>'
                } else {
                    contentCtrl +=' <a class="btn btn-up p-1 mb-1 fw-bold" id="'+ reponse.data[i].id + '" href="#" style="border: 1px solid lightgrey;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-move-up preview-icon"><path d="M8 6L12 2L16 6"/><path d="M12 2V22"/></svg></a>'
                }

                if (i === reponse.data.length - 1){
                    contentCtrl +=' <a class="btn mb-1"  href="#"></a>'
                } else {
                    contentCtrl +=' <a class="ms-2 btn btn-down p-1 mb-1 fw-bold" id="'+ reponse.data[i].id + '" href="#" style="border: 1px solid lightgrey;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down preview-icon"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg></a>'
                }

                contentCtrl +=' <span class="label fw-bold p-1 ps-2 pe-2" style="background: #2563EB;color: white;border-radius: 4px;font-size: 13px;">'+ reponse.data[i].ordre + '</span>'
                contentCtrl +=' <span class="label fw-bold">'+ reponse.data[i].widget + '</span>'
                contentCtrl +='</li>'
            }
            contentCtrl +='</ul>'
            ctrl_name.innerHTML = contentCtrl;
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Widgets', type: 'error', duration: 4000 })
        }
    })
}

function setOrderedDown(id_groupe, ctrl_name){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/setOrderedDown/' + id_groupe,
        type : 'POST',
        success: function (response){
            showToast(response.msg, {title: 'Ordonnancement des gadgets', type: response.code, duration: 1500 })
            getDisplayedTdbByGroupe( ctrl_name)
            getTdbByGroupe(id_groupe, document.querySelector(".div_dashboard"))
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Widgets', type: 'error', duration: 4000 })
        }
    })
}
function setOrderedUp(id_groupe, ctrl_name){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/setOrderedUp/' + id_groupe,
        type : 'POST',
        success: function (response){
            showToast(response.msg, {title: 'Ordonnancement des gadgets', type: response.code, duration: 1500 })
            getDisplayedTdbByGroupe( ctrl_name)
            getTdbByGroupe(id_groupe, document.querySelector(".div_dashboard"))
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des Widgets', type: 'error', duration: 4000 })
        }
    })
}

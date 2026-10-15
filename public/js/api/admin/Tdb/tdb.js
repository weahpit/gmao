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

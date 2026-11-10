function getAllCvsDeblocage(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllCvsDeblocage',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Contrat</th>
                            <th>Tonnage</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].contrat + '</a></td>'
                    contentCtrl +='<td class="fw-bold text-danger">' + reponse.data[i].tonnage + '</td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- CVs de Déblocage --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].contrat + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'cv_deblocage.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  CVs de Déblocage }', type: 'error', duration: 4000 })
        }
    })
}

function getAllCvsDeblocageByProprio(ctrl_name, ctrl_type, id_proprio){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllCvsDeblocageByProprio/' + id_proprio,
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Contrat</th>
                            <th>Tonnage</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].contrat + '</a></td>'
                    contentCtrl +='<td class="fw-bold text-danger">' + reponse.data[i].tonnage + '</td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- CVs de Déblocage --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].contrat + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'cv_deblocage.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  CVs de Déblocage }', type: 'error', duration: 4000 })
        }
    })
}


function saveCvDeblocage(formData, ctrl_name, ctrl_type, id_proprio){
    fetch( URL_APP + "api/saveCvDeblocage", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'CV de Déblocage'
            })
            if (data.code === "success") {
                clearForm("formCvDeblocage", "cv_deblocage")
                getAllCvsDeblocageByProprio(ctrl_name, ctrl_type, id_proprio)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Proprietaire', duration: 4000}));
}

function getSingleCvDeblocage(id_cv) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleCvDeblocage' + id_cv,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'CV de Déblocage'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'CV de Déblocage', duration: 4000});
            }
        });
    });
}


function getAllCvsExecution(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllCvsExecution',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>N° CV</th>
                            <th>CV Deblocage</th>
                            <th>Tonnage</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].contrat + '</a></td>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].cv_deb + '</a></td>'
                    contentCtrl +='<td class="fw-bold text-danger">' + reponse.data[i].tonnage + '</td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- CVs d\'exécution --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].contrat + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'cv_execution.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  CVs d\'exécution }', type: 'error', duration: 4000 })
        }
    })
}

function getAllCvsExecutionByDeb(ctrl_name, ctrl_type, cv_deb){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getAllCvsExecutionByDeb/' + cv_deb,
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>N° CV</th>
                            <th>CV Deblocage</th>
                            <th>Tonnage</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].contrat + '</a></td>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].cv_deb + '</a></td>'
                    contentCtrl +='<td class="fw-bold text-danger">' + reponse.data[i].tonnage + '</td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- CVs d\'exécution --</option>'
                if (reponse.data.length > 0){
                    for (var i=0;i < reponse.data.length;i++) {
                        contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].contrat + '</option>'
                    }
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'cv_execution.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des  CVs d\'exécution }', type: 'error', duration: 4000 })
        }
    })
}


function saveCvExecution(formData, ctrl_name, ctrl_type, cv_deb){
    fetch( URL_APP + "api/saveCvExecution", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'CV Execution'
            })
            if (data.code === "success") {
                clearForm("formCvExecution", "cv_execution")
                getAllCvsExecutionByDeb(ctrl_name, ctrl_type, cv_deb)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'CV Exécution', duration: 4000}));
}

function getSingleCvExecution(id_cv) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url:  URL_APP + 'api/getSingleCvExecution' + id_cv,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'CV de Déblocage'
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'CV \'exécution', duration: 4000});
            }
        });
    });
}

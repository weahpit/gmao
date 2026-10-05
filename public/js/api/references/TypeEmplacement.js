function getAllTypeEmplacements(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url : 'api/getAllTypeEmplacements',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Type Emplacement</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].type_emplacement + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Type Zones Exploitation --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].type_emplacement + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'TypesZones.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Type Zone Exploitation ', type: 'error', duration: 3000})
        }
    })
}
function saveTypeEmplacement (formData, ctrl_name, ctrl_type){
    fetch("api/saveTypeEmplacement ", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Type Zone Exploitation '
            })
            if (data.code === "success") {
                clearForm("formTypeEmplacement", "type_emplacement")
                getAllTypeEmplacements(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Type Zone Exploitation ', duration: 4000}));
}

function deleteTypeEmplacement (id_type_emplacement ){
    fetch("api/deleteTypeEmplacement ", { method: "POST", body: id_type_emplacement  })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'Type Zone Exploitation '
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Type Zone Exploitation ', duration: 4000}));
}
function getSingleTypeEmplacement (id_type_emplacement ) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'api/getSingleTypeEmplacement ' + id_type_emplacement ,
            type: 'POST',
            success: function(response) {
                if (response.code === "success") {
                    resolve(response); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 4000,
                        title: 'Type Zone Exploitation '
                    })
                }
            },
            error: function(err) {
                showToast("❌ Erreur : " + err, {title: 'Type Zone Exploitation ', duration: 4000});
            }
        });
    });
}

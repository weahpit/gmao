
/*function getAllEquipementTypes(ctrl_name, ctrl_type, pageSize = 8) {
    $.ajax({
        url: URL_APP + 'api/getAllEquipementTypes',
        type: 'POST',
        success: function (response) {
            let reponse = JSON.parse(response);
            const data = reponse.data || [];

            if (ctrl_type === 1) {
                // --- État local ---
                let currentPage = 1;
                let searchTerm = '';
                let filteredData = data.slice();

                // On construit UNE seule fois la structure : barre de recherche + zone de résultats
                ctrl_name.innerHTML = `
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text"><img src="icons/SVG/search.svg" alt=""></span>
                        <input type="text" class="input  input-search w-25" style="background: #eaf9f3;" id="searchEquipement"
                               placeholder="Rechercher un équipement (code, nom, catégorie, famille)...">
                    </div>
                    <div id="equipementResult"></div>
                `;

                const $search = $(ctrl_name).find('#searchEquipement');
                const $result = $(ctrl_name).find('#equipementResult');

                // Applique le filtre de recherche
                function applyFilter() {
                    const term = searchTerm.trim().toLowerCase();
                    if (!term) {
                        filteredData = data.slice();
                    } else {
                        filteredData = data.filter(function (item) {
                            return [item.code, item.nom, item.categorie, item.famille]
                                .some(function (champ) {
                                    return (champ || '').toString().toLowerCase().indexOf(term) !== -1;
                                });
                        });
                    }
                    currentPage = 1;
                }

                // Rendu UNIQUEMENT du tableau + pagination (pas la barre de recherche)
                function renderResult() {
                    const totalPages = Math.max(1, Math.ceil(filteredData.length / pageSize));
                    currentPage = Math.min(Math.max(1, currentPage), totalPages);

                    const start = (currentPage - 1) * pageSize;
                    const pageData = filteredData.slice(start, start + pageSize);

                    let html = `
                        <table class="table table-hover">
                            <thead><tr>
                                <th></th>
                                <th>Code</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Famille</th>
                            </tr></thead>
                            <tbody>
                    `;

                    if (pageData.length === 0) {
                        html += '<tr><td colspan="5" class="text-center text-muted">Aucun équipement trouvé</td></tr>';
                    }

                    for (let i = 0; i < pageData.length; i++) {
                        const item = pageData[i];
                        const nom = item.nom || '';
                        html += '<tr style="font-size: 14px;">';
                        if (item.photo) {
                            html += '<td><img class="thumbnail" src="data/equipements/' + item.photo + '" alt="" height="48" id="' + item.photo + '"></td>';
                        } else {
                            html += '<td></td>';
                        }
                        html += '<td class="text-danger fw-bold"><a href="' + item.id + '">' + item.code + '</a></td>';
                        html += '<td class="text-dark fw-bold">' + nom.toUpperCase() + '</td>';
                        html += '<td class="text-dark fw-bold">' + item.categorie + '</td>';
                        html += '<td class="text-dark fw-bold">' + item.famille + '</td>';
                        html += '</tr>';
                    }

                    html += '</tbody></table>';
                    html += buildPagination(totalPages);

                    $result.html(html);
                    bindPaginationEvents();
                }

                function buildPagination(totalPages) {
                    let html = '';

                    if (totalPages > 1) {
                        html += '<nav aria-label="Pagination EquipementTypes">';
                        html += '<ul class="pagination pagination-sm justify-content-center mt-2">';

                        html += '<li class="page-item ' + (currentPage === 1 ? 'disabled' : '') + '">' +
                            '<a class="page-link" href="#" data-page="' + (currentPage - 1) + '">&laquo;</a></li>';

                        const windowSize = 5;
                        let startPage = Math.max(1, currentPage - Math.floor(windowSize / 2));
                        let endPage = Math.min(totalPages, startPage + windowSize - 1);
                        startPage = Math.max(1, endPage - windowSize + 1);

                        for (let p = startPage; p <= endPage; p++) {
                            html += '<li class="page-item ' + (p === currentPage ? 'active' : '') + '">' +
                                '<a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>';
                        }

                        html += '<li class="page-item ' + (currentPage === totalPages ? 'disabled' : '') + '">' +
                            '<a class="page-link" href="#" data-page="' + (currentPage + 1) + '">&raquo;</a></li>';

                        html += '</ul></nav>';
                    }

                    if (filteredData.length > 0) {
                        const from = (currentPage - 1) * pageSize + 1;
                        const to = Math.min(currentPage * pageSize, filteredData.length);
                        html += '<div class="text-center text-muted" style="font-size: 12px;">' +
                            from + ' - ' + to + ' sur ' + filteredData.length +
                            (searchTerm ? ' (filtré sur ' + data.length + ')' : '') + '</div>';
                    }

                    return html;
                }

                function bindPaginationEvents() {
                    $result.find('.page-link').off('click').on('click', function (e) {
                        e.preventDefault();
                        const page = parseInt($(this).attr('data-page'), 10);
                        if (!isNaN(page)) {
                            currentPage = page;
                            renderResult();
                        }
                    });
                }

                // La recherche ne re-rend QUE la zone de résultats -> le champ n'est jamais détruit
                $search.on('input', function () {
                    searchTerm = $(this).val();
                    applyFilter();
                    renderResult();
                });

                // Premier affichage
                renderResult();

            } else if (ctrl_type === 2){
                let contentCtrl = '<option value="0">-- EquipementTypes --</option>';
                for (let i = 0; i < data.length; i++) {
                    const nom = data[i].nom || '';
                    contentCtrl += '<option value="' + data[i].id + '" style="text-transform: uppercase;">' + nom.toUpperCase() + '</option>';
                }
                ctrl_name.innerHTML = contentCtrl;
            } else {
                let contentCtrl = '';
                for (let i = 0; i < data.length; i++) {
                    const nom = data[i].nom || '';
                    contentCtrl += '<li><a class="eq_li" href="#" id="' + data[i].id + '" style="text-transform: uppercase;">' + nom.toUpperCase() + '</a></li>';
                }
                ctrl_name.innerHTML = contentCtrl;
                if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'menus.csv' })}
            }
        },
        error: function (response) {
            showToast(response.msg, { title: 'Erreur sur Chargement des EquipementTypes', type: 'error', duration: 4000 });
        }
    });
}*/

function getAllEquipementTypes(ctrl_name, ctrl_type){
    let contentCtrl = ''
    $.ajax({
        url: URL_APP + 'api/getAllEquipementTypes',
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                            <thead><tr>
                                <th></th>
                                <th>Code</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Famille</th>
                            </tr></thead>
                            <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    const nom = reponse.data[i].nom || '';
                    contentCtrl +='<tr>'
                    if (reponse.data[i].photo) {
                        contentCtrl += '<td><img class="thumbnail" src="data/equipements/' + reponse.data[i].photo + '" alt="" height="48" id="' + reponse.data[i].photo + '" style="cursor: pointer;"></td>';
                    } else {
                        contentCtrl += '<td></td>';
                    }
                    contentCtrl +='<td><a href="#">' + reponse.data[i].code + '</a></td>'
                    contentCtrl +='<td><a href="#">' + nom.toUpperCase() + '</a></td>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].categorie + '</a></td>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].famille + '</a></td>'

                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else if (ctrl_type === 2){
                contentCtrl += '<option value="0">-- Equipements Types --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    const nom = reponse.data[i].nom || '';
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + nom.toUpperCase()+ '</option>'
                }
            }else {
                for (var i=0;i < reponse.data.length;i++) {
                    const nom = reponse.data[i].nom || '';
                    contentCtrl += '<li><a class="eq_li" href="#" id="' + reponse.data[i].id + '" style="text-transform: uppercase;">' + nom.toUpperCase() + '</a></li>'
                }
            }

            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'EquipementsTypes.csv' })}
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Type_Equipement}', type: 'error', duration: 4000 })
        }
    })
}
function getEquipementTypeByFamille(ctrl_name, ctrl_type, value){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getEquipementTypeByFamille/' + value,
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            const data = reponse.data || [];
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                            <thead><tr>
                                <th></th>
                                <th>Code</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Famille</th>
                            </tr></thead>
                            <tbody>
                `
                for (let i = 0; i < pageData.length; i++) {
                    const item = pageData[i];
                    const nom = item.nom || '';
                    html += '<tr style="font-size: 14px;">';
                    if (item.photo) {
                        html += '<td><img class="thumbnail" src="data/equipements/' + item.photo + '" alt="" height="48" id="' + item.photo + '"></td>';
                    } else {
                        html += '<td></td>';
                    }
                    html += '<td class="text-danger fw-bold"><a href="' + item.id + '">' + item.code + '</a></td>';
                    html += '<td class="text-dark fw-bold">' + nom.toUpperCase() + '</td>';
                    html += '<td class="text-dark fw-bold">' + item.categorie + '</td>';
                    html += '<td class="text-dark fw-bold">' + item.famille + '</td>';
                    html += '</tr>';
                }
                contentCtrl +='</tbody></table>';
            } else if (ctrl_type === 2){
                contentCtrl += '<option value="0">-- Equipements --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    const nom = data[i].nom || '';
                    contentCtrl += '<option value="' + data[i].id + '" style="text-transform: uppercase;">' + nom.toUpperCase() + '</option>';
                }
            } else {
                for (var i=0;i < reponse.data.length;i++) {
                    const nom = data[i].nom || '';
                    contentCtrl +=  '<li><a class="eq_li" href="#" id="' + data[i].id + '" style="text-transform: uppercase;">' + nom.toUpperCase() + '</a></li>';
                }
            }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Type_Equipement}', type: 'error', duration: 4000 })
        }
    })
}

function getEquipementTypeByCategorie(ctrl_name, ctrl_type, value){
    let contentCtrl = ''
    $.ajax({
        url : URL_APP + 'api/getEquipementTypeByCategorie/' + value,
        type : 'POST',
        success: function (response){
            let reponse = JSON.parse(response)
            const data = reponse.data || [];
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                            <thead><tr>
                                <th></th>
                                <th>Code</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Famille</th>
                            </tr></thead>
                            <tbody>
                `
                for (let i = 0; i < pageData.length; i++) {
                    const item = pageData[i];
                    const nom = item.nom || '';
                    html += '<tr style="font-size: 14px;">';
                    if (item.photo) {
                        html += '<td><img class="thumbnail" src="data/equipements/' + item.photo + '" alt="" height="48" id="' + item.photo + '"></td>';
                    } else {
                        html += '<td></td>';
                    }
                    html += '<td class="text-danger fw-bold"><a href="' + item.id + '">' + item.code + '</a></td>';
                    html += '<td class="text-dark fw-bold">' + nom.toUpperCase() + '</td>';
                    html += '<td class="text-dark fw-bold">' + item.categorie + '</td>';
                    html += '<td class="text-dark fw-bold">' + item.famille + '</td>';
                    html += '</tr>';
                }
                contentCtrl +='</tbody></table>';
            } else if (ctrl_type === 2){
                contentCtrl += '<option value="0">-- Equipements --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    const nom = data[i].nom || '';
                    contentCtrl += '<option value="' + data[i].id + '" style="text-transform: uppercase;">' + nom.toUpperCase() + '</option>';
                }
            } else {
                for (var i=0;i < reponse.data.length;i++) {
                    const nom = data[i].nom || '';
                    contentCtrl +=  '<li><a class="eq_li" href="#" id="' + data[i].id + '" style="text-transform: uppercase;">' + nom.toUpperCase() + '</a></li>';
                }
            }
            ctrl_name.innerHTML = contentCtrl
        },
        error : function (response){
            showToast(response.msg, {title: 'Erreur sur Chargement des données {Type_Equipement}', type: 'error', duration: 4000 })
        }
    })
}

function saveEquipementType(formData, ctrl_name, ctrl_type){
    fetch( URL_APP + "api/saveEquipementType", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 2000,
                title: 'Equipement Type'
            })
            if (data.code === "success") {
                clearForm("formEquipementType", "CodeEquipement")
                getAllEquipementTypes(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'EquipementType', duration: 4000}));
}
function deleteEquipementType(id_equipement_type_type_type){
    fetch( URL_APP + "api/deleteEquipementType", { method: "POST", body: id_equipement_type_type_type })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 4000,
                title: 'EquipementType'
            })
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'EquipementType', duration: 4000}));
}

function getSingleEquipementType(id_equipement_type) {
    let formData = new FormData();
    formData.append('id_equipement', id_equipement_type)

    return new Promise((resolve, reject) => {
        fetch( URL_APP + "api/getSingleEquipementType", { method: "POST", body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.code === "success") {
                    resolve(data); // renvoie l'objet au .then()
                } else {
                    showToast(data.msg, {
                        type : data.code,
                        duration: 1000,
                        title: 'Equipement Type'
                    })
                }
            })
            .catch(err => showToast("❌ Erreur : " + err, {title: 'EquipementType', duration: 4000}));
    });
}

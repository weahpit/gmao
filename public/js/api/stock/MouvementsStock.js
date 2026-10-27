
function saveMvt(formData){
    fetch( URL_APP + "api/saveMvt", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                title: 'Mouvements Stock'
            })
            if (data.code === "success") {
                getEquipementDetails(id_equipement)
                    Swal.fire({
                        title: "Stock mouvementé  avec succès !",
                        icon: "success",
                        draggable: true
                    });
                //clearForm("formMarque", "marque")
                //getAllMarques(ctrl_name, ctrl_type)
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Marque', duration: 4000}));
}

function getArticlesFromStock(ctrl_name, ctrl_type, type_operation) {

    $.ajax({
        url: URL_APP + 'api/getArticlesFromStock',
        type: 'POST',

        success: function (response) {

            let html = '';

            if (ctrl_type === 1) {

                html = `
                    <div class="table-card">

                        <div class="table-toolbar">

                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input
                                    type="text"
                                    id="searchStock"
                                    placeholder="Rechercher un article..."
                                >
                            </div>
                            <div class="table-actions mb-2">`
                            if (type_operation === "1"){
                                html += `<button
                                    type="button"
                                    class="btn-submit-stock-primary"
                                    id="btnSubmitStock">

                                    <i class="fas fa-save"></i>
                                    Valider l'opération

                                </button>`
                            } else {
                                html += `<button
                                    type="button"
                                    class="btn-submit-stock-danger"
                                    id="btnSubmitStock">

                                    <i class="fas fa-save"></i>
                                    Valider l'opération

                                </button>`
                            }


                html += `</div>
                            <span class="stock-counter">
                                ${response.data.length} article(s)
                            </span>

                        </div>

                        <div class="table-wrapper">

                            <table class="modern-table" id="tblMvt">

                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Article</th>
                                        <th>Code</th>
                                        <th>Catégorie</th>
                                        <th class="text-center">Références</th>
                                        <th class="text-center"><div style="width: 100px;">En Stock</div></th>
                                        <th class="text-center"><div style="width: 100px;">Qté</div></th>
                                        `;
                                        if (type_operation === "1"){
                                            html +=`<th>PJ</th>`
                                        } else{
                                            html +=`
                                                    <th>Service</th>
                                                    <th>Demandeur</th>
                                                `
                                        }

                html +=`<th>Mention</th>
                                </tr>
                                </thead>

                                <tbody id="tbodyMvt">
                `;

                response.data.forEach(article => {

                    html += `
                        <tr>

                            <td>
                               <input
                                    class="chk_input custom-check"
                                    type="checkbox"
                                    data-id="${article.id}"
                                    data-nom="${article.nom}"
                                    data-stock="${article.qte}"
                                >
                            </td>

                            <td>
                                <div class="article-name" style="color: black;max-width: 400px;min-width: 300px;">
                                    ${article.nom}
                                </div>
                            </td>
                            <td>
                                <div class="article-name text-success" style="color: black;width: 120px;">
                                    ${article.code}
                                </div>
                            </td>
                            <td>
                                <div class="article-name" style="color: black;max-width: 300px;">
                                    ${article.categorie}
                                </div>
                            </td>
                            <td style="border-left: 1px solid #ffd000;border-right: 1px solid #ffd000;background: #faf6e0;">
                                <div class="article-name text-center" style="width: 150px;background:linear-gradient(175deg, #f5f0e1, #ecebe8);border-bottom: 2px solid #d7895f;color: #0d0d0d;border-radius: 10px;font-size: 16px;">
                                    <span class="p-1 w-100" style="">${article.reference}</span>
                                </div>
                            </td>
                            <td class="text-center mx-auto">
                                <span class="stock-badge">
                                    ${article.qte}
                                </span>
                            </td>`
                            if (type_operation === "1"){
                                html +=`
                                        <td class="text-center">
                                        <input
                                            class="qte_input modern-input"
                                            type="number"
                                            disabled
                                            id="qte_${article.id}"
                                        >
                                    </td>
                                `
                            } else {
                                html +=`
                                        <td class="text-center">
                                            <input
                                                class="qte_input modern-input text-center"
                                                style="width:75px;"
                                                type="number"
                                                disabled
                                                min="1"
                                                max="${article.qte}"
                                                data-stock="${article.qte}"
                                                id="qte_${article.id}"
                                            >

                                            <small class="stock-error" id="error_${article.id}"></small>
                                        </td>
                                `
                        }

                        if (type_operation === "1"){
                            html +=`
                                <td>

                                    <label
                                        class="upload-btn"
                                        for="file_${article.id}"
                                        title="Attacher une pièce jointe">

                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hard-drive-download preview-icon"><path d="M12 2v8"/><path d="m16 6-4 4-4-4"/><rect width="20" height="8" x="2" y="14" rx="2"/><path d="M6 18h.01"/><path d="M10 18h.01"/></svg>

                                    </label>

                                    <input
                                        type="file"
                                        id="file_${article.id}"
                                        hidden
                                    >

                                    <span
                                        class="file-name"
                                        id="span_${article.id}">
                                    </span>

                                </td>
                            `;

                        } else {

                            html +=`
                            <td>
                                <select
                                    class="service_input modern-input input"
                                    style="min-width: 150px;"
                                    disabled
                                    id="service_${article.id}"
                                >
                                <small class="stock-error" id="error_s_${article.id}"></small>
                            </td>
                            <td>
                                <input
                                    class="demandeur_input modern-input"
                                    style="min-width: 150px;text-transform: uppercase;"
                                    type="text"
                                    disabled
                                    id="demandeur_${article.id}"
                                >
                            </td>
                            `
                        }

                    html +=`<td>
                                <input
                                    class="mention_input modern-input"
                                    style="min-width: 200px;"
                                    type="text"
                                    disabled
                                    id="mention_${article.id}"
                                >
                            </td></tr>
                    `;
                });

                html += `
                                </tbody>

                            </table>

                        </div>
                        <div class="table-pagination" id="tablePagination"></div>

                    </div>
                `;

            } else {

                html = `
                    <option value="0">
                        -- Articles --
                    </option>
                `;

                response.data.forEach(article => {

                    html += `
                        <option value="${article.id}">
                            ${article.nom}
                        </option>
                    `;
                });
            }

            ctrl_name.innerHTML = html;

            /*
             * Recherche instantanée
             */
            if (ctrl_type === 1) {
                initTablePagination();

                /*
                 * Activation des champs lors du clic sur la checkbox
                 */
                document
                    .querySelectorAll('.chk_input')
                    .forEach(chk => {

                        chk.addEventListener('change', function () {

                            const id = this.dataset.id;

                            const qteCtrl =
                                document.getElementById(`qte_${id}`);

                            const mentionCtrl =
                                document.getElementById(`mention_${id}`);


                            const errorCtrl =
                                document.getElementById(`error_${id}`);

                            const row =
                                this.closest('tr');

                            qteCtrl.disabled = !this.checked;
                            mentionCtrl.disabled = !this.checked;

                            if (type_operation === "2"){
                                const serviceCtrl =
                                    document.getElementById(`service_${id}`);

                                const demandeurCtrl =
                                    document.getElementById(`demandeur_${id}`);

                                serviceCtrl.disabled = !this.checked;
                                demandeurCtrl.disabled = !this.checked;

                                getAllServices(serviceCtrl, 2)
                            }
                            row.classList.toggle(
                                'selected',
                                this.checked
                            );

                            if(!this.checked){

                                qteCtrl.value = '';

                                mentionCtrl.value = '';

                                qteCtrl.classList.remove('input-error');

                                if(errorCtrl){
                                    errorCtrl.textContent = '';
                                }

                                if (type_operation === "2"){
                                    document.getElementById(`service_${id}`).value = "0";
                                    document.getElementById(`demandeur_${id}`).value = "";
                                }
                                row.classList.remove('row-error');
                            }
                            qteCtrl.focus()
                        });
                    });


                /*
 * Validation stock
 */
                if (type_operation !== "1") {

                    document.querySelectorAll('.qte_input').forEach(input => {

                        input.addEventListener('input', function () {

                            const stock = parseFloat(this.dataset.stock || 0);
                            const qte = parseFloat(this.value || 0);

                            const id = this.id.replace('qte_', '');

                            const errorCtrl =
                                document.getElementById(`error_${id}`);

                            const row =
                                this.closest('tr');

                            if (this.value === '') {

                                errorCtrl.textContent = '';
                                this.classList.remove('input-error');
                                row?.classList.remove('row-error');

                                return;
                            }

                            if (qte <= 0) {

                                errorCtrl.textContent =
                                    'La quantité doit être supérieure à zéro.';

                                this.classList.add('input-error');
                                row?.classList.add('row-error');

                                return;
                            }

                            if (qte > stock) {

                                errorCtrl.textContent =
                                    `Stock insuffisant. Disponible : ${stock}`;

                                this.classList.add('input-error');
                                row?.classList.add('row-error');

                            } else {

                                errorCtrl.textContent = '';

                                this.classList.remove('input-error');
                                row?.classList.remove('row-error');
                            }
                        });
                    });
                    document.querySelectorAll('.service_input').forEach(input => {
                        input.addEventListener('input', function () {
                            const service = this.value;
                            const row = this.closest('tr');
                            const errorCtrl = document.getElementById(`error_s_${id}`);
                            if (service === "0" || service === null) {

                                errorCtrl.textContent ='Le service est obligatoire.';

                                this.classList.add('input-error');
                                row?.classList.add('row-error');

                                return;
                            }
                        })
                    })

                    document
                        .querySelectorAll('input[type="file"]')
                        .forEach(input => {

                            input.addEventListener('change', function () {

                                const id =
                                    this.id.replace('file_', '');

                                const span =
                                    document.getElementById(`span_${id}`);

                                if (!span) {
                                    return;
                                }

                                if (this.files.length > 0) {

                                    span.textContent =
                                        this.files[0].name;

                                    span.title =
                                        this.files[0].name;

                                } else {

                                    span.textContent = '';
                                }
                            });

                        });
                }


                document.getElementById('btnSubmitStock')
                    ?.addEventListener('click', function () {

                        const articles = getSelectedArticles(type_operation);

                        if (!articles) {
                            return;
                        }

                        const listeArticles = articles
                            .map(article => `
            <li>
                <strong>
                    ${article.article_nom.length > 20
                                                ? article.article_nom.slice(0, 20) + '...'
                                                : article.article_nom}
                </strong>
                ${article.quantite ? ` - <span class="text-danger">Qté : ${article.quantite}</span>` : ''}
            </li>
        `)
                            .join('');

                        Swal.fire({

                            title: 'Confirmer l’opération ?',

                            html: `
            <div class="text-start">
                <p>
                    Vous allez enregistrer
                    <b>${articles.length}</b> article(s).
                </p>

                <div class="mt-3">
                    <strong>Articles à envoyer :</strong>
                    <ul class="mt-2">
                        ${listeArticles}
                    </ul>
                </div>
            </div>
        `,

                            icon: 'question',

                            showCancelButton: true,

                            confirmButtonText: 'Confirmer',

                            cancelButtonText: 'Annuler',

                            confirmButtonColor: '#2563eb'

                        }).then(result => {

                            if (result.isConfirmed) {

                                sendArticles(
                                    articles,
                                    type_operation
                                );
                            }
                        });

                    });

            }
        },

        error: function (xhr) {

            showToast(
                xhr.responseJSON?.message || 'Erreur serveur',
                {
                    title: 'Erreur chargement des articles',
                    type: 'error',
                    duration: 4000
                }
            );
        }
    });
}

function getMouvementsBayDate(ctrl_name, ctrl_type, valeurr) {

    $.ajax({
        url: URL_APP + 'api/getMouvementsBayDate',
        type: 'POST',

        success: function (response) {
            let reponse = JSON.parse(response)
            if (ctrl_type === 1){
                contentCtrl +=`
                <table class="table table-hover">
                    <thead><tr>
                            <th>Nature</th>
                    </tr></thead>
                    <tbody>
                `
                for (var i=0;i < reponse.data.length;i++){
                    contentCtrl +='<tr>'
                    contentCtrl +='<td><a href="#">' + reponse.data[i].nature + '</a></td>'
                    contentCtrl +='<tr>'
                }
                contentCtrl +='</tbody></table>';
            } else {
                contentCtrl += '<option value="0">-- Nature de l\'équipement --</option>'
                for (var i=0;i < reponse.data.length;i++) {
                    contentCtrl += '<option value="' + reponse.data[i].id + '">' + reponse.data[i].nature + '</option>'
                }
            }
            ctrl_name.innerHTML = contentCtrl
            if (ctrl_type === 1){ enhanceTable(".table",{ pageSize: 10, filename: 'NaturesEquipements.csv' })}
        },

        error: function (xhr) {

            showToast(
                xhr.responseJSON?.message || 'Erreur serveur',
                {
                    title: 'Erreur chargement des articles',
                    type: 'error',
                    duration: 4000
                }
            );
        }
    });
}


function initTablePagination() {

    const rowsPerPage = 10;

    const allRows = Array.from(
        document.querySelectorAll('#tbodyMvt tr')
    );

    const pagination =
        document.getElementById('tablePagination');

    const searchInput =
        document.getElementById('searchStock');

    let currentPage = 1;

    function getFilteredRows() {

        const search =
            searchInput?.value.toLowerCase().trim() || '';

        return allRows.filter(row =>
            row.textContent
                .toLowerCase()
                .includes(search)
        );
    }

    function renderTable() {

        const filteredRows = getFilteredRows();

        allRows.forEach(row => {
            row.style.display = 'none';
        });

        const start =
            (currentPage - 1) * rowsPerPage;

        const end =
            start + rowsPerPage;

        filteredRows
            .slice(start, end)
            .forEach(row => {

                row.style.display = '';
            });

        renderPagination(filteredRows.length);
    }

    function renderPagination(totalRows) {

        pagination.innerHTML = '';

        const totalPages =
            Math.max(
                1,
                Math.ceil(totalRows / rowsPerPage)
            );

        if(totalPages <= 1){
            return;
        }

        const prevBtn =
            document.createElement('button');

        prevBtn.innerHTML = '&laquo;';

        prevBtn.disabled =
            currentPage === 1;

        prevBtn.addEventListener('click', () => {

            if(currentPage > 1){

                currentPage--;

                renderTable();
            }
        });

        pagination.appendChild(prevBtn);

        for(let i = 1; i <= totalPages; i++){

            const btn =
                document.createElement('button');

            btn.textContent = i;

            if(i === currentPage){

                btn.classList.add('active');
            }

            btn.addEventListener('click', () => {

                currentPage = i;

                renderTable();
            });

            pagination.appendChild(btn);
        }

        const nextBtn =
            document.createElement('button');

        nextBtn.innerHTML = '&raquo;';

        nextBtn.disabled =
            currentPage === totalPages;

        nextBtn.addEventListener('click', () => {

            if(currentPage < totalPages){

                currentPage++;

                renderTable();
            }
        });

        pagination.appendChild(nextBtn);
    }

    searchInput?.addEventListener('input', () => {

        currentPage = 1;

        renderTable();
    });

    renderTable();
}

function validateArticlesStock() {

    let valid = true;

    document.querySelectorAll('.qte_input').forEach(input => {

        if (input.disabled) {
            return;
        }

        const stock =
            parseFloat(input.dataset.stock || 0);

        const qte =
            parseFloat(input.value || 0);

        if (qte > stock || qte <= 0) {

            valid = false;

            input.classList.add('input-error');
        }
    });

    return valid;
}

function getSelectedArticles(type_operation) {

    let articles = [];
    let hasError = false;

    document.querySelectorAll('.chk_input').forEach(chk => {

        if (!chk.checked) {
            return;
        }

        const id = chk.dataset.id;
        const articleNom = chk.dataset.nom;

        const qteCtrl =
            document.getElementById(`qte_${id}`);

        const mentionCtrl =
            document.getElementById(`mention_${id}`);


            const fileCtrl =
                document.getElementById(`file_${id}`);

        const serviceCtrl =
            document.getElementById(`service_${id}`);

        const demandeurCtrl =
            document.getElementById(`demandeur_${id}`);

        const stock =
            parseFloat(chk.dataset.stock || 0);

        const qte =
            parseFloat(qteCtrl.value || 0);

        if (type_operation === "2"){
            if (serviceCtrl.value === "0" || serviceCtrl.value === null || demandeurCtrl.value === "" ) {

                showToast(
                    'Veuillez sélectionnez un service et saisir un demandeur SVP .',
                    {
                        title: 'Enregistrement des données',
                        type: 'error'
                    }
                );

                serviceCtrl.focus();

                hasError = true;

                return;
            }
        }


        if (qte <= 0) {

            showToast(
                'Veuillez saisir une quantité valide.',
                {
                    title: 'Contrôle quantité',
                    type: 'error'
                }
            );

            qteCtrl.focus();

            hasError = true;

            return;
        }

        if (
            type_operation !== "1" &&
            qte > stock
        ) {

            showToast(
                `Stock insuffisant pour ${articleNom}. Disponible : ${stock}`,
                {
                    title: 'Contrôle stock',
                    type: 'error'
                }
            );


            hasError = true;

            return;
        }

        if (fileCtrl){
            articles.push({
                article_id: id,
                article_nom: articleNom,
                stock: stock,
                quantite: qte,
                mention: mentionCtrl.value.trim(),
                fichier:
                    fileCtrl.files.length > 0
                        ? fileCtrl.files[0]
                        : null
            });
        } else {
            articles.push({
                article_id: id,
                article_nom: articleNom,
                stock: stock,
                quantite: qte,
                service_demandeur: serviceCtrl.value,
                demandeur: demandeurCtrl.value,
                mention: mentionCtrl.value.trim(),
                fichier: null
            });
        }
        setTimeout(()=>{qteCtrl.focus();}, 1000)

    });

    if (hasError) {
        return null;
    }

    if (!articles.length) {

        showToast(
            'Veuillez sélectionner au moins un article.',
            {
                title: 'Validation',
                type: 'warning'
            }
        );

        return null;
    }

    return articles;
}


function sendArticles(articles, type_operation) {

    const formData = new FormData();

    formData.append(
        'type_operation',
        type_operation
    );

    formData.append(
        'articles',
        JSON.stringify(articles)
    );

    articles.forEach(article => {

        if (article.fichier) {

            formData.append(
                `file_${article.article_id}`,
                article.fichier
            );
        }
    });

    $.ajax({

        url: URL_APP + 'api/saveStockOperation',

        type: 'POST',

        data: formData,

        processData: false,

        contentType: false,

        beforeSend: function () {

            $('#btnSubmitStock')
                .prop('disabled', true)
                .html(
                    '<i class="fas fa-spinner fa-spin"></i> Traitement...'
                );
        },

        success:function(response){

            showToast(
                response.msg ||
                'Opération effectuée.',
                {
                    title:'Succès',
                    type:'success'
                }
            );

            Swal.fire({
                icon:'success',
                title:'Opération enregistrée',
                text:'Le stock a été mis à jour.'
            }).then(() => {

                // Rechargement du tableau
                getArticlesFromStock(
                    document.getElementById('div_entree'),
                    1,
                    type_operation
                );

                // Rafraîchissement équipement
                if (typeof getEquipementDetails === 'function') {
                    getEquipementDetails(id_equipement);
                }

            });
        },

        error: function (xhr) {

            let message = 'Une erreur est survenue.';

            if (xhr.responseJSON?.msg) {
                message = xhr.responseJSON.msg;
            }

            showToast(
                message,
                {
                    title: 'Mouvement de stock',
                    type: 'error'
                }
            );

            Swal.fire({
                title: 'Erreur',
                text: message,
                icon: 'error',
                confirmButtonColor: '#dc2626'
            });
        },

        complete: function () {

            $('#btnSubmitStock')
                .prop('disabled', false)
                .html(
                    '<i class="fas fa-save"></i> Valider l\'opération'
                );
        }
    });
}

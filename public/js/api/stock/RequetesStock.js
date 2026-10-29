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
                <!--<div class="table-card">

                        <div class="table-toolbar">-->

                            <!--<div class="search-box">
                                <i class="fas fa-search"></i>
                                <input
                                    type="text"
                                    id="searchStock"
                                    placeholder="Rechercher un article..."
                                >
                            </div>-->
                            <!--<div class="table-wrapper">-->
                            <table class="table table-responsive" id="tblMvts">
                                    <thead><tr>
                                        <th><div class="w-10">Articles</div></th>
                                        <th class="text-center"> Nature</th>
                                        <th class="text-center">Qté</th>
                                        <th class="text-center">Service</th>
                                        <!--<th class="text-center">Emplacement</th>-->
                                        <th class="text-center">N° Bon</th>
                                        <th class="text-center">PJ</th>
                                        <th class="text-center">Stock Avant</th>
                                        <th class="text-center">Stock Après</th>
                                        <th class="text-center">Heure</th>
                                        <th class="text-center">Observations</th>
                                    </tr></thead>
                                    <tbody>
                        `

                for(var i=0; i< donnees.length; i++){
                    html += '<tr class="tr" style="cursor: default;" id="' + donnees[i].id + '">';
                    if (donnees[i].type_mvt === "1") {
                        html += '<td class="text-center" style="font-size: 16px;"><a class="" href="#"><img class="me-2" src="data/equipemenrs/' + donnees[i].photo + '"  alt="" style="border-radius: 999px;" height="32">' + donnees[i].eqType  + '</a></td>'
                        html += '<td class="text-center" style="font-size: 16px;" >ENTREE</td>'
                        html += '<td class="text-center" style="font-size: 16px;">' + donnees[i].qte  + '</a></td>'
                        html += '<td class="text-center" style="font-size: 16px;">' + donnees[i].service  + '</a></td>'
                        /*html += '<td class="text-center" style="font-size: 16px;">' + donnees[i].emplacement  + ' [' + donnees[i].emplacement2  + ']</a></td>'*/
                        html += '<td class="text-center" style="font-size: 16px;">' + donnees[i].numero_bon  + '</a></td>'

                        if (donnees[i].bl === "") {
                            html += `<td>-</td>`
                        }else {
                            html +=
                            `<td><a href="${chemin}${donnees[i].bl}" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-paperclip preview-icon"><path d="m16 6-8.414 8.586a2 2 0 0 0 2.829 2.829l8.414-8.586a4 4 0 1 0-5.657-5.657l-8.379 8.551a6 6 0 1 0 8.485 8.485l8.379-8.551"/></svg>
                        </a></td>
                        `
                        }
                        html += '<td class="text-center fw-bold" style="font-size: 16px;">' + donnees[i].stock_avant  + '</a></td>'
                        html += '<td class="text-center fw-bold" style="font-size: 16px;">' + donnees[i].stock_apres  + '</a></td>'
                        html += '<td class="text-center fw-bold" style="font-size: 16px;">' + donnees[i].created_at  + '</a></td>'
                        html += '<td class="text-center fw-bold" style="font-size: 16px;">' + donnees[i].mention  + '</a></td>'

                    } else {
                        html += '<td class="text-center text-danger" style="font-size: 16px;width: 200px;"><a href="#" ><img class="me-2" src="data/equipemenrs/' + donnees[i].photo + '"  alt="" style="border-radius: 999px;" height="32">' + donnees[i].eqType  + '</a></td>'
                        html += '<td class="text-center text-danger" style="font-size: 16px;" >SORTIE</td>'
                        html += '<td class="text-center text-danger" style="font-size: 16px;">' + donnees[i].qte  + '</a></td>'
                        html += '<td class="text-center text-danger" style="font-size: 16px;">' + donnees[i].service  + '</a></td>'
                        /*html += '<td class="text-center text-danger" style="font-size: 16px;">' + donnees[i].emplacement  + ' [' + donnees[i].emplacement2  + ']</a></td>'*/
                        html += '<td class="text-center text-danger" style="font-size: 16px;">' + donnees[i].numero_bon  + '</a></td>'
                        html += `<td></td>`
                        html += '<td class="text-center fw-bold text-danger" style="font-size: 16px;">' + donnees[i].stock_avant  + '</a></td>'
                        html += '<td class="text-center fw-bold text-danger" style="font-size: 16px;">' + donnees[i].stock_apres  + '</a></td>'
                        html += '<td class="text-center fw-bold text-danger" style="font-size: 16px;">' + donnees[i].created_at  + '</a></td>'
                        html += '<td class="text-center fw-bold text-danger" style="font-size: 16px;">' + donnees[i].mention  + '</a></td>'

                    }

                    html +='</tr>'
                }

                html +=`    </tbody>
                            </table>
                        <!--</div>
                        <div class="table-pagination" id="tablePagination"></div>-->

                    <!--</div>-->
`;
                ctrl_name.innerHTML = html;
               enhanceTable("#tblMvts",{ pageSize: 20, filename: 'operationsJournalieres.csv' })
                /*document.getElementById("tblMvts").style.width = "100%"*/
               /* initTablePagination()*/
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Marque', duration: 4000}));
}


function getCodeArticle(formData, codeInput){
    fetch( URL_APP + "api/getCodeArticle", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.code === "success") {
                codeInput.value = data.valeur
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Marque', duration: 4000}));
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

function RechercheDoublonArticle(formData, inputError){
    fetch( URL_APP + "api/RechercheDoublonArticle", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            document.getElementById('div_error_nom').style.display = "none"
            inputError.innerText = ""
            if (data.code === "error") {
                document.getElementById('div_error_nom').style.display = "block"
                inputError.innerText = data.msg
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Article', duration: 4000}));
}
function RechercheDoublonRef(formData, inputError){
    fetch( URL_APP + "api/RechercheDoublonRef", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            document.getElementById('div_error_reference').style.display = "none"
            inputError.innerText = ""
            if (data.code === "error") {
                document.getElementById('div_error_reference').style.display = "block"
                inputError.innerText = data.msg
            }
        })
        .catch(err => showToast("❌ Erreur : " + err, {title: 'Référence', duration: 4000}));
}
function RechercheBonSortie(){
    $.ajax({
        url : URL_APP + "api/RechercheBonSortie",
        type: 'POST',
        success: function (response){
            if (parseInt(response.msg) > 0){
                document.querySelector('.banner-marquee').style.display = "block"
            }else{
                document.querySelector('.banner-marquee').style.display = "none"
            }
        }
    })
}

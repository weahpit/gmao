
function dataTableSnvlt(name, title, taille){
    let table = new DataTable(name, {
        responsive: true,
        pageLength: taille,
        layout: {
            topStart: {
                buttons: [
                    {
                        extend: 'excelHtml5',
                        title: title,
                        text: 'Exporter en excel'

                    }
                ]
            }
        },
        colReorder: true,
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
    });
}

function dataTableSnvltNoButton(name, taille){
    let table = new DataTable(name, {
        responsive: true,
        pageLength: taille,
        colReorder: true,
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
    });
}

function render_toast_value(valeur, type_valeur){
    let couleur = '#ffaeae'; // Erreur
    let couleur_texte = '#be0303'; // Erreur
    let icon = 'error';
    let iconColor = "red"
    valeur ='<span class="text-black" style="font-size: 16px;">' + valeur + '</span>'
    if(type_valeur === 1){ couleur_texte ='#2a7904'; couleur = '#cdfcb6'; icon = 'success';iconColor = '#2a7904'} // Succès
    else if (type_valeur === 2){couleur_texte ='#051000'; couleur = '#fada8c'; icon = 'warning'; iconColor = "#a87803"} // Avertissement
    else if (type_valeur === 3){couleur_texte ='#051000'; couleur = '#85dbc6'; icon = 'success'; iconColor = "#257d64"} // Infos
    Swal.fire({
        toast: true,
        background: couleur,
        color: couleur_texte,
        position: "top-end",
        modal : true,
        timer : 7000,
        icon: icon,
        iconColor: iconColor,
        title: valeur,
        showConfirmButton: false
    });
}


function getNbJours(date1, date2){

    const differenceInMilliseconds = date2.getTime() - date1.getTime(); // Conversion en millisecondes
    const differenceInDays = differenceInMilliseconds /  (1000 * 60 * 60 * 24); // Conversion en jours
        return differenceInDays;
}

function downloadCSV(csv, filename) {
    var csvFile;
    var downloadLink;

    // CSV file
    csvFile = new Blob([csv], {type: "text/csv"});

    // Download link
    downloadLink = document.createElement("a");

    // File name
    downloadLink.download = filename;

    // Create a link to the file
    downloadLink.href = window.URL.createObjectURL(csvFile);

    // Hide download link
    downloadLink.style.display = "none";

    // Add the link to DOM
    document.body.appendChild(downloadLink);

    // Click download link
    downloadLink.click();
}

function exportTableToCSV(filename) {
    var csv = [];
    var rows = document.querySelectorAll("table tr");

    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");

        for (var j = 0; j < cols.length; j++)
            row.push(cols[j].innerText);

        csv.push(row.join(";"));
    }

    // Download CSV file
    downloadCSV(csv.join("\n"), filename);
}

function json_to_csv(data, filename)
{


// Generate CSV string
    const headers = Object.keys(data[0]).join(';');
    const rows = data.map(obj => Object.values(obj).join(';')).join('\n');
    const csvContent = headers + '\n' + rows;

// Create a Blob and trigger download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '.csv';
    link.click();
    URL.revokeObjectURL(link.href);
    //
    // var data = document.getElementById(element_id);
    //
    // var file = XLSX.utils.table_to_book(data, {sheet: "sheet1"});
    //
    // XLSX.write(file, { bookType: type, bookSST: true, type: 'base64' });
    //
    // XLSX.writeFile(file, filename+'.' + type);
}

function exportJsonToExcel(jsonData, name) {
    // Create a new workbook
    const workbook = XLSX.utils.book_new();

    // Convert JSON data to a worksheet
    const worksheet = XLSX.utils.json_to_sheet(jsonData);

    // Append the worksheet to the workbook
    XLSX.utils.book_append_sheet(workbook, worksheet, "données");

    // Export the workbook as an Excel file
    XLSX.writeFile(workbook, name + ".xlsx");
}

function isDate(value) {
    return value instanceof Date && !isNaN(value.valueOf());
}


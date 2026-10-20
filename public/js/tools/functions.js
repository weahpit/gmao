function uploadImage(button, image){
    button.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Vérification taille < 200Ko
            if (file.size > 1024 * 1024) {
                showToast(container, "L'image dépasse 1Mo !", "warning");
                e.target.value = "";
                return;
            }
            // Aperçu
            const reader = new FileReader();
            reader.onload = function(ev) {
                image.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
}
function clearForm(formId, focusId) {
    const form = document.getElementById(formId);
    const ctrl = document.getElementById(focusId);
    form.querySelectorAll("input").forEach(input => {
        if (input.type === "checkbox" || input.type === "radio") {
            input.checked = false;
        } else {
            input.value = "";
        }
    });
    form.querySelectorAll("select").forEach(select => select.selectedIndex = 0);
    form.querySelectorAll("textarea").forEach(textarea => textarea.value = "");
    setTimeout(()=>{ctrl.focus()}, 500)
}

// Event sur keypress pour valider un button

function ValidateOnKeypress(input, buttonAction, form){

        /*
            input : le champ ou control qui contient la valeur
            buttonAction : le boutton qui contient le code
            form : le formulaire s'il existe
        */

    input.addEventListener("keypress", function (evt) {
        if (evt.key === 'Enter'){
            evt.preventDefault();
            buttonAction.click()
            if (form){
                document.getElementById(form).reset()}
        }
    })
}

function couleurAleatoireElegante() {
    // Palette élégante : tons doux et raffinés
    const palette = [
        "#d9d5f0", // Slate Blue
        "#ced4d9", // Steel Blue
        "#e1ebf7", // Slate Gray
        "#B0C4DE", // Light Steel Blue
        "#d7f6f6", // Dark Slate Gray
        "#e4f3ca", // Dark Olive Green
        "#f0d2be", // Saddle Brown
        "#efe4cd",  // Champagne Gold
        "#c6e2fa",
        "#b7f6e9",
        "#f9c624",
        "#65d7c0",
        "#b1daac"
    ];

    // Choisir une couleur au hasard
    const index = Math.floor(Math.random() * palette.length);
    return palette[index];
}

function executer(fn, render_type) {
    return new Promise((resolve, reject) => {
        try {
            fn(render_type)
                .then(data => {
                    resolve(data); // renvoie les données ou le rendu
                })
                .catch(err => {
                    reject("Erreur lors de l'exécution du widget : " + err);
                });
        } catch (err) {
            reject("Exception : " + err.message);
        }
    });
}

function afficheParValeur(select, valeur){
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].text === valeur) {
            select.selectedIndex = i; // sélectionne l'option
            break;
        }
    }
}

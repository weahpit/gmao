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

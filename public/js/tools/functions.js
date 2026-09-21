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


function saveMvt(formData){
    fetch( URL_APP + "api/saveMvt", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            showToast(data.msg, {
                type : data.code,
                duration: 8000,
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

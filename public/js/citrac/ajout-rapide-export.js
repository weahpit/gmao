/* Ajouts sans navigation : le dossier et les cases deja cochees restent intacts. */
(function () {
    'use strict';
    function init() {
        const dialog=document.getElementById('export-quick-dialog');
        if (!dialog || dialog.dataset.quickReady) return;
        dialog.dataset.quickReady = '1';
        const form=dialog.closest('form'), libelle=document.getElementById('export-quick-libelle'), message=document.getElementById('export-quick-message'), save=document.getElementById('export-quick-save');
        let courant=null, actif=false;
        const noms={client:'un client',pays:'un pays',navire:'un navire',proprietaire:'un propriétaire',produit:'un produit',document:'un type de document'};
        function fermer() { if (actif) return; if (dialog.close) dialog.close(); else dialog.removeAttribute('open'); }
        document.querySelectorAll('[data-quick-type]').forEach(button => button.addEventListener('click', () => {
            courant=button; libelle.value=''; message.textContent='';
            const type=button.dataset.quickType;
            libelle.maxLength=['proprietaire','produit','pays'].includes(type)?100:255;
            document.getElementById('export-quick-title').textContent='Ajouter '+noms[type];
            if (dialog.showModal) dialog.showModal(); else dialog.setAttribute('open','');
            libelle.focus();
        }));
        document.getElementById('export-quick-cancel').addEventListener('click',fermer);
        dialog.addEventListener('cancel',e=>{ if(actif)e.preventDefault(); });
        libelle.addEventListener('keydown',e=>{ if(e.key==='Enter'){e.preventDefault(); save.click();} });
        save.addEventListener('click',async()=>{
            if (!courant || actif) return;
            const nom=libelle.value.trim();
            if (!nom) {message.textContent='Saisissez un nom ou un libellé.'; libelle.focus(); return;}
            const type=courant.dataset.quickType, cible=document.getElementById(courant.dataset.quickTarget);
            const proprio=document.getElementById('proprietaire');
            if(type==='produit' && !proprio?.value){message.textContent='Sélectionnez d’abord un propriétaire dans l’ordre de transit.';return;}
            const donnees=new URLSearchParams({_token:form.querySelector('[name="_token"]').value,type,libelle:nom});
            if(type==='produit') donnees.set('proprietaire',proprio.value);
            actif=true; save.disabled=true; message.textContent='Enregistrement…';
            try {
                const r=await fetch(form.dataset.quickUrl,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:donnees});
                const data=await r.json();
                if(!r.ok || !data.id)throw new Error(data.message||'Ajout impossible. Rechargez la page uniquement après avoir sauvegardé votre dossier.');
                if(type==='document') {
                    let caseDoc=Array.from(cible.querySelectorAll('input[type="checkbox"]')).find(n=>n.value===data.id);
                    if(!caseDoc){const label=document.createElement('label');caseDoc=document.createElement('input');caseDoc.type='checkbox';caseDoc.name='documents[]';caseDoc.value=data.id;label.append(caseDoc,document.createTextNode(' '+data.libelle));cible.appendChild(label);}
                    caseDoc.checked=true; document.getElementById('export-types-empty')?.remove();
                } else {
                    let option=Array.from(cible.options).find(n=>n.value===data.id);
                    if(!option){option=document.createElement('option');option.value=data.id;option.textContent=data.libelle;cible.appendChild(option);}
                    if(data.proprietaire)option.dataset.proprietaire=data.proprietaire;
                    option.disabled=false;option.hidden=false;cible.value=data.id;cible.dispatchEvent(new Event('change',{bubbles:true}));
                }
                actif=false;fermer();
            } catch(e) {message.textContent=e.message || 'Erreur réseau : aucun ajout confirmé.';}
            finally{actif=false;save.disabled=false;}
        });
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();

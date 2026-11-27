/* Enregistrement du dossier sans changement de page (JSON), repli normal si le serveur renvoie du HTML. */
(() => {
'use strict';
const form=document.getElementById('export-form'); if(!form)return;
const zone=document.getElementById('export-erreur');
form.addEventListener('submit',async e=>{
  if(!window.fetch||!window.FormData||e.submitter?.formNoValidate===false)return;
  e.preventDefault(); if(form.dataset.busy)return; form.dataset.busy='1';
  const bouton=document.getElementById('export-enregistrer'); if(bouton)bouton.disabled=true; if(zone)zone.textContent='Enregistrement en cours…';
  try{
    const r=await fetch(form.action||location.href,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
    const texte=await r.text(); let d; try{d=JSON.parse(texte);}catch{ location.reload(); return; }
    if(!r.ok)throw Error(d.message||'Enregistrement refusé.');
    if(d.redirection){location.assign(d.redirection);return;}
    if(zone)zone.textContent=d.message||'Dossier enregistré.';
  }catch(err){if(zone)zone.textContent=err.message;}finally{delete form.dataset.busy;if(bouton)bouton.disabled=false;}
});
})();

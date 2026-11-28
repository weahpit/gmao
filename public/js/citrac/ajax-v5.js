(() => {
'use strict';
if(window.citracAjaxV5)return;window.citracAjaxV5=true;
const region=()=>document.querySelector('[data-ajax-region]');
// Un champ name=action masque form.action ; lire l'attribut, jamais la propriete DOM.
function adresseFormulaire(form){
 const valeur=form.getAttribute('action');
 const url=new URL(valeur&&valeur.trim()?valeur:location.href,location.href);
 if(url.origin!==location.origin)throw Error('Adresse du formulaire invalide : envoi externe interdit.');
 return url;
}

function message(text,error=false){const r=region();if(!r)return;let p=r.querySelector('.v5-status');if(!p){p=document.createElement('p');p.className='v5-status';p.setAttribute('role','status');r.prepend(p);}p.classList.toggle('error',error);p.textContent=text;}
async function refresh(url){const response=await fetch(url,{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});if(!response.ok||response.redirected)throw Error('Actualisation refusée : vérifiez votre connexion.');const doc=new DOMParser().parseFromString(await response.text(),'text/html'),next=doc.querySelector('[data-ajax-region]');if(!next)throw Error('Contenu de la page indisponible.');next.querySelectorAll('script').forEach(s=>s.remove());region().replaceWith(next);}
document.addEventListener('submit',async event=>{const form=event.target;if(!(form instanceof HTMLFormElement)||!form.matches('[data-ajax]')||!region()?.contains(form))return;event.preventDefault();if(form.dataset.busy)return;form.dataset.busy='1';const controls=[...form.querySelectorAll('button')];try{if((form.getAttribute('method')||'get').toLowerCase()==='get'){const url=adresseFormulaire(form);url.search=new URLSearchParams(new FormData(form)).toString();controls.forEach(b=>b.disabled=true);await refresh(url);history.replaceState(null,'',url);}else{const data=new FormData(form);controls.forEach(b=>b.disabled=true);const resp=await fetch(adresseFormulaire(form),{method:'POST',body:data,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});let d;try{d=await resp.json();}catch{throw Error('Réponse serveur non JSON (session expirée ou limite de téléversement).');}if(!resp.ok){const detail=[d?.message,d?.error,d?.detail,d?.title].find(v=>typeof v==='string'&&v.trim());throw Error(detail||({403:'Acces refuse : verifiez votre connexion, le jeton du formulaire et vos droits.',413:'Envoi trop volumineux : reduisez les fichiers ou verifiez les limites serveur.',422:'Saisie invalide : verifiez les champs et les quantites.'}[resp.status])||`Erreur HTTP ${resp.status}. Consultez le journal serveur a l’heure de cet essai.`);}await refresh(location.href);message(d.message||'Mise à jour effectuée.');}}catch(e){message(e.message,true);}finally{delete form.dataset.busy;controls.forEach(b=>b.disabled=false);}});
document.addEventListener('click',async e=>{const a=e.target.closest('a');if(!a||!region()?.contains(a)||e.ctrlKey||e.metaKey||e.shiftKey||a.target)return;const url=new URL(a.href);if(url.origin!==location.origin||url.pathname!==location.pathname)return;e.preventDefault();try{await refresh(url);history.replaceState(null,'',url);}catch(err){message(err.message,true);}});
})();

/* CITRAC : saisie des conteneurs, stocks résiduels et formules. */
(() => {
 'use strict';
 const form=document.getElementById('export-form'); if(!form)return;
 const read=id=>{try{return JSON.parse(document.getElementById(id)?.value||'[]');}catch{return [];}};
 const stocks=read('export-lots'), original=read('export-reservations-originales');
 let tcs=read('export-conteneurs'), affectations=read('export-affectations-initiales');
 const byId=new Map(stocks.map(l=>[l.id,{...l,pn:Number(l.pn),colis:Number(l.colis)}]));
 original.forEach(tc=>(tc.lots||[]).forEach(l=>{const s=byId.get(l.id);if(s){s.pn+=Number(l.qteExporter||0);s.colis+=Number(l.nbCartons||0);}}));
 const el=(tag,cls,text)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(text!==undefined)e.textContent=text;return e;};
 const input=(type,value,cb)=>{const e=el('input');e.type=type;e.value=value??'';e.addEventListener('input',()=>{cb(e.value);sync();});return e;};
 const label=(text,control)=>{const e=el('label','exp-field',text);e.append(control);return e;};
 const button=(text,cb)=>{const e=el('button','exp-btn',text);e.type='button';e.addEventListener('click',cb);return e;};
 const number=(value,cb,sacs=false)=>{const e=input('number',value,cb);e.min='0';e.step=sacs?'1':'0.001';return e;};
 const selected=new Set();
 function remaining(id,exclude){let kg=0,sacs=0;tcs.forEach(tc=>(tc.lots||[]).forEach(l=>{if(l!==exclude&&l.id===id){kg+=Number(l.qteExporter||0);sacs+=Number(l.nbCartons||0);}}));const s=byId.get(id);return {pn:Math.max(0,(s?.pn||0)-kg),sacs:Math.max(0,(s?.colis||0)-sacs)};}
 function render(){
  const host=document.getElementById('export-tcs');host.replaceChildren();selected.clear();tcs.forEach(tc=>(tc.lots||[]).forEach(l=>{if(l.id)selected.add(l.id);}));
  tcs.forEach((tc,idx)=>{
   const card=el('div','exp-card');const grid=el('div','exp-grid');
   grid.append(label('N° conteneur',input('text',tc.tc,v=>tc.tc=v)),label('N° plomb',input('text',tc.plomb,v=>tc.plomb=v)));
   const type=el('select');['Tc20','Tc40'].forEach(v=>{const o=new Option(v,v);o.selected=tc.typeTc===v;type.add(o);});type.addEventListener('change',()=>{tc.typeTc=type.value;sync();});grid.append(label('Type',type));
   const vrac=input('checkbox','',()=>tc.vrac=vrac.checked);vrac.checked=!!tc.vrac;grid.append(label('Vrac',vrac));card.append(grid);
   const table=el('table','exp-table');table.dataset.pagination='off';const head=el('thead');const hr=el('tr');['Lot','Nombre de sacs','Poids net chargé (kg)','Stock restant disponible','Action'].forEach(t=>hr.append(el('th','',t)));head.append(hr);table.append(head);const body=el('tbody');
   (tc.lots||[]).forEach((l,li)=>{
    const tr=el('tr'), select=el('select');select.add(new Option('Choisir le lot',''));
    byId.forEach(s=>{if(s.produit!==document.getElementById('produit').value)return;if(s.id!==l.id&&(s.pn<=0||selected.has(s.id)))return;const o=new Option(s.numero,s.id);o.selected=s.id===l.id;select.add(o);});
    select.addEventListener('change',()=>{l.id=select.value;const rest=remaining(l.id,l);l.nbCartons=rest.sacs;l.qteExporter=rest.pn.toFixed(3);render();sync();});
    const sac=number(l.nbCartons,v=>{l.nbCartons=v;},true),kg=number(l.qteExporter,v=>{l.qteExporter=v;});const rest=remaining(l.id,l);sac.max=rest.sacs;kg.max=rest.pn;
    [select,sac,kg,el('span','',`${rest.sacs} sacs / ${rest.pn.toLocaleString('fr-FR')} kg`),button('Retirer',()=>{tc.lots.splice(li,1);render();sync();})].forEach(c=>{const td=el('td');td.append(c);tr.append(td);});body.append(tr);
   });table.append(body);const scroll=el('div','exp-scroll');scroll.append(table);card.append(scroll,button('+ Ajouter un lot',()=>{tc.lots??=[];tc.lots.push({id:'',nbCartons:'',qteExporter:''});render();sync();}),button('Retirer le conteneur',()=>{tcs.splice(idx,1);render();sync();}));host.append(card);
  });
 }
 function renderFormules(){const host=document.getElementById('export-formules');host.replaceChildren();affectations.forEach((a,i)=>{const tr=el('tr'),select=el('select');select.append(document.getElementById('export-formule-options').content.cloneNode(true));select.name=`formules[${i}][id]`;select.value=a.id||'';Array.from(select.options).forEach(o=>{if(o.value&&o.dataset.proprietaire!==document.getElementById('proprietaire').value&&o.value!==a.id)o.disabled=true;});select.addEventListener('change',()=>{a.id=select.value;const o=select.selectedOptions[0];if(!a.volume)a.volume=o?.dataset.volume||'';renderFormules();sync();});const v=number(a.volume,x=>a.volume=x);v.name=`formules[${i}][volume]`;[select,v,button('Retirer',()=>{affectations.splice(i,1);renderFormules();sync();})].forEach(c=>{const td=el('td');td.append(c);tr.append(td);});host.append(tr);});}
 function sync(){document.getElementById('export-conteneurs').value=JSON.stringify(tcs);let kg=0;tcs.forEach(tc=>(tc.lots||[]).forEach(l=>kg+=Number(l.qteExporter||0)));const covered=affectations.reduce((n,a)=>n+Number(a.volume||0),0);document.getElementById('export-total').textContent=kg.toLocaleString('fr-FR',{minimumFractionDigits:3});document.getElementById('export-couvert').textContent=covered.toLocaleString('fr-FR',{minimumFractionDigits:3});document.getElementById('export-ecart').textContent=`Écart : ${(kg-covered).toLocaleString('fr-FR')} kg`;}
 document.getElementById('export-add-tc').addEventListener('click',()=>{tcs.push({tc:'',typeTc:'Tc20',plomb:'',lots:[]});render();sync();});
 document.getElementById('export-add-formule').addEventListener('click',()=>{affectations.push({id:'',volume:''});renderFormules();sync();});
 form.addEventListener('submit',()=>sync());
 document.getElementById('produit').addEventListener('change',()=>{render();documents();});document.getElementById('client').addEventListener('change',documents);
 document.getElementById('proprietaire').addEventListener('change',()=>{const id=document.getElementById('proprietaire').value;const p=document.getElementById('produit');Array.from(p.options).forEach(o=>{o.disabled=!!o.value&&o.dataset.proprietaire&&o.dataset.proprietaire!==id;});if(p.selectedOptions[0]?.disabled)p.value='';render();renderFormules();documents();});
 document.addEventListener('citrac:formule-creee',e=>{const f=e.detail;const option=new Option(f.libelle,f.id);option.dataset.volume=f.volume;option.dataset.proprietaire=f.proprietaire;document.getElementById('export-formule-options').content.append(option);affectations.push({id:f.id,volume:''});renderFormules();sync();});
 let docRequest;
 async function documents(){const produit=document.getElementById('produit').value,client=document.getElementById('client').value,host=document.getElementById('export-doc-liste');host.replaceChildren();if(!produit||!client)return;docRequest?.abort();docRequest=new AbortController();try{const url=new URL(form.dataset.documentsUrl,location.origin);url.searchParams.set('produit',produit);url.searchParams.set('client',client);if(form.dataset.dossier)url.searchParams.set('dossier',form.dataset.dossier);const resp=await fetch(url,{signal:docRequest.signal,credentials:'same-origin'});if(!resp.ok)throw Error('Lecture des documents impossible.');const d=await resp.json();document.getElementById('export-doc-modele').textContent=d.personnalise?'Modèle client personnalisé':'Modèle standard';(d.documents||[]).forEach(doc=>{const li=el('li','',`${doc.libelle}${doc.statut?' — '+doc.statut:''} `);if(doc.visualiser){const a=el('a','','Consulter');a.href=doc.visualiser;a.target='_blank';a.rel='noopener';li.append(a);}host.append(li);});}catch(e){if(e.name!=='AbortError')document.getElementById('export-doc-modele').textContent=e.message;}}
 render();renderFormules();sync();documents();
})();
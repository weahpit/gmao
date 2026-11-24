/* Signature vectorielle privee : stockage de points seulement. */
(function(){
    'use strict';
    function init(){
        const canvas=document.getElementById('visiteur-signature'), cache=document.getElementById('visiteur-signature-valeur');
        if(!canvas || canvas.dataset.initialise)return; canvas.dataset.initialise='1';
        const ctx=canvas.getContext('2d'), message=document.getElementById('visiteur-signature-message');
        if(!ctx){message.textContent='Le dessin de signature n’est pas disponible dans ce navigateur.';return;}
        let traits=[],actuel=null,pointeur=null;
        try{const v=JSON.parse(cache.value||'[]');if(Array.isArray(v))traits=v.filter(t=>Array.isArray(t)&&t.every(p=>Array.isArray(p)&&p.length===2&&p.every(Number.isFinite)&&p[0]>=0&&p[0]<=900&&p[1]>=0&&p[1]<=240));}catch(_){cache.value='';}
        function tracer(){
            ctx.clearRect(0,0,900,240);ctx.strokeStyle='#243642';ctx.lineWidth=2.5;ctx.lineCap='round';ctx.lineJoin='round';
            traits.forEach(t=>{if(!t.length)return;ctx.beginPath();ctx.moveTo(t[0][0],t[0][1]);t.slice(1).forEach(p=>ctx.lineTo(p[0],p[1]));ctx.stroke();});
            cache.value=traits.length?JSON.stringify(traits):'';
        }
        function point(e){const r=canvas.getBoundingClientRect();return[Math.round(Math.max(0,Math.min(900,(e.clientX-r.left)*900/r.width))*100)/100,Math.round(Math.max(0,Math.min(240,(e.clientY-r.top)*240/r.height))*100)/100];}
        canvas.addEventListener('pointerdown',e=>{
            if(e.button!==0 || actuel)return;
            if(traits.length>=300){message.textContent='Limite de traits atteinte. Effacez et recommencez.';return;}
            e.preventDefault();actuel=[point(e)];traits.push(actuel);pointeur=e.pointerId;
            if(canvas.setPointerCapture)canvas.setPointerCapture(pointeur);tracer();
        });
        canvas.addEventListener('pointermove',e=>{
            if(!actuel || e.pointerId!==pointeur)return;e.preventDefault();
            if(traits.reduce((s,t)=>s+t.length,0)>=20000){message.textContent='Signature trop détaillée. Effacez et recommencez.';return;}
            actuel.push(point(e));tracer();
        });
        function finir(e){if(!actuel || e.pointerId!==pointeur)return;if(actuel.length<2)traits.pop();actuel=null;pointeur=null;tracer();message.textContent=traits.length?'Signature prête à être enregistrée avec la visite.':'';}
        ['pointerup','pointercancel','lostpointercapture'].forEach(type=>canvas.addEventListener(type,finir));
        document.getElementById('visiteur-signature-effacer').addEventListener('click',()=>{traits=[];actuel=null;pointeur=null;tracer();message.textContent='Signature effacée.';});
        document.getElementById('visiteur-signature-annuler').addEventListener('click',()=>{traits.pop();actuel=null;pointeur=null;tracer();message.textContent='Dernier trait annulé.';});
        canvas.closest('form').addEventListener('submit',()=>{if(actuel && actuel.length<2)traits.pop();actuel=null;tracer();});
        tracer();
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();

(function(){
  'use strict';
  const el = (s,a={},c=[])=>{
    const e=document.createElement(s);
    Object.entries(a).forEach(([k,v])=>{
      if(k==='class') e.className=v; else if(k==='html') e.innerHTML=v; else e.setAttribute(k,v);
    });
    (Array.isArray(c)?c:[c]).forEach(x=>x!=null&&e.appendChild(typeof x==='string'?document.createTextNode(x):x));
    return e;
  };

  const state = JSON.parse(JSON.stringify((window.npPBv3Admin && npPBv3Admin.config) || {}));

  function ensureDefaults(){
    if(!state.color) state.color='#0B2650';
    if(typeof state.yearly_discount!=='number') state.yearly_discount=10;
    if(!Array.isArray(state.bands) || !state.bands.length){
      state.bands=[
        {label:'1-25',min:1,max:25},{label:'26-50',min:26,max:50},{label:'51-100',min:51,max:100},
        {label:'101-200',min:101,max:200},{label:'201-350',min:201,max:350},{label:'351-500',min:351,max:500},
        {label:'+500',min:501,max:999999},
      ];
    }
    if(!Array.isArray(state.modules)) state.modules=[];
    if(!state.branding) state.branding={company:'',logo:'',primary:'#0B2650',secondary:'#6366f1',accent:'#ec4899',font:'Inter',custom_css:'',powered:false};
  }
  ensureDefaults();

  const input=(v,a={})=>{const i=document.createElement('input');Object.assign(i,{type:'text',value:v||''},a);return i};
  const number=v=>input(v,{type:'number',step:'1'});
  const color=v=>input(v||'#0B2650',{type:'color',style:'width:48px;height:36px;border-radius:10px;border:1px solid #e1e4ea'});
  const btn=(l,v,fn)=>{const b=el('button',{class:'np3-btn '+(v||'')},l);b.addEventListener('click',fn);return b};
  const tab=(id,label)=>{const b=el('button',{class:'np3-tab','data-tab':id},label);b.addEventListener('click',()=>show(id));return b};

  function render(){
    const app=document.getElementById('np3-admin-app'); if(!app) return;
    app.innerHTML='';
    const header=el('div',{class:'np3-admin-header'},[
      el('div',{class:'np3-tabs'},[
        tab('general','Generelt'),tab('bands','Ansattegrupper'),tab('modules','Moduler'),tab('branding','Branding'),tab('pdf','PDF Generator')
      ]),
      el('div',{class:'np3-actions'},[btn('Nulstil til demo','ghost',reset),btn('Gem ændringer','primary',save)])
    ]);
    app.appendChild(header);
    app.appendChild(el('div',{class:'np3-admin-body'}));
    show('general');
  }

  function setActive(id){document.querySelectorAll('.np3-tab').forEach(t=>t.classList.toggle('active',t.getAttribute('data-tab')===id))}
  function show(id){
    setActive(id);
    const body=document.querySelector('.np3-admin-body'); body.innerHTML='';
    if(id==='general') general(body);
    if(id==='bands') bands(body);
    if(id==='modules') modules(body);
    if(id==='branding') branding(body);
    if(id==='pdf') pdf(body);
  }

  function general(body){
    const w=el('div',{class:'np3-card'}); w.appendChild(el('h3',{},'Generelle indstillinger'));
    const row1=el('div',{class:'np3-row'},[
      el('div',{class:'np3-label'},'Primær farve'),
      el('div',{class:'np3-ctrl'},[color(state.color),input(state.color,{class:'np3-text'})])
    ]);
    const c=row1.querySelector('input[type=color]'), t=row1.querySelector('.np3-text');
    c.value=state.color; t.value=state.color;
    c.addEventListener('input',e=>{ t.value=e.target.value; state.color=e.target.value; });
    t.addEventListener('input',e=>{ c.value=e.target.value; state.color=e.target.value; });

    const row2=el('div',{class:'np3-row'},[
      el('div',{class:'np3-label'},'Årlig rabat (%)'),
      el('div',{class:'np3-ctrl'},[number(state.yearly_discount)])
    ]);
    row2.querySelector('input').addEventListener('input',e=> state.yearly_discount=parseFloat(e.target.value||0));

    w.appendChild(row1); w.appendChild(row2);
    body.appendChild(w);
  }

  function bands(body){
    const w=el('div',{class:'np3-card'}); w.appendChild(el('h3',{},'Ansattegrupper'));
    const list=el('div',{class:'np3-stack'});
    state.bands.forEach((b,i)=>list.appendChild(row(b,i)));
    const add=btn('Tilføj gruppe','ghost',()=>{ state.bands.push({label:'',min:0,max:0}); show('bands'); });
    w.appendChild(list); w.appendChild(add); body.appendChild(w);

    function row(b,i){
      const r=el('div',{class:'np3-grid-4'});
      const l=input(b.label), mi=number(b.min), ma=number(b.max), del=el('button',{class:'np3-icon-btn'},'🗑');
      l.addEventListener('input',e=>b.label=e.target.value);
      mi.addEventListener('input',e=>b.min=parseInt(e.target.value||0));
      ma.addEventListener('input',e=>b.max=parseInt(e.target.value||0));
      del.addEventListener('click',()=>{ state.bands.splice(i,1); show('bands'); });
      [l,mi,ma,del].forEach(x=>r.appendChild(x)); return r;
    }
  }

  function modules(body){
    const w=el('div',{class:'np3-card'}); w.appendChild(el('h3',{},'Moduler'));
    const list=el('div',{class:'np3-stack'});
    state.modules.forEach((m,i)=>list.appendChild(block(m,i)));
    const add=btn('Tilføj modul','ghost',()=>{ state.modules.push({name:'',desc:'',prices:state.bands.map(()=>0)}); show('modules'); });
    w.appendChild(list); w.appendChild(add); body.appendChild(w);

    function block(m,i){
      const c=el('div',{class:'np3-subcard'});
      const name=input(m.name), desc=el('textarea',{class:'np3-textarea'},m.desc||'');
      name.addEventListener('input',e=>m.name=e.target.value);
      desc.addEventListener('input',e=>m.desc=e.target.value);
      c.appendChild(el('label',{class:'np3-label-top'},'Modulnavn')); c.appendChild(name);
      c.appendChild(el('label',{class:'np3-label-top'},'Beskrivelse')); c.appendChild(desc);

      const grid=el('div',{class:'np3-prices-grid'});
      if(!Array.isArray(m.prices)) m.prices=[];
      if(m.prices.length<state.bands.length) m.prices=[...m.prices,...Array(state.bands.length-m.prices.length).fill(0)];
      state.bands.forEach((b,idx)=>{
        const L=el('div',{class:'np3-price-label'}, b.label||('Bånd '+(idx+1)));
        const F=input(m.prices[idx]??0,{type:'number',step:'1'});
        F.addEventListener('input',e=> m.prices[idx]=parseFloat(e.target.value||0));
        grid.appendChild(el('div',{class:'np3-price-row'},[L,F]));
      });
      c.appendChild(el('div',{class:'np3-subtitle'},'Priser per ansattegruppe'));
      c.appendChild(grid);

      const del=el('button',{class:'np3-icon-btn danger'},'🗑');
      del.addEventListener('click',()=>{ state.modules.splice(i,1); show('modules'); });
      c.appendChild(el('div',{class:'np3-actions-end'},[del]));
      return c;
    }
  }

  function branding(body){
    const w=el('div',{class:'np3-card'}); w.appendChild(el('h3',{},'Branding'));
    [['Firmanavn','company'],['Logo URL','logo']].forEach(([L,K])=>{
      const r=el('div',{class:'np3-row'},[el('div',{class:'np3-label'},L),el('div',{class:'np3-ctrl'},[input(state.branding[K]||'')])]);
      r.querySelector('input').addEventListener('input',e=> state.branding[K]=e.target.value);
      w.appendChild(r);
    });
    body.appendChild(w);
  }

  function pdf(body){
    const w=el('div',{class:'np3-card'}); w.appendChild(el('h3',{},'PDF Tilbudsgenerator'));
    w.appendChild(el('p',{class:'np3-muted'},'PDF downloades fra opsummeringen i beregneren.'));
    body.appendChild(w);
  }

  async function save(){
    const fd=new FormData();
    fd.append('action','np_pb_v3_save'); fd.append('nonce',npPBv3Admin.nonce);
    fd.append('config', JSON.stringify(state));
    const res=await fetch(npPBv3Admin.ajax,{method:'POST',body:fd});
    const data=await res.json(); toast(data&&data.success?'Gemt ✔':'Fejl ved gemning');
  }
  async function reset(){
    const fd=new FormData();
    fd.append('action','np_pb_v3_reset'); fd.append('nonce',npPBv3Admin.nonce);
    const res=await fetch(npPBv3Admin.ajax,{method:'POST',body:fd});
    const data=await res.json();
    if(data&&data.success){ Object.assign(state,data.data.config); render(); toast('Nulstillet ✔'); }
    else toast('Kunne ikke nulstille');
  }

  function toast(msg){
    let t=document.querySelector('.np3-toast');
    if(!t){ t=el('div',{class:'np3-toast'}); document.body.appendChild(t); }
    t.textContent=msg; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),1600);
  }

  document.addEventListener('DOMContentLoaded', render);
})();

(function(){
  const API_URL     = (window.HQS_SEARCH && HQS_SEARCH.endpoint)     ? HQS_SEARCH.endpoint     : "/wp-json/hqs/v2/search?q=";
  const LOG_URL     = (window.HQS_SEARCH && HQS_SEARCH.log_endpoint) ? HQS_SEARCH.log_endpoint : "/wp-json/hqs/v2/log";
  const SEARCH_PAGE = (window.HQS_SEARCH && HQS_SEARCH.search_page)  ? HQS_SEARCH.search_page  : "/pesquisa/?q=";
  const CAT_BASE    = (window.HQS_SEARCH && HQS_SEARCH.category_base)? HQS_SEARCH.category_base: "/product-category/";
  const CURRENCY    = (window.HQS_SEARCH && HQS_SEARCH.currency)     ? HQS_SEARCH.currency     : "EUR";
  
  const fmtPrice    = new Intl.NumberFormat('pt-PT', { style: 'currency', currency: CURRENCY });
  
  let dropdown=null, input=null, timer=null, abortCtrl=null;
  const esc = (s)=> String(s||"").replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
 
  function isSticky(el){
    const STICKY = ['sticky','fixed'];
    let n = el;
    while (n && n !== document.body){
      const cs = window.getComputedStyle(n);
      if (STICKY.includes(cs.position)) return true;
      if (cs.transform && cs.transform !== 'none') return true;
      n = n.parentElement;
    }
    return false;
  }
 
  function findInput(){
    const marked=document.querySelector('input[data-hqs="1"]'); if(marked) return marked;
    const candidates=["form.ajax-search-form input[type='text']","input[name='s']","input[placeholder*='Pesquisar']","input[placeholder*='Pesquise']","[id*='et_b-header-search-input']"]; 
    for(const sel of candidates){ const el=document.querySelector(sel); if(el) return el; }
    return document.querySelector('.hqs-meili-input')||null;
  }
 
  function createDropdown(){ 
    if(dropdown) return; 
    dropdown=document.createElement('div'); 
    dropdown.id='hqs-dropdown'; 
    dropdown.style.display='none'; 
    dropdown.setAttribute('role','dialog'); 
    dropdown.setAttribute('aria-label','Sugestões de pesquisa'); 
    dropdown.innerHTML=`<div class="hqs-container" role="document"><div class="hqs-left"><div class="hqs-scroll-area"><h3>Populares</h3><div class="hqs-list hqs-popular-list" role="listbox" aria-label="Populares"></div><h3>Categorias</h3><div class="hqs-list hqs-categories-list" role="listbox" aria-label="Categorias"></div></div><div class="hqs-left-footer"></div></div><div class="hqs-right"><div class="hqs-products-header"><div class="hqs-products-title">Produtos</div><div class="hqs-loader"></div></div><div class="hqs-products-grid" role="list" aria-label="Produtos"></div></div></div>`; 
    document.body.appendChild(dropdown);
  } 
 
  function syncDropdownPosition(){
    if(!input||!dropdown) return; const rect=input.getBoundingClientRect(); const sticky=isSticky(input); const isMobile=window.innerWidth<=768;
    dropdown.classList.toggle('hqs-fixed', sticky); dropdown.classList.add('hqs-desktop');
    const desiredWidth=Math.max(rect.width,1000); const vw=window.innerWidth; let left=rect.left + (sticky?0:window.scrollX);
    if(!isMobile){ if(left+desiredWidth>vw-16){ left=vw-desiredWidth-16; } if(left<16) left=16; dropdown.style.width=desiredWidth+"px"; dropdown.style.left=left+"px"; } else { dropdown.style.width=vw+"px"; dropdown.style.left=0; }
    const top=rect.bottom + (sticky?0:window.scrollY) + 6; dropdown.style.top=top+"px";
  }
  window.addEventListener('resize', syncDropdownPosition);
  window.addEventListener('scroll', syncDropdownPosition, { passive:true });
 
  function setLoading(isLoading) {
    if(!dropdown) return;
    if(isLoading) dropdown.classList.add('hqs-loading');
    else dropdown.classList.remove('hqs-loading');
  }
 
  async function fetchResults(q){ 
    if(!q||q.length<2) return { query:"", products:[], categories:[], popular:[], total_hits:0 };
    setLoading(true);
    try{ 
      if(abortCtrl) abortCtrl.abort(); 
      abortCtrl=new AbortController();
      // LIMIT 12 AQUI
      const res=await fetch(API_URL+encodeURIComponent(q)+"&limit=12",{ headers:{'Accept':'application/json'}, signal:abortCtrl.signal });
      if(!res.ok) throw new Error(res.status);
      return await res.json();
    } catch(err){ 
      if(err.name!=='AbortError') console.error('[HQS] API error', err); 
      return { products:[], categories:[], popular:[], total_hits:0 };
    } finally {
      if (abortCtrl && !abortCtrl.signal.aborted) setLoading(false);
    }
  }
 
  function buildPriceHTML(p){ 
      const n=Number(p.price||0), r=Number(p.regular_price||0), s=Number(p.sale_price||0); 
      if(s > 0 && r > 0 && s < r) {
          return `<span class="price-sale">${fmtPrice.format(s)}</span> <span class="price-old">${fmtPrice.format(r)}</span>`;
      }
      const base = (n > 0) ? n : (r > 0 ? r : s);
      if(base === 0) return `<span class="price-contact">Sob consulta</span>`;
      return `<span class="price-normal">${fmtPrice.format(base)}</span>`; 
  }
 
  function buildProductHTML(p){ 
      const thumb = p.thumbnail || "";
      const hover = (p.images && p.images[0]) ? p.images[0] : thumb; 
      const title=esc(p.title||""); 
      const desc=esc(p.short_description||""); 
      const brand=esc(p.brand||""); 
      const sku=p.sku?`SKU: ${esc(p.sku)}`:""; 
      const priceHTML=buildPriceHTML(p); 
      
      return `<div class="hqs-card" role="listitem"><a href="${p.url}" class="hqs-link"><div class="hqs-img"><img class="img-main" src="${thumb}" alt="${title}"><img class="img-hover" src="${hover}" alt="${title}"></div><div class="hqs-info"><div class="hqs-title" title="${title}">${title}</div><div class="hqs-meta"><span>${brand}</span><span>${sku}</span></div><div class="hqs-desc">${desc}</div><div class="hqs-price-wrapper">${priceHTML}</div></div></a></div>`; 
  }
 
  function renderResults(data,term){ 
    if(!dropdown) return; 
    const popularBox=dropdown.querySelector('.hqs-popular-list'); 
    const catBox=dropdown.querySelector('.hqs-categories-list'); 
    const grid=dropdown.querySelector('.hqs-products-grid'); 
    const leftFooter=dropdown.querySelector('.hqs-left-footer'); 
    popularBox.innerHTML=''; catBox.innerHTML=''; grid.innerHTML=''; leftFooter.innerHTML=''; 
    const isMobile=window.innerWidth<=768; 
    const totalHits=typeof data.total_hits==='number'?data.total_hits:(data.products?data.products.length:0); 
    const popularLimit=isMobile?4:8; const categoriesLimit=isMobile?3:6;
    const mobileTitle = dropdown.querySelector('.hqs-mob-title');
    if (mobileTitle && isMobile) {
      mobileTitle.textContent = totalHits > 0 ? `Resultados (${totalHits})` : 'Resultados';
    }
 
    if(data.popular&&data.popular.length){ data.popular.slice(0,popularLimit).forEach(item=>{ const el=document.createElement('div'); el.className='hqs-item'; el.setAttribute('role','option'); el.textContent=item; el.onclick=()=>{ input.value=item; input.dispatchEvent(new Event('input',{bubbles:true})); }; popularBox.appendChild(el); }); } else { popularBox.innerHTML=`<div class="hqs-item" aria-disabled="true">Sem sugestões populares</div>`; }
    if(data.categories&&data.categories.length){ data.categories.slice(0,categoriesLimit).forEach(catName=>{ const slug=catName.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^\w\s\/-]/g,'').replace(/\s+/g,'-'); const el=document.createElement('div'); el.className='hqs-item'; el.setAttribute('role','option'); el.textContent=catName; el.onclick=()=>{ window.location.href = CAT_BASE + slug + '/'; }; catBox.appendChild(el); }); } else { catBox.innerHTML=`<div class="hqs-item" aria-disabled="true">Sem categorias</div>`; }
    
    const products=data.products||[]; 
    if(!products.length){ grid.innerHTML=`<div class="hqs-item" aria-disabled="true">Sem produtos para este termo.</div>`; } else { products.forEach(p=>{ grid.insertAdjacentHTML('beforeend', buildProductHTML(p)); }); }
    
    if(totalHits>0){ 
      leftFooter.innerHTML=`<button class="hqs-viewall" aria-label="Ver todos os resultados">Ver todos (${totalHits}) →</button>`; 
      const btn=leftFooter.querySelector('.hqs-viewall'); 
      if(btn) btn.addEventListener('click', ()=>{ window.location.href = SEARCH_PAGE + encodeURIComponent(term); }); 
    }
    try { fetch(LOG_URL, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ term, hits: totalHits, device: (isMobile?'mobile':'desktop') }) }); } catch(e){}
    syncDropdownPosition();
    setLoading(false); 
  }
 
  function bindInputEvents(){ 
    if(!input||input.dataset.hqsBound==='1') return; 
    input.dataset.hqsBound='1'; 
    if(input.closest('form')) input.closest('form').addEventListener('submit', e=>e.preventDefault()); 
    input.addEventListener('input', ()=>{ const q=input.value.trim(); if(q.length<2){ if(dropdown){ dropdown.style.display='none'; dropdown.classList.remove('hqs-open'); } return; } createDropdown(); dropdown.style.display='block'; dropdown.classList.add('hqs-open'); syncDropdownPosition(); clearTimeout(timer); timer=setTimeout(()=>{ fetchResults(q).then(d=>renderResults(d,q)); },250); }); 
    input.addEventListener('keydown', e=>{ const term=input.value.trim(); if(e.key==='Enter'&&term.length>0){ window.location.href = SEARCH_PAGE + encodeURIComponent(term); } if(!dropdown||dropdown.style.display==='none') return; const focusables=dropdown.querySelectorAll('.hqs-item, .hqs-card a, .hqs-viewall'); if(!focusables.length) return; const arr=[...focusables]; const idx=arr.indexOf(document.activeElement); if(e.key==='ArrowDown'){ e.preventDefault(); (arr[Math.min(idx+1,arr.length-1)]||arr[0]).focus(); } else if(e.key==='ArrowUp'){ e.preventDefault(); (arr[Math.max(idx-1,0)]||arr[0]).focus(); } else if(e.key==='Escape'){ dropdown.style.display='none'; dropdown.classList.remove('hqs-open'); input.focus(); } }); 
    document.addEventListener('click', ev=>{ if(!dropdown) return; if(!dropdown.contains(ev.target)&&ev.target!==input){ dropdown.style.display='none'; dropdown.classList.remove('hqs-open'); } }); 
  }
 
  function init(){ input=findInput(); if(!input){ setTimeout(init,400); return; } createDropdown(); bindInputEvents(); syncDropdownPosition(); }
  document.addEventListener('DOMContentLoaded', ()=> setTimeout(init,400));
})();
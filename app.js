// Utility: debounce
function debounce(fn, wait=150){ let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn(...args), wait); } }

// ===== Index features =====
(function(){
  const cards = document.getElementById('cards');
  if(!cards) return; // only on index

  const search = document.getElementById('search');
  const toggleOpen = document.getElementById('toggleOpen');
  const sortAZ = document.getElementById('sortAZ');
  const sortZA = document.getElementById('sortZA');
  const sortStatus = document.getElementById('sortStatus');
  const viewGrid = document.getElementById('viewGrid');
  const viewList = document.getElementById('viewList');

  const countAll = document.getElementById('countAll');
  const countOpen = document.getElementById('countOpen');
  const countClosed = document.getElementById('countClosed');

  const items = Array.from(cards.querySelectorAll('.card'));
  let showOnly = 'all'; // 'all' | 'open' | 'closed'

  function applyFilters(){
    const q = (search.value || '').toLowerCase().trim();
    let visible = 0, vOpen = 0, vClosed = 0;

    items.forEach(el=>{
      const name = el.dataset.name.toLowerCase();
      const status = el.dataset.status; // open | closed
      const match = name.includes(q) && (showOnly==='all' || status===showOnly);
      el.style.display = match ? '' : 'none';
      if(match){
        visible++;
        if(status==='open') vOpen++; else vClosed++;
      }
    });

    countAll.textContent = `${visible} total`;
    countOpen.textContent = `${vOpen} ouverts`;
    countClosed.textContent = `${vClosed} fermés`;
  }

  function sortBy(fn){
    const sorted = items.slice().sort(fn);
    sorted.forEach(el=>cards.appendChild(el));
  }

  search.addEventListener('input', debounce(applyFilters, 120));
  document.addEventListener('keydown', e=>{
    if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='k'){
      e.preventDefault(); search.focus();
    }
  });

  toggleOpen.addEventListener('click', ()=>{
    showOnly = showOnly==='all' ? 'open' : (showOnly==='open' ? 'closed' : 'all');
    toggleOpen.textContent = 'Afficher: ' + (showOnly==='all' ? 'Tous' : showOnly==='open' ? 'Disponibles' : 'Non Disponibles');
    applyFilters();
  });

  sortAZ.addEventListener('click', ()=>{
    sortAZ.classList.add('active'); sortZA.classList.remove('active'); sortStatus.classList.remove('active');
    sortBy((a,b)=> a.dataset.name.localeCompare(b.dataset.name));
  });
  sortZA.addEventListener('click', ()=>{
    sortZA.classList.add('active'); sortAZ.classList.remove('active'); sortStatus.classList.remove('active');
    sortBy((a,b)=> b.dataset.name.localeCompare(a.dataset.name));
  });
  sortStatus.addEventListener('click', ()=>{
    sortStatus.classList.add('active'); sortAZ.classList.remove('active'); sortZA.classList.remove('active');
    sortBy((a,b)=> a.dataset.status.localeCompare(b.dataset.status) || a.dataset.name.localeCompare(b.dataset.name));
  });

  viewGrid.addEventListener('click', ()=>{ cards.classList.add('grid-view'); cards.classList.remove('list-view'); viewGrid.classList.add('active'); viewList.classList.remove('active'); });
  viewList.addEventListener('click', ()=>{ cards.classList.remove('grid-view'); cards.classList.add('list-view'); viewList.classList.add('active'); viewGrid.classList.remove('active'); });

  // Copy link + preview modal
  cards.addEventListener('click', (e)=>{
    const btnCopy = e.target.closest('.btn-copy');
    if(btnCopy){
      navigator.clipboard.writeText(btnCopy.dataset.copy).then(()=>{
        btnCopy.textContent = 'Copié !';
        setTimeout(()=>btnCopy.textContent='Copier le lien', 1200);
      });
    }
    const btnPrev = e.target.closest('.btn-preview');
    if(btnPrev){
      const src = btnPrev.dataset.src; openPreview(src);
    }
  });

  // Modal preview
  const modal = document.getElementById('previewModal');
  const img = document.getElementById('previewImg');
  const close = modal?.querySelector('.modal-close');
  function openPreview(src){ img.src = src; modal.showModal(); }
  close?.addEventListener('click', ()=> modal.close());
  modal?.addEventListener('click', e=>{ if(e.target===modal) modal.close(); });
  document.addEventListener('keydown', e=>{ if(e.key==='Escape' && modal?.open) modal.close(); });

  // Initial counts
  applyFilters();
})();

// ===== Admin image URL live preview =====
(function(){
  const url = document.getElementById('imageUrl');
  const img = document.getElementById('preview');
  const wrap = document.getElementById('imgPreview');
  if(!url || !img) return;

  function update(){
    const v = url.value.trim();
    if(!v){ img.removeAttribute('src'); wrap.querySelector('.hint').textContent='Aperçu de l’image'; return; }
    img.src = v; wrap.querySelector('.hint').textContent = 'Aperçu';
  }
  url.addEventListener('input', debounce(update, 120));
  update();
})();
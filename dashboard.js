/* ===== Toasts ===== */
function showToast(message, type='info') {
  const container = document.getElementById('notifications') || (() => {
    const div = document.createElement('div');
    div.id = 'notifications';
    document.body.appendChild(div);
    return div;
  })();
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4800);
}

/* ===== Theme toggle (dark <-> modern) ===== */
(function(){
  const btn = document.getElementById('themeToggle');
  if (!btn) return;
  const apply = (mode)=>{
    document.body.classList.toggle('theme-dark', mode==='dark');
    document.body.classList.toggle('theme-modern', mode==='modern');
    document.cookie = 'dash_theme='+mode+'; path=/; max-age=31536000';
    showToast(`Thème: ${mode==='dark'?'Sombre':'Modernisé'}`, 'info');
  };
  // init
  const cookie = (document.cookie.split('; ').find(r=>r.startsWith('dash_theme='))||'').split('=')[1] || 'dark';
  apply(cookie);
  btn.addEventListener('click', ()=>{
    const mode = document.body.classList.contains('theme-dark') ? 'modern' : 'dark';
    apply(mode);
  });
})();

/* ===== Filtre liste cartes ===== */
(function(){
  const table = document.getElementById('cardsTable');
  if (!table) return;
  const search = document.getElementById('searchCards');
  const status = document.getElementById('filterStatus');

  function apply(){
    const q = (search.value||'').toLowerCase().trim();
    const st = status.value;
    table.querySelectorAll('tbody tr').forEach(tr=>{
      const name = tr.dataset.name || '';
      const holder = tr.dataset.holder || '';
      const rowStatus = tr.dataset.status || '';
      const matchQ = !q || name.includes(q) || holder.includes(q);
      const matchS = !st || rowStatus === st;
      tr.style.display = (matchQ && matchS) ? '' : 'none';
    });
  }

  search.addEventListener('input', ()=>requestAnimationFrame(apply));
  status.addEventListener('change', apply);
})();

/* ===== Prévisualisation Image + Reset + Mode edit ===== */
(function(){
  const url = document.getElementById('imageUrl');
  const img = document.getElementById('preview');
  const wrap = document.getElementById('imgPreview');
  const file = document.getElementById('fileUpload');
  const reset = document.getElementById('resetForm');

  if (url && img && wrap) {
    const update = ()=>{
      const v = url.value.trim();
      if (!v) { img.style.display='none'; wrap.querySelector('.hint').textContent='Aperçu de l’image'; return; }
      img.src = v; img.style.display='block'; wrap.querySelector('.hint').textContent='Aperçu';
    };
    url.addEventListener('input', update); update();
  }

  file?.addEventListener('change', e=>{
    const f = e.target.files?.[0]; if(!f) return;
    const r = new FileReader();
    r.onload = ()=>{ img.src = r.result; img.style.display='block'; wrap.querySelector('.hint').textContent='Aperçu (fichier)'; };
    r.readAsDataURL(f);
  });

  reset?.addEventListener('click', ()=>{
    document.getElementById('formAction').value = 'create_card';
    document.getElementById('formId').value = '';
    document.getElementById('cardForm').reset();
    img && (img.style.display='none');
    showToast('Formulaire réinitialisé', 'info');
  });

  // bouton ✏️ pour pré-remplir
  document.querySelectorAll('[data-edit]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const d = JSON.parse(btn.getAttribute('data-edit'));
      document.getElementById('formAction').value = 'update_card';
      document.getElementById('formId').value = d.id;
      document.getElementById('name').value = d.name||'';
      document.getElementById('link').value = d.link||'';
      document.getElementById('status').value = d.status||'open';
      document.getElementById('imageUrl').value = d.image_url||'';
      document.getElementById('holder').value = d.holder_discord||'';
      const e = new Event('input'); document.getElementById('imageUrl').dispatchEvent(e);
      showToast('Mode édition activé', 'info');
    });
  });
})();

/* ===== Logs — filtre texte ===== */
(function(){
  const table = document.getElementById('logsTable');
  if (!table) return;
  const search = document.getElementById('searchLogs');
  const apply = ()=>{
    const q = (search.value||'').toLowerCase().trim();
    table.querySelectorAll('tbody tr').forEach(tr=>{
      const u = tr.dataset.user || '';
      const a = tr.dataset.action || '';
      tr.style.display = (!q || u.includes(q) || a.includes(q)) ? '' : 'none';
    });
  };
  search.addEventListener('input', apply);
})();

/* ===== Chart.js ===== */
(function(){
  if (!window.__CHART_DATA__) return;
  const { pie, line } = window.__CHART_DATA__;
  // Pie
  const ctxPie = document.getElementById('chartPie');
  if (ctxPie) {
    new Chart(ctxPie, {
      type: 'doughnut',
      data: {
        labels: ['Disponibles','Non disponibles'],
        datasets: [{ data: [pie.open, pie.closed] }]
      },
      options: {
        plugins:{ legend:{ position:'bottom' } }
      }
    });
  }
  // Line
  const ctxLine = document.getElementById('chartLine');
  if (ctxLine) {
    new Chart(ctxLine, {
      type: 'line',
      data: {
        labels: line.labels,
        datasets: [{ label:'Créations/mois', data: line.values, tension:.3 }]
      },
      options: {
        plugins:{ legend:{ display:false } },
        scales: { y: { beginAtZero:true, ticks: { precision:0 } } }
      }
    });
  }
})();

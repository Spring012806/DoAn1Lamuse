(() => {
  const root = document.querySelector('.lm-products-page');
  if (!root) return;
  const grid = root.querySelector('#lm-product-grid');
  const cards = [...root.querySelectorAll('.lm-product-card')];
  const count = root.querySelector('#lm-count');
  const countLabel = root.querySelector('.lm-result-count');
  const empty = root.querySelector('#lm-empty-state');
  const activeFilters = root.querySelector('#lm-active-filters');
  const filterPanel = root.querySelector('#lm-filter');
  let selectedSize = '';
  let selectedColor = '';
  let toastTimer;

  function notify(message) {
    let toast = document.querySelector('.lm-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'lm-toast';
      Object.assign(toast.style, {position:'fixed',left:'50%',bottom:'22px',transform:'translate(-50%,15px)',opacity:'0',pointerEvents:'none',zIndex:'9999',background:'#0d530e',color:'#fbf5dd',padding:'13px 20px',font:'12px Arial,sans-serif',transition:'.25s',boxShadow:'0 8px 25px #0002'});
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.style.opacity = '1'; toast.style.transform = 'translate(-50%,0)';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translate(-50%,15px)'; }, 2400);
  }
  function chosenPrices() { return [...root.querySelectorAll('input[name="price"]:checked')].map(el => el.value); }
  function chosenMaterials() { return [...root.querySelectorAll('input[name="material"]:checked')].map(el => el.value.toLocaleLowerCase('vi')); }
  function priceMatch(price, ranges) {
    if (!ranges.length) return true;
    return ranges.some(r => r==='under' ? price<1000000 : r==='1to2' ? price>=1000000&&price<2000000 : r==='2to3' ? price>=2000000&&price<=3000000 : price>3000000);
  }
  function applyFilters() {
    const category = root.querySelector('input[name="category"]:checked')?.value || 'all';
    const prices = chosenPrices();
    const materials = chosenMaterials();
    const query = (root.querySelector('#lm-product-search')?.value || '').trim().toLocaleLowerCase('vi');
    let visible = 0;
    cards.forEach(card => {
      const price = Number(card.dataset.price);
      const categoryOk = category==='all' || card.dataset.category===category;
      const materialOk = !materials.length || materials.some(m => card.dataset.material.toLocaleLowerCase('vi').includes(m));
      const colors = (card.dataset.color || '')
  .split(',')
  .map(c => c.trim().toLocaleLowerCase('vi'));

const sizes = (card.dataset.size || '')
  .split(',')
  .map(s => s.trim().toLocaleLowerCase('vi'));

const colorOk = !selectedColor ||
  colors.includes(selectedColor.toLocaleLowerCase('vi'));

const sizeOk = !selectedSize ||
  sizes.includes(selectedSize.toLocaleLowerCase('vi'));
      const queryOk = !query || card.dataset.name.toLocaleLowerCase('vi').includes(query) || card.dataset.category.toLocaleLowerCase('vi').includes(query);
      
      const match = categoryOk
        && materialOk
        && priceMatch(price, prices)
        && colorOk
        && sizeOk
        && queryOk;

      card.hidden = !match;
      if (match) visible++;
    });
    const sort = root.querySelector('#lm-sort').value;
    const shown = cards.filter(card => !card.hidden);
    shown.sort((a,b) => {
      if (sort==='price-asc') return Number(a.dataset.price)-Number(b.dataset.price);
      if (sort==='price-desc') return Number(b.dataset.price)-Number(a.dataset.price);
      if (sort==='name') return a.dataset.name.localeCompare(b.dataset.name,'vi');
      if (sort==='newest') return Number(b.dataset.id)-Number(a.dataset.id);
      return Number(a.dataset.id)-Number(b.dataset.id);
    }).forEach(card => grid.appendChild(card));
    count.textContent = visible;
    countLabel.innerHTML = `Hiển thị <strong>${visible}</strong> sản phẩm.`;
    empty.hidden = visible !== 0;
    root.querySelector('.lm-pagination').hidden = visible === 0;
    renderPills(category, prices, materials);
  }
  function renderPills(category, prices, materials) {
    activeFilters.innerHTML = '';
    const labels = [];
    if (category!=='all') labels.push({label:category, clear:()=>{const all=root.querySelector('input[name="category"][value="all"]');if(all)all.checked=true;}});
    prices.forEach(p => labels.push({label:({under:'Dưới 1 triệu','1to2':'1–2 triệu','2to3':'2–3 triệu',over:'Trên 3 triệu'})[p],clear:()=>{const el=root.querySelector(`input[name="price"][value="${p}"]`);if(el)el.checked=false;}}));
    materials.forEach(m => labels.push({label:m,clear:()=>{root.querySelectorAll('input[name="material"]').forEach(el=>{if(el.value.toLocaleLowerCase('vi')===m)el.checked=false;});}}));
    if (selectedSize) labels.push({label:'Size '+selectedSize,clear:()=>{selectedSize='';root.querySelectorAll('.lm-size-chip').forEach(b=>b.classList.remove('active'));}});
    if (selectedColor) labels.push({label:selectedColor,clear:()=>{selectedColor='';root.querySelectorAll('.lm-swatch').forEach(b=>b.classList.remove('active'));}});
    labels.forEach(item=>{const btn=document.createElement('button');btn.type='button';btn.className='lm-filter-pill';btn.innerHTML=`${item.label} <span>×</span>`;btn.addEventListener('click',()=>{item.clear();applyFilters();});activeFilters.appendChild(btn);});
  }
  root.querySelector('#lm-apply-filters').addEventListener('click',()=>{applyFilters();filterPanel.classList.remove('is-open');});
  root.querySelector('#lm-reset-filters').addEventListener('click',resetFilters);
  root.querySelector('#lm-empty-reset').addEventListener('click',resetFilters);
  function resetFilters(){
    root.querySelectorAll('input[name="price"],input[name="material"]').forEach(el=>el.checked=false);
    const all=root.querySelector('input[name="category"][value="all"]');if(all)all.checked=true;
    selectedSize='';selectedColor='';root.querySelectorAll('.lm-size-chip,.lm-swatch').forEach(el=>el.classList.remove('active'));
    applyFilters();
  }
  root.querySelectorAll('.lm-filter-title').forEach(btn=>btn.addEventListener('click',()=>{
    const content=btn.nextElementSibling;const expanded=btn.getAttribute('aria-expanded')==='true';btn.setAttribute('aria-expanded',String(!expanded));btn.querySelector('span').textContent=expanded?'+':'−';if(content)content.hidden=expanded;
  }));
  root.querySelectorAll('.lm-size-chip').forEach(btn=>btn.addEventListener('click',()=>{
    const wasActive=btn.classList.contains('active');root.querySelectorAll('.lm-size-chip').forEach(b=>b.classList.remove('active'));selectedSize=wasActive?'':btn.dataset.size;if(!wasActive)btn.classList.add('active');applyFilters();
  }));
  root.querySelectorAll('.lm-swatch').forEach(btn=>btn.addEventListener('click',()=>{
    const wasActive=btn.classList.contains('active');root.querySelectorAll('.lm-swatch').forEach(b=>b.classList.remove('active'));selectedColor=wasActive?'':btn.dataset.color;if(!wasActive)btn.classList.add('active');applyFilters();
  }));
  root.querySelectorAll('input[name="category"],input[name="price"],input[name="material"]').forEach(el=>el.addEventListener('change',applyFilters));
  root.querySelector('#lm-sort').addEventListener('change',applyFilters);
  root.querySelectorAll('.lm-view-switch button').forEach(btn=>btn.addEventListener('click',()=>{
    root.querySelectorAll('.lm-view-switch button').forEach(b=>b.classList.toggle('active',b===btn));grid.classList.toggle('is-list',btn.dataset.view==='list');
  }));
  root.querySelector('#lm-mobile-filter').addEventListener('click',()=>filterPanel.classList.add('is-open'));
  root.querySelector('.lm-filter-close').addEventListener('click',()=>filterPanel.classList.remove('is-open'));
  document.addEventListener('click',e=>{if(filterPanel.classList.contains('is-open')&&!filterPanel.contains(e.target)&&!e.target.closest('#lm-mobile-filter'))filterPanel.classList.remove('is-open');});
  root.querySelectorAll('.lm-wishlist').forEach(btn=>btn.addEventListener('click',()=>{
    const active=btn.getAttribute('aria-pressed')==='true';btn.setAttribute('aria-pressed',String(!active));btn.textContent=active?'♡':'♥';notify(active?'Đã bỏ khỏi danh sách yêu thích.':'Đã thêm vào danh sách yêu thích.');
  }));
  root.querySelectorAll('.lm-add-cart').forEach(btn=>btn.addEventListener('click',()=>{
    // Demo UI: thay bằng AJAX/PHP endpoint khi tích hợp giỏ hàng thật.
    const cartCount=document.querySelector('[data-cart-count],#cart-count,.cart-count');
    if(cartCount)cartCount.textContent=String((parseInt(cartCount.textContent,10)||0)+1);
    notify(`Đã thêm “${btn.dataset.name}” vào giỏ hàng.`);
  }));
  // Phân trang giao diện mẫu: chưa tải dữ liệu trang 2/3 nếu chưa nối backend.
  root.querySelectorAll('.lm-pagination button:not(:disabled)').forEach(btn=>btn.addEventListener('click',()=>{
    if(btn.textContent.trim()==='‹'||btn.textContent.trim()==='›')return;
    root.querySelectorAll('.lm-pagination button').forEach(b=>{b.classList.remove('active');b.removeAttribute('aria-current');});btn.classList.add('active');btn.setAttribute('aria-current','page');notify('Phân trang hiện là giao diện mẫu; cần kết nối backend để tải sản phẩm tương ứng.');
  }));
  applyFilters();
})();

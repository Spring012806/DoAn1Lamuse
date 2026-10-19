document.addEventListener('DOMContentLoaded', () => {
  const product = window.LM_PRODUCT || {};
  const variants = Array.isArray(window.LM_VARIANTS) ? window.LM_VARIANTS : [];
  const $ = id => document.getElementById(id);
  const colorsBox = $('lmColors'), sizesBox = $('lmSizes');
  const colorLabel = $('lmColorLabel'), priceEl = $('lmPrice');
  const availability = $('lmAvailability'), qty = $('lmQuantity');
  const addBtn = $('lmAddCart'), buyBtn = $('lmBuyNow');
  const feedback = $('lmFeedback');
  let colorId = null, sizeId = null, chosen = null;

  const money = n => new Intl.NumberFormat('vi-VN').format(Number(n || 0)) + '₫';
  const colors = () => {
    const map = new Map();
    variants.forEach(v => { if (!map.has(v.colorId)) map.set(v.colorId, v); });
    return [...map.values()];
  };
  const sizes = () => {
    const map = new Map();
    variants.filter(v => v.colorId === colorId)
      .sort((a,b) => a.sizeOrder - b.sizeOrder)
      .forEach(v => { if (!map.has(v.sizeId)) map.set(v.sizeId, v); });
    return [...map.values()];
  };
  function syncVariant() {
    chosen = variants.find(v => v.colorId === colorId && v.sizeId === sizeId) || null;
    if (!chosen) {
      availability.classList.remove('is-out');
      availability.textContent = colorId === null ? 'Vui lòng chọn màu sắc và kích thước.' : 'Vui lòng chọn kích thước.';
      priceEl.textContent = money(product.price);
      qty.value = '1'; qty.removeAttribute('max');
      addBtn.disabled = buyBtn.disabled = true;
      return;
    }
    const available = chosen.stock > 0;
    availability.classList.toggle('is-out', !available);
    availability.textContent = available ? `Còn ${chosen.stock} sản phẩm` : 'Biến thể này đã hết hàng.';
    priceEl.textContent = money(chosen.price || product.price);
    qty.max = String(chosen.stock);
    qty.value = available ? '1' : '1';
    addBtn.disabled = buyBtn.disabled = !available;
  }
  function renderColors() {
    colorsBox.replaceChildren();
    colors().forEach(c => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'lm-color-swatch' + (c.colorId === colorId ? ' is-active' : '');
      b.title = c.colorName; b.setAttribute('aria-label', c.colorName);
      const span = document.createElement('span'); span.style.backgroundColor = c.hex; b.append(span);
      b.addEventListener('click', () => {
        colorId = c.colorId; sizeId = null; colorLabel.textContent = c.colorName;
        renderColors(); renderSizes(); syncVariant();
      });
      colorsBox.append(b);
    });
  }
  function renderSizes() {
    sizesBox.replaceChildren();
    sizes().forEach(s => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'lm-size-btn' + (s.sizeId === sizeId ? ' is-active' : '');
      b.textContent = s.sizeName;
      const hasStock = variants.some(v => v.colorId === colorId && v.sizeId === s.sizeId && v.stock > 0);
      b.disabled = !hasStock;
      b.addEventListener('click', () => { sizeId = s.sizeId; renderSizes(); syncVariant(); });
      sizesBox.append(b);
    });
  }
  function normalizeQty() {
    let n = Number.parseInt(qty.value, 10);
    if (!Number.isFinite(n) || n < 1) n = 1;
    if (chosen && chosen.stock > 0) n = Math.min(n, chosen.stock);
    qty.value = String(Math.max(1, n));
  }
  $('lmQtyMinus')?.addEventListener('click', () => { qty.value = String(Math.max(1, (parseInt(qty.value,10)||1)-1)); normalizeQty(); });
  $('lmQtyPlus')?.addEventListener('click', () => { qty.value = String((parseInt(qty.value,10)||1)+1); normalizeQty(); });
  qty?.addEventListener('change', normalizeQty);

  async function postAction(url, payload) {
    feedback.textContent = 'Đang xử lý…';
    addBtn.disabled = buyBtn.disabled = true;
    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type':'application/json', 'Accept':'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Có lỗi xảy ra. Vui lòng thử lại.');
      feedback.textContent = data.message || 'Đã cập nhật.';
      if (data.redirect) window.location.href = data.redirect;
      return data;
    } catch (e) {
      feedback.textContent = e.message || 'Không thể kết nối máy chủ.';
      return null;
    } finally {
      syncVariant();
    }
  }
  function cartPayload(action) {
    if (!chosen || chosen.stock < 1) { feedback.textContent = 'Vui lòng chọn biến thể còn hàng.'; return null; }
    normalizeQty();
    return {action, variant_id: chosen.id, quantity: Number(qty.value)};
  }
  addBtn?.addEventListener('click', () => {
    const payload = cartPayload('add');
    if (payload) postAction('cart_action.php', payload);
  });
  buyBtn?.addEventListener('click', () => {
    const payload = cartPayload('buy_now');
    if (payload) postAction('cart_action.php', payload);
  });

  $('lmWishlist')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    try {
      const response = await fetch('wishlist_action.php', {
        method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'},
        credentials:'same-origin', body:JSON.stringify({product_id: Number(product.id)})
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Không thể cập nhật yêu thích.');
      button.classList.toggle('is-active', data.active);
      button.setAttribute('aria-pressed', data.active ? 'true' : 'false');
      button.querySelector('span').textContent = data.active ? '♥' : '♡';
      feedback.textContent = data.message;
    } catch (e) { feedback.textContent = e.message || 'Không thể cập nhật yêu thích.'; }
  });

  document.querySelectorAll('.lm-thumb').forEach(button => {
    button.addEventListener('click', () => {
      const img = $('lmMainImage');
      if (!img) return;
      img.src = button.dataset.image; img.alt = button.dataset.alt || product.name || '';
      document.querySelectorAll('.lm-thumb').forEach(t => t.classList.toggle('is-active', t === button));
    });
  });

  const modal = $('lmSizeModal'), openModal = $('lmOpenSizeGuide'), closeModal = $('lmCloseSizeGuide');
  let lastFocus = null;
  function openGuide() { lastFocus = document.activeElement; modal.hidden = false; document.body.classList.add('lm-modal-open'); closeModal.focus(); }
  function closeGuide() { modal.hidden = true; document.body.classList.remove('lm-modal-open'); if (lastFocus) lastFocus.focus(); }
  openModal?.addEventListener('click', openGuide);
  closeModal?.addEventListener('click', closeGuide);
  modal?.addEventListener('click', e => { if (e.target === modal) closeGuide(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal && !modal.hidden) closeGuide(); });

  if (colors().length) {
    const first = colors()[0];
    colorId = first.colorId; colorLabel.textContent = first.colorName;
    renderColors(); renderSizes(); syncVariant();
  }
});

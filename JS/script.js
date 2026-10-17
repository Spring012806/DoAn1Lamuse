const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

const toast = (message) => {
  const el = $('#toast');
  el.textContent = message;
  el.classList.add('show');
  window.clearTimeout(toast.timer);
  toast.timer = window.setTimeout(() => el.classList.remove('show'), 2600);
};

// Mobile navigation
const header = $('.site-header');
$('.menu-toggle').addEventListener('click', () => {
  const opened = header.classList.toggle('menu-open');
  $('.menu-toggle').setAttribute('aria-expanded', String(opened));
});
$$('.nav a').forEach(link => link.addEventListener('click', () => header.classList.remove('menu-open')));

// Search panel filters the visible product cards
$('.search-toggle').addEventListener('click', () => {
  $('#search-form').classList.toggle('open');
  if ($('#search-form').classList.contains('open')) $('#search-input').focus();
});
$('.close-search').addEventListener('click', () => $('#search-form').classList.remove('open'));
$('#search-form').addEventListener('submit', event => {
  event.preventDefault();
  const query = $('#search-input').value.trim().toLocaleLowerCase('vi');
  let count = 0;
  $$('.product-card').forEach(card => {
    const matches = !query || card.dataset.name.toLocaleLowerCase('vi').includes(query);
    card.hidden = !matches;
    if (matches) count++;
  });
  $('#products').scrollIntoView({ behavior: 'smooth' });
  $('#search-form').classList.remove('open');
  toast(query ? `Tìm thấy ${count} sản phẩm phù hợp.` : 'Đang hiển thị tất cả sản phẩm.');
});

// Simple cart demo
let cartCount = 0;
$$('.quick-add').forEach(button => button.addEventListener('click', () => {
  cartCount++;
  $('#cart-count').textContent = cartCount;
  toast(`Đã thêm “${button.dataset.product}” vào giỏ hàng.`);
}));
$('#cart-button').addEventListener('click', () => {
  toast(cartCount ? `Giỏ hàng của nàng có ${cartCount} sản phẩm.` : 'Giỏ hàng vẫn đang chờ nàng chọn một món yêu thích.');
});

// Favourite buttons
$$('.heart').forEach(button => button.addEventListener('click', () => {
  const liked = button.classList.toggle('liked');
  button.textContent = liked ? '♥' : '♡';
  button.setAttribute('aria-pressed', String(liked));
  toast(liked ? 'Đã lưu vào danh sách yêu thích.' : 'Đã bỏ khỏi danh sách yêu thích.');
}));

// Account placeholder: replace with your real login/register page when backend is ready
$('#account-button').addEventListener('click', () => {
  const current = $('#account-label').textContent;
  if (current === 'Đăng nhập') {
    const name = window.prompt('Tên hiển thị của nàng (để thử giao diện đăng nhập):');
    if (name && name.trim()) {
      $('#account-label').textContent = name.trim();
      toast(`Xin chào, ${name.trim()}. Chức năng này hiện là bản demo giao diện.`);
    }
  } else {
    toast(`Xin chào ${current} — đây là bản demo tài khoản.`);
  }
});

// Newsletter demo
$('#newsletter-form').addEventListener('submit', event => {
  event.preventDefault();
  const email = $('#newsletter-email').value.trim();
  if (email) {
    $('#newsletter-message').textContent = 'Cảm ơn nàng. Lá thư đầu tiên sẽ tìm đường đến hộp thư của bạn.';
    $('#newsletter-email').value = '';
    toast('Đăng ký bản tin thành công (bản demo).');
  }
});

// Hero slides: change background crop/image without a heavy slider dependency

const heroImages = [
  './images/wallpaper1.jpg',
  './images/wallpaper2.jpg',
  './images/wallpaper3.jpg'
];

let heroIndex = 0;

function setHero(index) {
  heroIndex = (index + heroImages.length) % heroImages.length;

  const image = $('.hero-image');
  image.style.backgroundImage = `url("${heroImages[heroIndex]}")`;

  $('.hero-pagination').innerHTML = heroImages.map((_, i) =>
    `<span style="opacity:${i === heroIndex ? 1 : .55}">${String(i + 1).padStart(2, '0')}</span>${i < heroImages.length - 1 ? '<i></i>' : ''}`
  ).join('');
}

setHero(0);

$('.hero-arrow.prev').addEventListener('click', () => setHero(heroIndex - 1));
$('.hero-arrow.next').addEventListener('click', () => setHero(heroIndex + 1));



/* Hero tự động chuyển ảnh mỗi 6,5 giây */
let heroAutoTimer = setInterval(() => {
  setHero(heroIndex + 1);
}, 6500);

// Tạm dừng khi rê chuột vào banner
const heroSection = document.querySelector('.hero');

heroSection.addEventListener('mouseenter', () => {
  clearInterval(heroAutoTimer);
});

heroSection.addEventListener('mouseleave', () => {
  clearInterval(heroAutoTimer);
  heroAutoTimer = setInterval(() => {
    setHero(heroIndex + 1);
  }, 6500);
});

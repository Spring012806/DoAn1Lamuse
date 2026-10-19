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



/* Search: gửi từ khóa đến timkiem.php */
const searchToggle = $('.search-toggle');
const searchForm = $('#search-form');
const searchInput = $('#search-input');
const closeSearch = $('.close-search');

if (searchToggle && searchForm && searchInput) {
  searchToggle.addEventListener('click', () => {
    const opened = searchForm.classList.toggle('open');

    if (opened) {
      searchInput.focus();
    }
  });

  if (closeSearch) {
    closeSearch.addEventListener('click', () => {
      searchForm.classList.remove('open');
    });
  }

  searchForm.addEventListener('submit', (event) => {
    const keyword = searchInput.value.trim();

    if (keyword.length < 2) {
      event.preventDefault();
      searchInput.focus();
      return;
    }

    // Từ khóa hợp lệ: để trình duyệt tự gửi form GET.
  });
}



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


/* Tài khoản: chuyển đến trang đăng nhập thật */
const accountButton = $('#account-button');

if (accountButton) {
  // Nếu là thẻ a, trình duyệt tự chuyển trang theo thuộc tính href.
  // Không dùng prompt demo nữa.
  accountButton.addEventListener('click', (event) => {
    if (accountButton.tagName.toLowerCase() === 'a') {
      return;
    }

    // Hỗ trợ nếu header vẫn đang dùng button.
    event.preventDefault();
    window.location.href = 'login.php';
  });
}


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

// Hero slides: chỉ chạy ở trang có banner
const heroImages = [
  './images/wallpaper1.jpg',
  './images/wallpaper2.jpg',
  './images/wallpaper3.jpg'
];

const heroImage = $('.hero-image');
const heroPagination = $('.hero-pagination');
const heroSection = $('.hero');
const heroPrev = $('.hero-arrow.prev');
const heroNext = $('.hero-arrow.next');

if (heroImage && heroPagination && heroSection && heroPrev && heroNext) {
  let heroIndex = 0;
  let heroAutoTimer;

  function setHero(index) {
    heroIndex = (index + heroImages.length) % heroImages.length;
    heroImage.style.backgroundImage =
      `url("${heroImages[heroIndex]}")`;

    heroPagination.innerHTML = heroImages.map((_, i) =>
      `<span style="opacity:${i === heroIndex ? 1 : 0.55}">
        ${String(i + 1).padStart(2, '0')}
      </span>${i < heroImages.length - 1 ? '<i></i>' : ''}`
    ).join('');
  }

  function startAutoSlide() {
    clearInterval(heroAutoTimer);
    heroAutoTimer = setInterval(() => {
      setHero(heroIndex + 1);
    }, 6500);
  }

  setHero(0);
  startAutoSlide();

  heroPrev.addEventListener('click', () => setHero(heroIndex - 1));
  heroNext.addEventListener('click', () => setHero(heroIndex + 1));

  heroSection.addEventListener('mouseenter', () => {
    clearInterval(heroAutoTimer);
  });

  heroSection.addEventListener('mouseleave', startAutoSlide);
}

document.addEventListener("DOMContentLoaded", function () {
  const track = document.getElementById("aboutGalleryTrack");
  const prevBtn = document.getElementById("aboutGalleryPrev");
  const nextBtn = document.getElementById("aboutGalleryNext");
  const dotsContainer = document.getElementById("aboutGalleryDots");

  // Kiểm tra đúng các phần tử HTML của gallery La Muse.
  if (!track || !prevBtn || !nextBtn || !dotsContainer) {
    console.error("La Muse: Không tìm thấy thành phần gallery.");
    return;
  }

  const items = Array.from(track.querySelectorAll(".lm-about-gallery-item"));

  if (items.length === 0) {
    console.error("La Muse: Không có ảnh trong gallery.");
    return;
  }

  let currentIndex = 0;
  let autoPlay = null;

  // Khớp với breakpoint trong CSS của trang Giới thiệu.
  function getVisibleCount() {
    if (window.innerWidth <= 520) return 1;
    if (window.innerWidth <= 800) return 2;
    return 3;
  }

  function getMaxIndex() {
    return Math.max(0, items.length - getVisibleCount());
  }

  // Lấy chính xác khoảng cách giữa các ảnh từ CSS.
  function getStep() {
    const itemWidth = items[0].getBoundingClientRect().width;
    const styles = window.getComputedStyle(track);
    const gap = parseFloat(styles.columnGap) || 0;

    return itemWidth + gap;
  }

  function renderDots() {
    dotsContainer.replaceChildren();

    for (let i = 0; i <= getMaxIndex(); i++) {
      const dot = document.createElement("button");

      dot.type = "button";
      dot.className =
        "lm-about-gallery-dot" + (i === currentIndex ? " is-active" : "");

      dot.setAttribute("aria-label", "Hiển thị nhóm ảnh " + (i + 1));

      dot.addEventListener("click", function () {
        currentIndex = i;
        updateGallery();
        startAutoPlay();
      });

      dotsContainer.appendChild(dot);
    }
  }

  function updateGallery() {
    currentIndex = Math.max(0, Math.min(currentIndex, getMaxIndex()));

    track.style.transform = "translateX(-" + currentIndex * getStep() + "px)";

    prevBtn.disabled = currentIndex === 0;
    nextBtn.disabled = currentIndex >= getMaxIndex();

    // Cập nhật trạng thái chấm điều hướng.
    Array.from(dotsContainer.children).forEach(function (dot, i) {
      dot.classList.toggle("is-active", i === currentIndex);
    });
  }

  // Chuyển sang nhóm ảnh kế tiếp, quay về đầu khi hết ảnh.
  function nextSlide() {
    currentIndex = currentIndex >= getMaxIndex() ? 0 : currentIndex + 1;

    updateGallery();
  }

  // Chuyển về nhóm ảnh trước, quay về cuối nếu đang ở đầu.
  function prevSlide() {
    currentIndex = currentIndex <= 0 ? getMaxIndex() : currentIndex - 1;

    updateGallery();
  }

  function startAutoPlay() {
    clearInterval(autoPlay);

    // Chỉ tự chạy khi có nhiều hơn một vị trí hiển thị.
    if (getMaxIndex() > 0) {
      autoPlay = setInterval(nextSlide, 3000);
    }
  }

  prevBtn.addEventListener("click", function () {
    prevSlide();
    startAutoPlay();
  });

  nextBtn.addEventListener("click", function () {
    nextSlide();
    startAutoPlay();
  });

  // Tạm dừng tự chạy khi rê chuột vào gallery.
  track.addEventListener("mouseenter", function () {
    clearInterval(autoPlay);
  });

  track.addEventListener("mouseleave", function () {
    startAutoPlay();
  });

  // Tính toán lại khi đổi kích thước màn hình.
  window.addEventListener("resize", function () {
    renderDots();
    updateGallery();
    startAutoPlay();
  });

  renderDots();
  updateGallery();
  startAutoPlay();
});

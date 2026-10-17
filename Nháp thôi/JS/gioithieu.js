const track = document.getElementById("galleryTrack");
const prevBtn = document.getElementById("prevBtn");
const nextBtn = document.getElementById("nextBtn");
const indicators = document.getElementById("galleryIndicators");

if (track && prevBtn && nextBtn && indicators) {
  const items = Array.from(track.querySelectorAll(".gallery-item"));

  let currentIndex = 0;
  let autoPlay;

  function getVisibleCount() {
    if (window.innerWidth <= 480) return 1;
    if (window.innerWidth <= 768) return 2;
    return 3;
  }

  function getMaxIndex() {
    return Math.max(0, items.length - getVisibleCount());
  }

  function renderDots() {
    indicators.innerHTML = "";

    for (let i = 0; i <= getMaxIndex(); i++) {
      const dot = document.createElement("button");
      dot.className = "gallery-dot";
      dot.setAttribute("aria-label", `Đến vị trí ${i + 1}`);

      dot.addEventListener("click", () => {
        currentIndex = i;
        updateGallery();
        startAutoPlay();
      });

      indicators.appendChild(dot);
    }
  }

  function updateGallery() {
    if (items.length === 0) return;

    currentIndex = Math.max(0, Math.min(currentIndex, getMaxIndex()));

    const itemWidth = items[0].getBoundingClientRect().width;
    const gap = parseFloat(getComputedStyle(track).gap) || 0;

    track.style.transform = `translateX(-${currentIndex * (itemWidth + gap)}px)`;

    prevBtn.disabled = currentIndex === 0;
    nextBtn.disabled = currentIndex === getMaxIndex();

    prevBtn.style.opacity = prevBtn.disabled ? "0.4" : "1";
    nextBtn.style.opacity = nextBtn.disabled ? "0.4" : "1";

    Array.from(indicators.children).forEach((dot, i) => {
      dot.classList.toggle("active", i === currentIndex);
    });
  }

  function nextSlide() {
    if (currentIndex < getMaxIndex()) {
      currentIndex++;
    } else {
      currentIndex = 0;
    }

    updateGallery();
  }

  function prevSlide() {
    if (currentIndex > 0) {
      currentIndex--;
    } else {
      currentIndex = getMaxIndex();
    }

    updateGallery();
  }

  function startAutoPlay() {
    clearInterval(autoPlay);
    autoPlay = setInterval(nextSlide, 3000);
  }

  prevBtn.addEventListener("click", () => {
    prevSlide();
    startAutoPlay();
  });

  nextBtn.addEventListener("click", () => {
    nextSlide();
    startAutoPlay();
  });

  track.addEventListener("mouseenter", () => {
    clearInterval(autoPlay);
  });

  track.addEventListener("mouseleave", startAutoPlay);

  window.addEventListener("resize", () => {
    renderDots();
    updateGallery();
  });

  renderDots();
  updateGallery();
  startAutoPlay();
}

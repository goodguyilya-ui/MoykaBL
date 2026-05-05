(() => {
  const slider = document.getElementById("slider");
  if (!slider) {
    return;
  }

  const slides = Array.from(slider.querySelectorAll(".slide"));
  const prevBtn = slider.querySelector(".prev");
  const nextBtn = slider.querySelector(".next");
  let index = slides.findIndex((slide) => slide.classList.contains("is-active"));
  if (index < 0) {
    index = 0;
    slides[0]?.classList.add("is-active");
  }

  function syncVisibility() {
    slides.forEach((slide, i) => {
      const isActive = i === index;
      slide.classList.toggle("is-active", isActive);
      slide.style.display = isActive ? "block" : "none";
    });
  }

  function show(nextIndex) {
    index = (nextIndex + slides.length) % slides.length;
    syncVisibility();
  }

  function goNext() {
    show(index + 1);
  }

  function goPrev() {
    show(index - 1);
  }

  nextBtn?.addEventListener("click", goNext);
  prevBtn?.addEventListener("click", goPrev);
  syncVisibility();
  setInterval(goNext, 3000);
})();

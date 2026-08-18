(function () {
    function initAnnonceSlider() {
        const slider = document.getElementById('annonceBgSlider');
        if (!slider || slider.dataset.bound === '1') return;
        slider.dataset.bound = '1';
        const slides = Array.from(slider.querySelectorAll('.vip-jobs-bg-slide'));
        if (slides.length < 2) return;
        let current = 0;
        const show = function (index) {
            current = (index + slides.length) % slides.length;
            slides.forEach(function (slide, position) { slide.classList.toggle('active', position === current); });
        };
        show(0);
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            window.setInterval(function () { show(current + 1); }, 5200);
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAnnonceSlider);
    else initAnnonceSlider();
    document.addEventListener('turbo:load', initAnnonceSlider);
})();

// ── Menu hamburger ─────────────────────────────────────
function toggleMenu() {
    document.getElementById('main-nav').classList.toggle('open');
}
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.main-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            document.getElementById('main-nav').classList.remove('open');
        });
    });
});

// ── Lightbox ───────────────────────────────────────────
var _lbImages = [];
var _lbIndex  = 0;

function openLightbox(el) {
    var grid = el.closest('[data-gallery]');
    _lbImages = JSON.parse(grid.dataset.gallery);
    _lbIndex  = parseInt(el.dataset.index, 10);

    var items = grid.querySelectorAll('img');
    var caps  = Array.from(items).map(function (img) { return img.alt || ''; });

    _lbShow(caps);
}

function _lbShow(caps) {
    var lb  = document.getElementById('lightbox');
    var img = document.getElementById('lightbox-img');
    var cap = document.getElementById('lb-caption');

    img.src = _lbImages[_lbIndex];
    if (cap) cap.textContent = (caps && caps[_lbIndex]) ? caps[_lbIndex] : '';
    lb.classList.add('open');
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
}

function lbPrev(e) {
    e.stopPropagation();
    _lbIndex = (_lbIndex - 1 + _lbImages.length) % _lbImages.length;
    document.getElementById('lightbox-img').src = _lbImages[_lbIndex];
    var cap = document.getElementById('lb-caption');
    if (cap) cap.textContent = '';
}

function lbNext(e) {
    e.stopPropagation();
    _lbIndex = (_lbIndex + 1) % _lbImages.length;
    document.getElementById('lightbox-img').src = _lbImages[_lbIndex];
    var cap = document.getElementById('lb-caption');
    if (cap) cap.textContent = '';
}

// Keyboard navigation
document.addEventListener('keydown', function (e) {
    if (!document.getElementById('lightbox').classList.contains('open')) return;
    if (e.key === 'ArrowLeft')  lbPrev(e);
    if (e.key === 'ArrowRight') lbNext(e);
    if (e.key === 'Escape')     closeLightbox();
});

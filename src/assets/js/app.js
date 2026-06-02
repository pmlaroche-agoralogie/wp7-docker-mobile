function toggleMenu() {
    document.getElementById('main-nav').classList.toggle('open');
}

// Close menu when a nav link is clicked (mobile UX)
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.main-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            document.getElementById('main-nav').classList.remove('open');
        });
    });
});

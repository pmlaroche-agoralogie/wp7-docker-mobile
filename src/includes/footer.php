</main>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <a href="/informations-legales" class="footer-copyright-link"><?= SITE_NAME ?></a></p>
</footer>

<!-- Bannière d'information cookies -->
<div id="cookie-banner" style="display:none" role="dialog" aria-label="Information cookies" aria-live="polite">
    <p>
        Ce site ne g&egrave;re que trois cookies strictement r&eacute;serv&eacute;s au fonctionnement du site.
        <a href="/informations-legales">Cliquer ici</a> pour en savoir plus.
    </p>
    <button id="cookie-banner-ok" onclick="cookieBannerDismiss()">OK</button>
</div>

<!-- Lightbox (partagé pour toutes les galeries) -->
<div id="lightbox" onclick="closeLightbox()">
    <button id="lb-close" onclick="closeLightbox()" title="Fermer (Échap)">&times;</button>
    <button class="lb-btn" id="lb-prev" onclick="lbPrev(event)" title="Précédent">&#8249;</button>
    <img id="lightbox-img" src="" alt="">
    <button class="lb-btn" id="lb-next" onclick="lbNext(event)" title="Suivant">&#8250;</button>
    <div id="lb-caption"></div>
</div>

<script src="/assets/js/app.js"></script>
<script>
(function () {
    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }
    if (!getCookie('cookie_notice_seen')) {
        document.getElementById('cookie-banner').style.display = 'flex';
    }
})();

function cookieBannerDismiss() {
    var expires = new Date(Date.now() + 365 * 24 * 3600 * 1000).toUTCString();
    document.cookie = 'cookie_notice_seen=1; path=/; expires=' + expires + '; SameSite=Lax';
    document.getElementById('cookie-banner').style.display = 'none';
}
</script>
</body>
</html>

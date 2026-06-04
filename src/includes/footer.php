</main>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?></p>
</footer>

<!-- Lightbox (partagé pour toutes les galeries) -->
<div id="lightbox" onclick="closeLightbox()">
    <button id="lb-close" onclick="closeLightbox()" title="Fermer (Échap)">&times;</button>
    <button class="lb-btn" id="lb-prev" onclick="lbPrev(event)" title="Précédent">&#8249;</button>
    <img id="lightbox-img" src="" alt="">
    <button class="lb-btn" id="lb-next" onclick="lbNext(event)" title="Suivant">&#8250;</button>
    <div id="lb-caption"></div>
</div>

<script src="/assets/js/app.js"></script>
</body>
</html>

<?php
requireLogin();
$pageTitle = 'Mon espace — ' . SITE_NAME;
$user = getCurrentUser();
$db   = getDB();

$meteoRow = $db->prepare("SELECT meteo_code_insee FROM users WHERE id = ?");
$meteoRow->execute([$user['id']]);
$meteoCodeInsee = $meteoRow->fetchColumn() ?: '64430';

$recentAnnonces = $db->query(
    "SELECT id, titre, texte, tag, prix, created_at FROM annonces WHERE visible = 1 ORDER BY created_at DESC LIMIT 3"
)->fetchAll();

$tagColors = [
    'matériels' => ['bg' => '#dbeafe', 'color' => '#1d4ed8'],
    'aliments'  => ['bg' => '#dcfce7', 'color' => '#15803d'],
    'animaux'   => ['bg' => '#fef9c3', 'color' => '#a16207'],
    'services'  => ['bg' => '#ede9fe', 'color' => '#6d28d9'],
    'divers'    => ['bg' => '#f1f5f9', 'color' => '#475569'],
];

include __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Bonjour, <?= htmlspecialchars($user['username']) ?></h1>

<div class="module-grid">

    <div class="module-card">
        <div class="mod-icon">&#128203;</div>
        <h3>Activit&eacute;s</h3>
        <p>Pr&eacute;sentation des activit&eacute;s r&eacute;centes dans l'intranet</p>
    </div>

    <div class="module-card" style="cursor:default;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.6rem;">
            <div style="display:flex; align-items:center; gap:.5rem;">
                <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#128226;</span>
                <h3 style="margin:0;">Annonces</h3>
            </div>
            <a href="/annonces" style="font-size:.78rem; color:var(--primary); text-decoration:none; white-space:nowrap;">Toutes &rarr;</a>
        </div>
        <?php if (empty($recentAnnonces)): ?>
            <p style="color:var(--muted); font-size:.82rem; font-style:italic;">Aucune annonce pour le moment.</p>
        <?php else: ?>
            <?php foreach ($recentAnnonces as $a):
                $tc = $tagColors[$a['tag']] ?? $tagColors['divers'];
            ?>
            <a href="/annonce?id=<?= $a['id'] ?>" class="annonce-home-item" style="padding:.45rem 0;">
                <div class="annonce-home-header">
                    <span class="annonce-tag" style="background:<?= $tc['bg'] ?>; color:<?= $tc['color'] ?>; font-size:.65rem;">
                        <?= htmlspecialchars($a['tag']) ?>
                    </span>
                    <?php if ($a['prix'] !== null): ?>
                        <span style="font-size:.78rem; color:var(--primary); font-weight:700; margin-left:auto;">
                            <?= number_format((float)$a['prix'], 2, ',', '&thinsp;') ?>&thinsp;&euro;
                        </span>
                    <?php endif; ?>
                </div>
                <h3 style="font-size:.82rem;"><?= htmlspecialchars($a['titre']) ?></h3>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="module-card">
        <div class="mod-icon">&#128193;</div>
        <h3>Fichiers</h3>
        <p>Les 3 derniers fichiers disponibles</p>
    </div>

    <div class="module-card">
        <div class="mod-icon">&#9993;</div>
        <h3>Messagerie</h3>
        <p>Vos derniers messages re&ccedil;us</p>
    </div>

    <div class="module-card meteo-card" style="cursor:default;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.6rem;">
            <div style="display:flex; align-items:center; gap:.5rem;">
                <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#127780;</span>
                <h3 style="margin:0;">M&eacute;t&eacute;o</h3>
            </div>
            <button id="meteo-btn-custom" onclick="meteoToggleForm()"
                style="font-size:.72rem; color:var(--primary); background:none; border:none; cursor:pointer; text-decoration:underline; padding:0;">
                Personnaliser
            </button>
        </div>

        <iframe id="meteo-iframe"
            width="100%" height="290" frameborder="0"
            src="https://meteofrance.com/widget/prevision/<?= htmlspecialchars($meteoCodeInsee) ?>0##3D6AA2"
            title="Pr&eacute;visions M&eacute;t&eacute;o-France"
            style="border-radius:8px; display:block;">
        </iframe>

        <div id="meteo-form" style="display:none; margin-top:.75rem;">
            <label style="font-size:.8rem; color:var(--muted); display:block; margin-bottom:.3rem;">
                Code postal
            </label>
            <div style="display:flex; gap:.4rem; flex-wrap:wrap;">
                <input id="meteo-cp" type="text" maxlength="5" placeholder="ex: 64300"
                    style="flex:1; min-width:90px; padding:.4rem .6rem; border:1px solid var(--border); border-radius:6px; font-size:.85rem;">
                <button onclick="meteoSearch()"
                    style="padding:.4rem .75rem; background:var(--primary); color:#fff; border:none; border-radius:6px; font-size:.82rem; cursor:pointer;">
                    Rechercher
                </button>
            </div>
            <div id="meteo-results" style="margin-top:.5rem;"></div>
            <div id="meteo-msg" style="font-size:.78rem; color:var(--muted); margin-top:.35rem;"></div>
        </div>
    </div>

    <script>
    function meteoToggleForm() {
        var f = document.getElementById('meteo-form');
        f.style.display = f.style.display === 'none' ? 'block' : 'none';
        document.getElementById('meteo-msg').textContent = '';
        document.getElementById('meteo-results').innerHTML = '';
    }

    function meteoSearch() {
        var cp = document.getElementById('meteo-cp').value.trim();
        if (!/^\d{5}$/.test(cp)) {
            document.getElementById('meteo-msg').textContent = 'Saisissez un code postal à 5 chiffres.';
            return;
        }
        document.getElementById('meteo-msg').textContent = 'Recherche…';
        document.getElementById('meteo-results').innerHTML = '';

        fetch('https://geo.api.gouv.fr/communes?codePostal=' + encodeURIComponent(cp) + '&fields=code,nom&format=json')
            .then(function(r) { return r.json(); })
            .then(function(communes) {
                document.getElementById('meteo-msg').textContent = '';
                if (!communes.length) {
                    document.getElementById('meteo-msg').textContent = 'Aucune commune trouvée pour ce code postal.';
                    return;
                }
                var html = '';
                communes.forEach(function(c) {
                    html += '<button onclick="meteoSave(\'' + c.code + '\', \'' + c.nom.replace(/'/g, "\\'") + '\')" '
                          + 'style="display:block; width:100%; text-align:left; padding:.35rem .6rem; margin-bottom:.3rem; '
                          + 'border:1px solid var(--border); border-radius:6px; background:var(--bg); cursor:pointer; font-size:.82rem;">'
                          + c.nom + '</button>';
                });
                document.getElementById('meteo-results').innerHTML = html;
            })
            .catch(function() {
                document.getElementById('meteo-msg').textContent = 'Erreur lors de la recherche. Réessayez.';
            });
    }

    function meteoSave(codeInsee, nomCommune) {
        document.getElementById('meteo-msg').textContent = 'Enregistrement…';
        fetch('/api/meteo-set', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code_insee: codeInsee })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                document.getElementById('meteo-msg').textContent = '';
                document.getElementById('meteo-form').style.display = 'none';
                document.getElementById('meteo-iframe').src =
                    'https://meteofrance.com/widget/prevision/' + codeInsee + '0##3D6AA2';
            } else {
                document.getElementById('meteo-msg').textContent = data.error || 'Erreur inconnue.';
            }
        })
        .catch(function() {
            document.getElementById('meteo-msg').textContent = 'Erreur réseau. Réessayez.';
        });
    }
    </script>

    <div class="module-card" onclick="location.href='/produits'">
        <div class="mod-icon">&#128722;</div>
        <h3>Commandes</h3>
        <p>Catalogue de produits et panier de commandes</p>
    </div>

    <?php if ($user['role'] === 'admin'): ?>

    <div class="module-card" onclick="location.href='/admin/users'">
        <div class="mod-icon">&#128101;</div>
        <h3>Gestion utilisateurs</h3>
        <p>Liste, ajout, groupes, activation</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/pages'">
        <div class="mod-icon">&#128196;</div>
        <h3>Pages de contenu</h3>
        <p>&Eacute;diteur WYSIWYG des pages publiques</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/produits'">
        <div class="mod-icon">&#128230;</div>
        <h3>Produits</h3>
        <p>Gestion du catalogue, fiches, photos</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/commandes'">
        <div class="mod-icon">&#128203;</div>
        <h3>Commandes re&ccedil;ues</h3>
        <p>Suivi et livraison des commandes &eacute;leveurs</p>
    </div>

    <?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

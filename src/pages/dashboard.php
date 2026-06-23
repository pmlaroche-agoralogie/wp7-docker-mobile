<?php
requireLogin();
$pageTitle = 'Mon espace — ' . SITE_NAME;
$user = getCurrentUser();
$db   = getDB();

$meteoRow = $db->prepare("SELECT meteo_code_insee FROM users WHERE id = ?");
$meteoRow->execute([$user['id']]);
$meteoCodeInsee = $meteoRow->fetchColumn() ?: '64430';

// Admin : compteur réponses non lues
$adminUnreadReplies = 0;
if ($user['role'] === 'admin') {
    $adminUnreadReplies = (int)$db->query("SELECT COUNT(*) FROM message_replies WHERE read_by_admin = 0")->fetchColumn();
}

// --- Points rouges : nouveautés depuis le dernier passage ---

// Messagerie : messages non lus
$unreadCountStmt = $db->prepare("SELECT COUNT(*) FROM message_recipients WHERE user_id = ? AND read_at IS NULL");
$unreadCountStmt->execute([$user['id']]);
$messagesNewDot = (int)$unreadCountStmt->fetchColumn() > 0;

// Cotations : nouveau PDF depuis dernier passage
$cotationsDir  = '/var/www/html/media/cotations';
$latestPdfTime = 0;
if (is_dir($cotationsDir)) {
    foreach (glob($cotationsDir . '/*.pdf') as $file) {
        $mtime = filemtime($file);
        if ($mtime > $latestPdfTime) $latestPdfTime = $mtime;
    }
}
$lastVisitCotations = getLastPageVisit($user['id'], 'cotations');
$cotationsNewDot = $latestPdfTime > 0 &&
    (!$lastVisitCotations || $latestPdfTime > strtotime($lastVisitCotations));

// Annonces : nouvelle annonce depuis dernier passage
$lastVisitAnnonces  = getLastPageVisit($user['id'], 'annonces');
$latestAnnonceAt    = $db->query("SELECT MAX(created_at) FROM annonces WHERE visible = 1")->fetchColumn();
$annoncesNewDot     = $latestAnnonceAt &&
    (!$lastVisitAnnonces || $latestAnnonceAt > $lastVisitAnnonces);

// Fichiers personnels : nouveau fichier depuis dernier passage
$lastVisitFichiers = getLastPageVisit($user['id'], 'fichiers');
$fichiersNewDot    = false;
if ($lastVisitFichiers) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM files_bo_name WHERE owner_id = ? AND group_id IS NULL AND created_at > ?");
    $stmt->execute([$user['id'], $lastVisitFichiers]);
    $fichiersNewDot = (int)$stmt->fetchColumn() > 0;
}

// Fichiers de groupe : nouveau fichier dans les groupes de l'utilisateur
$lastVisitFichiersGroupes = getLastPageVisit($user['id'], 'fichiers-groupes');
$fichiersGroupesNewDot    = false;
if ($lastVisitFichiersGroupes) {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM files_bo_name f
        JOIN files_bo_group_members m ON m.group_id = f.group_id AND m.user_id = ?
        WHERE f.created_at > ?
    ");
    $stmt->execute([$user['id'], $lastVisitFichiersGroupes]);
    $fichiersGroupesNewDot = (int)$stmt->fetchColumn() > 0;
}

// Sponsors : 2 tirés au sort parmi les actifs
$allSponsors = $db->query("SELECT * FROM sponsors WHERE active = 1")->fetchAll();
shuffle($allSponsors);
$displayedSponsors = array_slice($allSponsors, 0, 2);

include __DIR__ . '/../includes/header.php';
?>
<div class="module-grid">

    <div class="module-card" onclick="location.href='/messages'" style="cursor:pointer;">
        <div style="display:flex; align-items:center; gap:.5rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                 style="flex-shrink:0; color:var(--primary);">
                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                <polyline points="2,4 12,13 22,4"></polyline>
            </svg>
            <h3 style="margin:0;">Messagerie</h3>
            <?php if ($messagesNewDot): ?>
                <span style="margin-left:auto; display:flex; align-items:center; gap:.35rem; flex-shrink:0;">
                    <span style="font-size:.68rem; color:#ef4444; font-weight:600; white-space:nowrap;">il y a du nouveau</span>
                    <span style="width:9px; height:9px; border-radius:50%; background:#ef4444; flex-shrink:0; display:inline-block;"></span>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="module-card" onclick="location.href='/cotations'" style="cursor:pointer;">
        <div style="display:flex; align-items:center; gap:.5rem;">
            <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#128196;</span>
            <h3 style="margin:0;">Cotations</h3>
            <?php if ($cotationsNewDot): ?>
                <span style="margin-left:auto; display:flex; align-items:center; gap:.35rem; flex-shrink:0;">
                    <span style="font-size:.68rem; color:#ef4444; font-weight:600; white-space:nowrap;">il y a du nouveau</span>
                    <span style="width:9px; height:9px; border-radius:50%; background:#ef4444; flex-shrink:0; display:inline-block;"></span>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="module-card" onclick="location.href='/annonces'" style="cursor:pointer;">
        <div style="display:flex; align-items:center; gap:.5rem;">
            <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#128668;</span>
            <h3 style="margin:0;">Annonces</h3>
            <?php if ($annoncesNewDot): ?>
                <span style="margin-left:auto; display:flex; align-items:center; gap:.35rem; flex-shrink:0;">
                    <span style="font-size:.68rem; color:#ef4444; font-weight:600; white-space:nowrap;">il y a du nouveau</span>
                    <span style="width:9px; height:9px; border-radius:50%; background:#ef4444; flex-shrink:0; display:inline-block;"></span>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="module-card" onclick="location.href='/produits'">
        <div style="display:flex; align-items:center; gap:.5rem;">
            <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#128722;</span>
            <h3 style="margin:0;">Boutique</h3>
        </div>
    </div>

    <div class="module-card" onclick="location.href='/fichiers'" style="cursor:pointer;">
        <div style="display:flex; align-items:center; gap:.5rem;">
            <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#128193;</span>
            <h3 style="margin:0;">Personnel</h3>
            <?php if ($fichiersNewDot): ?>
                <span style="margin-left:auto; display:flex; align-items:center; gap:.35rem; flex-shrink:0;">
                    <span style="font-size:.68rem; color:#ef4444; font-weight:600; white-space:nowrap;">il y a du nouveau</span>
                    <span style="width:9px; height:9px; border-radius:50%; background:#ef4444; flex-shrink:0; display:inline-block;"></span>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="module-card" onclick="location.href='/fichiers-groupes'" style="cursor:pointer;">
        <div style="display:flex; align-items:center; gap:.5rem;">
            <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#128450;</span>
            <h3 style="margin:0;">Général</h3>
            <?php if ($fichiersGroupesNewDot): ?>
                <span style="margin-left:auto; display:flex; align-items:center; gap:.35rem; flex-shrink:0;">
                    <span style="font-size:.68rem; color:#ef4444; font-weight:600; white-space:nowrap;">il y a du nouveau</span>
                    <span style="width:9px; height:9px; border-radius:50%; background:#ef4444; flex-shrink:0; display:inline-block;"></span>
                </span>
            <?php endif; ?>
        </div>
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

    <?php if (!empty($displayedSponsors)): ?>
    <div class="module-card" style="cursor:default;">
        <div style="display:flex; align-items:center; justify-content:<?= count($displayedSponsors) > 1 ? 'space-around' : 'center' ?>; gap:1rem; flex-wrap:wrap;">
            <?php foreach ($displayedSponsors as $sp): ?>
            <a href="<?= htmlspecialchars($sp['url']) ?>" target="_blank" rel="noopener noreferrer"
               style="display:block; flex:1; min-width:80px; max-width:120px; text-align:center;">
                <img src="/media/sponsors/<?= htmlspecialchars($sp['logo']) ?>"
                     alt="<?= htmlspecialchars($sp['name']) ?>"
                     style="max-width:100%; max-height:60px; object-fit:contain;">
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

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

    <div class="module-card" onclick="location.href='/admin/cotations'">
        <div class="mod-icon">&#128196;</div>
        <h3>Cotations</h3>
        <p>Gestion des PDF de cotations march&eacute;</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/annonces'">
        <div class="mod-icon">&#128668;</div>
        <h3>Annonces</h3>
        <p>Gestion des petites annonces &eacute;leveurs</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/fichiers-groupes'">
        <div class="mod-icon">&#128101;</div>
        <h3>Groupes de fichiers</h3>
        <p>Cr&eacute;er des groupes, g&eacute;rer les membres</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/messages'" style="position:relative;">
        <div class="mod-icon">&#9993;</div>
        <h3>Messagerie</h3>
        <p>Envoyer des messages, consulter les r&eacute;ponses</p>
        <?php if ($adminUnreadReplies > 0): ?>
            <div style="margin-top:.4rem;">
                <span style="background:#ef4444; color:#fff; border-radius:999px; padding:.15rem .55rem; font-size:.72rem; font-weight:700;">
                    <?= $adminUnreadReplies ?> r&eacute;ponse<?= $adminUnreadReplies > 1 ? 's' : '' ?> non lue<?= $adminUnreadReplies > 1 ? 's' : '' ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <div class="module-card" onclick="location.href='/admin/sponsors'">
        <div class="mod-icon">&#127775;</div>
        <h3>Sponsors</h3>
        <p>Ajouter, activer ou supprimer des sponsors</p>
    </div>

    <?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

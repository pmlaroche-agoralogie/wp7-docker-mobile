<?php
requireAdmin();
$db = getDB();

$blockId   = isset($_GET['id']) ? (int)$_GET['id'] : ((int)($_POST['block_id'] ?? 0) ?: null);
$pageSlug  = $_GET['page']  ?? ($_POST['page_slug'] ?? null);
$blockType = $_GET['type']  ?? ($_POST['type']      ?? null);

// Load existing block when editing
$block = null;
if ($blockId) {
    $stmt = $db->prepare("SELECT * FROM page_blocks WHERE id = ?");
    $stmt->execute([$blockId]);
    $block = $stmt->fetch();
    if (!$block) { header('Location: /admin/pages'); exit; }
    $pageSlug  = $block['page_slug'];
    $blockType = $block['type'];
}

if (!$pageSlug || !in_array($blockType, ['html', 'gallery', 'video'])) {
    header('Location: /admin/pages'); exit;
}

// Load page
$stmt = $db->prepare("SELECT * FROM pages WHERE slug = ?");
$stmt->execute([$pageSlug]);
$page = $stmt->fetch();
if (!$page) { header('Location: /admin/pages'); exit; }

// ── POST: save ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type  = $_POST['type']     ?? $blockType;
    $pSlug = $_POST['page_slug'] ?? $pageSlug;
    $bId   = (int)($_POST['block_id'] ?? 0);

    $content = '';

    if ($type === 'html') {
        $content = $_POST['content'] ?? '';

    } elseif ($type === 'gallery') {
        $files    = $_POST['files']    ?? [];
        $captions = $_POST['captions'] ?? [];
        $remove   = $_POST['remove']   ?? [];
        $images   = [];
        foreach ($files as $i => $file) {
            if (!isset($remove[$i])) {
                $images[] = ['file' => $file, 'caption' => $captions[$i] ?? ''];
            }
        }
        // New uploads
        if (!empty($_FILES['new_images']['name'][0])) {
            $uploadDir = '/var/www/html/media/gallery/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            foreach ($_FILES['new_images']['tmp_name'] as $k => $tmp) {
                if ($_FILES['new_images']['error'][$k] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($_FILES['new_images']['name'][$k], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;
                $fname = uniqid('img_') . '.' . $ext;
                move_uploaded_file($tmp, $uploadDir . $fname);
                $images[] = ['file' => 'gallery/' . $fname, 'caption' => ''];
            }
        }
        $content = json_encode($images);

    } elseif ($type === 'video') {
        $src = trim($_POST['url'] ?? '');
        // File upload overrides URL
        if (!empty($_FILES['video_file']['tmp_name']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4', 'webm', 'ogg', 'mov'])) {
                $uploadDir = '/var/www/html/media/videos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fname = uniqid('vid_') . '.' . $ext;
                move_uploaded_file($_FILES['video_file']['tmp_name'], $uploadDir . $fname);
                $src = '/media/videos/' . $fname;
            }
        }
        $content = json_encode(['src' => $src, 'title' => trim($_POST['title'] ?? '')]);
    }

    if ($bId) {
        $db->prepare("UPDATE page_blocks SET content = ? WHERE id = ?")
           ->execute([$content, $bId]);
    } else {
        $pos = $db->prepare("SELECT COALESCE(MAX(position), -1) + 1 AS p FROM page_blocks WHERE page_slug = ?");
        $pos->execute([$pSlug]);
        $position = (int)$pos->fetch()['p'];
        $db->prepare("INSERT INTO page_blocks (page_slug, position, type, content) VALUES (?, ?, ?, ?)")
           ->execute([$pSlug, $position, $type, $content]);
    }

    header('Location: /admin/pages?edit=' . urlencode($pSlug) . '&saved=1');
    exit;
}

// ── Préparer les données pour le formulaire ──────────────
$htmlContent  = '';
$galleryImages = [];
$videoData    = ['src' => '', 'title' => ''];

if ($block) {
    if ($blockType === 'html')    $htmlContent   = $block['content'];
    if ($blockType === 'gallery') $galleryImages = json_decode($block['content'], true) ?? [];
    if ($blockType === 'video')   $videoData     = json_decode($block['content'], true) ?? $videoData;
}

$typeLabels = ['html' => 'Texte HTML', 'gallery' => 'Galerie photos', 'video' => 'Vidéo'];
$pageTitle  = ($block ? 'Modifier' : 'Ajouter') . ' — ' . $typeLabels[$blockType] . ' — ' . SITE_NAME;

if ($blockType === 'html') {
    $extraHead = '<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">';
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
    <h1 class="page-title" style="margin:0;">
        <?= $block ? 'Modifier' : 'Ajouter' ?> — <?= htmlspecialchars($typeLabels[$blockType]) ?>
    </h1>
    <a href="/admin/pages?edit=<?= urlencode($pageSlug) ?>" class="btn btn-sm">&larr; <?= htmlspecialchars($page['title']) ?></a>
</div>

<form method="post" enctype="multipart/form-data" id="block-form">
    <input type="hidden" name="type"      value="<?= htmlspecialchars($blockType) ?>">
    <input type="hidden" name="page_slug" value="<?= htmlspecialchars($pageSlug) ?>">
    <input type="hidden" name="block_id"  value="<?= $blockId ?? 0 ?>">

<?php if ($blockType === 'html'): ?>
<!-- ══ HTML (Quill) ══════════════════════════════════════ -->
    <div class="card" style="padding:0; overflow:hidden; margin-bottom:1rem;">
        <div id="quill-editor" style="min-height:400px; font-size:1rem;"></div>
    </div>
    <textarea name="content" id="content-input" hidden></textarea>

    <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
    <script>
    var quill = new Quill('#quill-editor', {
        theme: 'snow',
        modules: { toolbar: [
            [{ header: [2, 3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ color: [] }, { background: [] }],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'link', 'image'],
            ['clean']
        ]}
    });
    quill.clipboard.dangerouslyPasteHTML(<?= json_encode($htmlContent) ?>);
    document.getElementById('block-form').addEventListener('submit', function () {
        document.getElementById('content-input').value = quill.root.innerHTML;
    });
    </script>

<?php elseif ($blockType === 'gallery'): ?>
<!-- ══ Galerie ═══════════════════════════════════════════ -->
    <?php if ($galleryImages): ?>
    <div class="card" style="margin-bottom:1rem;">
        <h2 style="margin-bottom:.75rem;">Images existantes</h2>
        <div class="gallery-admin-grid">
        <?php foreach ($galleryImages as $i => $img): ?>
            <div class="gallery-admin-item">
                <img src="/media/<?= htmlspecialchars($img['file']) ?>" alt="">
                <input type="hidden" name="files[<?= $i ?>]" value="<?= htmlspecialchars($img['file']) ?>">
                <input type="text" name="captions[<?= $i ?>]"
                       value="<?= htmlspecialchars($img['caption'] ?? '') ?>"
                       placeholder="L&eacute;gende (optionnel)">
                <label class="remove-label">
                    <input type="checkbox" name="remove[<?= $i ?>]"> Supprimer
                </label>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:1rem;">
        <h2 style="margin-bottom:.75rem;">Ajouter des images</h2>
        <div class="form-group" style="margin-bottom:0;">
            <label>S&eacute;lectionnez des photos (JPG, PNG, WebP&hellip;)</label>
            <input type="file" name="new_images[]" multiple accept="image/*"
                   style="padding:.5rem 0;">
        </div>
        <p style="font-size:.8rem; color:var(--muted); margin-top:.5rem;">
            Vous pourrez ajouter des l&eacute;gendes apr&egrave;s enregistrement.
        </p>
    </div>

    <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>

<?php elseif ($blockType === 'video'): ?>
<!-- ══ Vidéo ═════════════════════════════════════════════ -->
    <div class="card" style="margin-bottom:1rem;">
        <div class="form-group">
            <label>Titre (optionnel)</label>
            <input type="text" name="title" value="<?= htmlspecialchars($videoData['title']) ?>"
                   placeholder="Ex : Interview du pr&eacute;sident">
        </div>

        <div class="form-group">
            <label>URL de la vid&eacute;o</label>
            <input type="text" name="url" id="video-url"
                   value="<?= htmlspecialchars($videoData['src']) ?>"
                   placeholder="https://youtube.com/watch?v=... ou https://vimeo.com/... ou .mp4"
                   oninput="previewVideo()">
            <p style="font-size:.8rem; color:var(--muted); margin-top:.3rem;">
                YouTube, Vimeo, ou URL directe d&rsquo;un fichier .mp4/.webm
            </p>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>— ou — T&eacute;l&eacute;verser un fichier vid&eacute;o</label>
            <input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg,.mov"
                   style="padding:.5rem 0;" onchange="clearUrlIfFile(this)">
            <p style="font-size:.8rem; color:var(--muted); margin-top:.3rem;">
                Le fichier t&eacute;l&eacute;vers&eacute; remplace l&rsquo;URL ci-dessus.
            </p>
        </div>
    </div>

    <!-- Aperçu vidéo -->
    <div id="video-preview" class="card" style="margin-bottom:1rem; display:<?= $videoData['src'] ? 'block' : 'none' ?>;">
        <h2 style="margin-bottom:.75rem;">Aper&ccedil;u</h2>
        <div id="preview-container"></div>
    </div>

    <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>

    <script>
    function youtubeId(url) {
        var m = url.match(/[?&]v=([^&]+)/) || url.match(/youtu\.be\/([^?]+)/);
        return m ? m[1] : null;
    }
    function vimeoId(url) {
        var m = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
        return m ? m[1] : null;
    }
    function previewVideo() {
        var url = document.getElementById('video-url').value.trim();
        var box = document.getElementById('video-preview');
        var c   = document.getElementById('preview-container');
        if (!url) { box.style.display = 'none'; return; }
        box.style.display = 'block';
        var ytId = youtubeId(url), vmId = vimeoId(url);
        if (ytId) {
            c.innerHTML = '<div class="video-wrap"><iframe src="https://www.youtube.com/embed/'+ytId+'" frameborder="0" allowfullscreen></iframe></div>';
        } else if (vmId) {
            c.innerHTML = '<div class="video-wrap"><iframe src="https://player.vimeo.com/video/'+vmId+'" frameborder="0" allowfullscreen></iframe></div>';
        } else {
            c.innerHTML = '<video controls style="width:100%;border-radius:8px;background:#000;"><source src="'+url+'"></video>';
        }
    }
    function clearUrlIfFile(input) {
        if (input.files.length > 0) document.getElementById('video-url').value = '';
    }
    // Init preview on page load
    if (document.getElementById('video-url').value) previewVideo();
    </script>
<?php endif; ?>

</form>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

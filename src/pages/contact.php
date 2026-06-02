<?php
$pageTitle = 'Contact — ' . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Contact</h1>

<div class="card">
    <h2>Coordonn&eacute;es</h2>
    <p><strong>Email :</strong> contact@example.com</p>
    <br>
    <p><strong>Adresse :</strong> 1 rue Exemple, 75000 Paris</p>
    <br>
    <p><strong>T&eacute;l&eacute;phone :</strong> 01 23 45 67 89</p>
</div>

<div class="card">
    <h2>Nous &eacute;crire</h2>
    <form>
        <div class="form-group">
            <label>Votre nom</label>
            <input type="text" placeholder="Jean Dupont">
        </div>
        <div class="form-group">
            <label>Votre email</label>
            <input type="email" placeholder="jean@example.com">
        </div>
        <div class="form-group">
            <label>Message</label>
            <textarea style="width:100%;padding:.65rem .9rem;border:1px solid var(--border);border-radius:8px;font-size:.95rem;min-height:100px;resize:vertical;" placeholder="Votre message…"></textarea>
        </div>
        <button type="button" class="btn btn-primary">Envoyer</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

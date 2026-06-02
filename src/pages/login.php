<?php
$pageTitle = 'Connexion — ' . SITE_NAME;

if (isLoggedIn()) {
    header('Location: /dashboard');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password && login($username, $password)) {
        header('Location: /dashboard');
        exit;
    }
    $error = 'Identifiants incorrects ou compte d&eacute;sactiv&eacute;.';
}

include __DIR__ . '/../includes/header.php';
?>
<div class="login-wrap">
    <div class="card">
        <h2 style="margin-bottom:1.5rem; text-align:center;">Connexion</h2>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="username">Identifiant ou email</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-full">Se connecter</button>
        </form>
    </div>
    <p class="login-hint">Identifiants par d&eacute;faut&nbsp;: <strong>admin</strong> / <strong>admin123</strong></p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

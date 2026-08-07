<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaAdmin();

if (adminLogado()) {
    header('Location: ' . URL_BASE . '/admin/index.php');
    exit;
}

$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM Admin WHERE Email = :email");
    $stmt->execute(['email' => $email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($senha, $admin['Senha'])) {
        $_SESSION['admin_id'] = $admin['IDAdmin'];
        $_SESSION['admin_nome'] = $admin['Nome'];
        header('Location: ' . URL_BASE . '/admin/index.php');
        exit;
    }
    $erro = 'E-mail ou senha inválidos.';
}

$instalado = isset($_GET['instalado']);
require __DIR__ . '/_topo.php';
?>
<div class="row">
    <div class="col-md-5 mx-auto">
        <div class="card p-4">
            <h1 class="h4 mb-3">Painel administrativo</h1>
            <?php if ($instalado): ?><div class="alert alert-success">Administrador criado. Faça login.</div><?php endif; ?>
            <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Senha</label>
                    <input type="password" name="senha" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-marca rounded-pill w-100">Entrar</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_rodape.php'; ?>

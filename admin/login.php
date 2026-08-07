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
        <div class="card p-4 p-md-5">
            <h1 class="h4 mb-1">Painel administrativo</h1>
            <p class="text-secundario mb-4">Entra com sua conta de admin.</p>
            <?php if ($instalado): ?><div class="alert alert-success">Administrador criado. Faça login.</div><?php endif; ?>
            <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <form method="post">
                <div class="form-floating mb-3">
                    <input type="email" name="email" id="adminEmail" class="form-control" placeholder="seuemail@exemplo.com" required>
                    <label for="adminEmail">E-mail</label>
                </div>
                <div class="form-floating mb-3 tem-toggle-senha">
                    <input type="password" name="senha" id="adminSenha" class="form-control" placeholder="Sua senha" required>
                    <label for="adminSenha">Senha</label>
                    <button type="button" class="btn-toggle-senha" data-alvo="adminSenha" aria-label="Mostrar senha">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <button type="submit" class="btn btn-marca rounded-pill w-100 py-2">Entrar</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_rodape.php'; ?>

<?php
// Sem acesso SSH em produção, esta página é a única forma de criar o 1º admin sem mexer direto no banco.
// Depois que existir 1 admin cadastrado, ela se autobloqueia.
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaAdmin();

global $pdo;
$totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM Admin")->fetchColumn();

$erro = null;
if ($totalAdmins === 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($nome === '' || $email === '' || strlen($senha) < 8) {
        $erro = 'Preencha nome, e-mail e uma senha com pelo menos 8 caracteres.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO Admin (IDAdmin, Nome, Email, Senha) VALUES (:id, :nome, :email, :senha)");
        $stmt->execute([
            'id' => gerarUuid(),
            'nome' => $nome,
            'email' => $email,
            'senha' => password_hash($senha, PASSWORD_DEFAULT),
        ]);
        header('Location: ' . URL_BASE . '/admin/login.php?instalado=1');
        exit;
    }
}

require __DIR__ . '/_topo.php';
?>
<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card p-4">
            <?php if ($totalAdmins > 0): ?>
                <p class="mb-0">Já existe um administrador configurado. Se você perdeu o acesso, peça pra alguém com acesso ao banco de dados redefinir a senha diretamente na tabela <code>Admin</code>.</p>
                <a href="<?= URL_BASE ?>/admin/login.php" class="btn btn-marca rounded-pill mt-3">Ir para o login</a>
            <?php else: ?>
                <h1 class="h4 mb-3">Criar o primeiro administrador</h1>
                <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha</label>
                        <input type="password" name="senha" class="form-control" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-marca rounded-pill w-100">Criar administrador</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_rodape.php'; ?>

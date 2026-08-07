<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaCliente();

global $pdo;

$linkRecuperacao = null;
$mensagem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    $stmt = $pdo->prepare("SELECT IDCliente FROM Cliente WHERE Email = :email");
    $stmt->execute(['email' => $email]);
    $idCliente = $stmt->fetchColumn();

    // Mesma mensagem exista ou não o e-mail — não confirma pra quem tá tentando adivinhar contas.
    $mensagem = 'Se esse e-mail existir na nossa base, o link de redefinição foi gerado.';

    if ($idCliente) {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("UPDATE Cliente SET TokenRecuperacao = :token, DataExpiracaoToken = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE IDCliente = :id");
        $stmt->execute(['token' => $token, 'id' => $idCliente]);

        // Envio por e-mail entra na rodada de e-mails transacionais (junto com pedido/checkout).
        // Até lá, o link aparece na tela mesmo, só pra não travar o fluxo em dev.
        $linkRecuperacao = URL_BASE . '/usuario/redefinir-senha.php?token=' . $token;
    }
}

require __DIR__ . '/../geral/header.php';
?>
<div class="row">
    <div class="col-md-5 mx-auto">
        <div class="card p-4">
            <h1 class="h4 mb-3">Recuperar senha</h1>
            <?php if ($mensagem): ?><div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
            <?php if ($linkRecuperacao): ?>
                <div class="alert alert-warning small">
                    E-mail transacional ainda não configurado nesta base — em dev, o link é este:<br>
                    <a href="<?= htmlspecialchars($linkRecuperacao) ?>" class="link-marca"><?= htmlspecialchars($linkRecuperacao) ?></a>
                </div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-marca rounded-pill w-100">Enviar link de redefinição</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../geral/footer.php'; ?>

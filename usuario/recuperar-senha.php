<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
garantirTabelaUsuario();

global $pdo;

$linkRecuperacao = null;
$mensagem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    $stmt = $pdo->prepare("SELECT IDUsuario FROM Usuario WHERE Email = :email");
    $stmt->execute(['email' => $email]);
    $idUsuario = $stmt->fetchColumn();

    // Mesma mensagem exista ou não o e-mail — não confirma pra quem tá tentando adivinhar contas.
    $mensagem = 'Se esse e-mail existir na nossa base, o link de redefinição foi gerado.';

    if ($idUsuario) {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("UPDATE Usuario SET TokenRecuperacao = :token, DataExpiracaoToken = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE IDUsuario = :id");
        $stmt->execute(['token' => $token, 'id' => $idUsuario]);

        // Envio por e-mail entra na rodada de e-mails transacionais (junto com pedido/checkout).
        // Até lá, o link aparece na tela mesmo, só pra não travar o fluxo em dev.
        $linkRecuperacao = URL_BASE . '/usuario/redefinir-senha.php?token=' . $token;
    }
}

require __DIR__ . '/../geral/header.php';
?>
<div class="row">
    <div class="col-md-5 mx-auto">
        <div class="card p-4 p-md-5">
            <h1 class="h4 mb-1">Recuperar senha</h1>
            <p class="text-secundario mb-4">Manda o e-mail da sua conta que a gente te ajuda.</p>
            <?php if ($mensagem): ?><div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
            <?php if ($linkRecuperacao): ?>
                <div class="alert alert-warning small">
                    E-mail transacional ainda não configurado nesta base — em dev, o link é este:<br>
                    <a href="<?= htmlspecialchars($linkRecuperacao) ?>" class="link-marca"><?= htmlspecialchars($linkRecuperacao) ?></a>
                </div>
            <?php endif; ?>
            <form method="post">
                <div class="form-floating mb-3">
                    <input type="email" name="email" id="recuperarEmail" class="form-control" placeholder="seuemail@exemplo.com" required>
                    <label for="recuperarEmail">E-mail</label>
                </div>
                <button type="submit" class="btn btn-marca rounded-pill w-100 py-2">Enviar link de redefinição</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../geral/footer.php'; ?>

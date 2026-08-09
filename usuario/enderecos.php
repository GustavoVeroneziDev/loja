<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
exigirLoginCliente();
garantirTabelaUsuario();
garantirTabelaEndereco();

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sucesso = null;

    if ($action === 'criar' || $action === 'editar') {
        $id = $_POST['id'] ?? '';
        $cep = preg_replace('/\D/', '', $_POST['cep'] ?? '');
        $logradouro = trim($_POST['logradouro'] ?? '');
        $numero = trim($_POST['numero'] ?? '');
        $complemento = trim($_POST['complemento'] ?? '');
        $bairro = trim($_POST['bairro'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $uf = strtoupper(trim($_POST['uf'] ?? ''));
        $principal = isset($_POST['principal']);

        if (strlen($cep) === 8 && $logradouro !== '' && $numero !== '' && $cidade !== '' && array_key_exists($uf, listaUfsBrasil())) {
            // Só pode existir 1 endereço principal por vez — desmarca os outros antes de gravar
            // este como principal (e o 1º endereço da conta vira principal sozinho, sem perguntar).
            $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM Endereco WHERE FKUsuario = :u");
            $stmtTotal->execute(['u' => $_SESSION['usuario_id']]);
            $ehPrimeiro = (int) $stmtTotal->fetchColumn() === 0;
            $principal = $principal || ($action === 'criar' && $ehPrimeiro);

            if ($principal) {
                $pdo->prepare("UPDATE Endereco SET Principal = 0 WHERE FKUsuario = :u")
                    ->execute(['u' => $_SESSION['usuario_id']]);
            }

            $cepFormatado = substr($cep, 0, 5) . '-' . substr($cep, 5);

            if ($action === 'criar') {
                $stmt = $pdo->prepare("INSERT INTO Endereco (IDEndereco, FKUsuario, CEP, Logradouro, Numero, Complemento, Bairro, Cidade, UF, Principal) VALUES (:id, :u, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :uf, :principal)");
                $stmt->execute([
                    'id' => gerarUuid(),
                    'u' => $_SESSION['usuario_id'],
                    'cep' => $cepFormatado,
                    'logradouro' => $logradouro,
                    'numero' => $numero,
                    'complemento' => $complemento !== '' ? $complemento : null,
                    'bairro' => $bairro !== '' ? $bairro : null,
                    'cidade' => $cidade,
                    'uf' => $uf,
                    'principal' => $principal ? 1 : 0,
                ]);
            } elseif ($id !== '') {
                $stmt = $pdo->prepare("UPDATE Endereco SET CEP = :cep, Logradouro = :logradouro, Numero = :numero, Complemento = :complemento, Bairro = :bairro, Cidade = :cidade, UF = :uf, Principal = :principal WHERE IDEndereco = :id AND FKUsuario = :u");
                $stmt->execute([
                    'cep' => $cepFormatado,
                    'logradouro' => $logradouro,
                    'numero' => $numero,
                    'complemento' => $complemento !== '' ? $complemento : null,
                    'bairro' => $bairro !== '' ? $bairro : null,
                    'cidade' => $cidade,
                    'uf' => $uf,
                    'principal' => $principal ? 1 : 0,
                    'id' => $id,
                    'u' => $_SESSION['usuario_id'],
                ]);
            }
            $sucesso = true;
        }
    }

    if ($action === 'marcar_principal') {
        $id = $_POST['id'] ?? '';
        if ($id !== '') {
            $pdo->prepare("UPDATE Endereco SET Principal = 0 WHERE FKUsuario = :u")->execute(['u' => $_SESSION['usuario_id']]);
            $pdo->prepare("UPDATE Endereco SET Principal = 1 WHERE IDEndereco = :id AND FKUsuario = :u")
                ->execute(['id' => $id, 'u' => $_SESSION['usuario_id']]);
            $sucesso = true;
        }
    }

    if ($action === 'excluir') {
        $id = $_POST['id'] ?? '';
        if ($id !== '') {
            $stmt = $pdo->prepare("DELETE FROM Endereco WHERE IDEndereco = :id AND FKUsuario = :u");
            $stmt->execute(['id' => $id, 'u' => $_SESSION['usuario_id']]);
            $sucesso = true;
        }
    }

    header('Location: ' . URL_BASE . '/usuario/enderecos.php' . ($sucesso ? '?ok=1' : '?erro=1'));
    exit;
}

$sucesso = isset($_GET['ok']) ? 'Operação realizada com sucesso.' : null;
$erro = isset($_GET['erro']) ? 'Confira os campos obrigatórios e tente de novo.' : null;
$enderecos = obterEnderecosPorUsuario($_SESSION['usuario_id']);
$ufs = listaUfsBrasil();

require __DIR__ . '/../geral/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 titulo-estilizado">Meus endereços</h1>
    <button class="btn btn-marca rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCriarEndereco">
        <i class="bi bi-plus-lg"></i> Novo endereço
    </button>
</div>

<?php if ($sucesso): ?><div class="alert alert-success"><?= $sucesso ?></div><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

<?php if (!$enderecos): ?>
    <div class="card p-5 text-center text-secundario">
        <p class="mb-0">Você ainda não salvou nenhum endereço.</p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($enderecos as $endereco): ?>
            <div class="col-md-6">
                <div class="card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <?php if ($endereco['Principal']): ?>
                            <span class="badge-sucesso px-2 py-1 small"><i class="bi bi-check-circle"></i> Principal</span>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="action" value="marcar_principal">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($endereco['IDEndereco']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill">Marcar como principal</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <p class="mb-3">
                        <?= htmlspecialchars($endereco['Logradouro']) ?>, <?= htmlspecialchars($endereco['Numero']) ?>
                        <?php if ($endereco['Complemento']): ?> — <?= htmlspecialchars($endereco['Complemento']) ?><?php endif; ?><br>
                        <?php if ($endereco['Bairro']): ?><?= htmlspecialchars($endereco['Bairro']) ?> — <?php endif; ?>
                        <?= htmlspecialchars($endereco['Cidade']) ?>/<?= htmlspecialchars($endereco['UF']) ?><br>
                        CEP <?= htmlspecialchars($endereco['CEP']) ?>
                    </p>
                    <div class="d-flex gap-2 mt-auto">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $endereco['IDEndereco'] ?>">
                            <i class="bi bi-pencil"></i> Editar
                        </button>
                        <form method="post" data-confirmar="Excluir este endereço?">
                            <input type="hidden" name="action" value="excluir">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($endereco['IDEndereco']) ?>">
                            <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i> Excluir</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php foreach ($enderecos as $endereco): ?>
    <div class="modal fade" id="modalEditar<?= $endereco['IDEndereco'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" class="form-endereco">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar endereço</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($endereco['IDEndereco']) ?>">
                        <?php require __DIR__ . '/_campos-endereco.php'; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-marca rounded-pill">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="modalCriarEndereco" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" class="form-endereco">
                <div class="modal-header">
                    <h5 class="modal-title">Novo endereço</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="criar">
                    <?php $endereco = null; require __DIR__ . '/_campos-endereco.php'; ?>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-marca rounded-pill">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Autopreenche Logradouro/Bairro/Cidade/UF a partir do CEP (ViaCEP, API pública sem chave) — só
// completa o que veio vazio, nunca sobrescreve o que a pessoa já tinha digitado/editado na mão.
document.querySelectorAll('.form-endereco').forEach(function (form) {
    var campoCep = form.querySelector('.campo-cep');
    if (!campoCep) return;

    campoCep.addEventListener('blur', function () {
        var cep = campoCep.value.replace(/\D/g, '');
        if (cep.length !== 8) return;

        fetch('https://viacep.com.br/ws/' + cep + '/json/')
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                if (dados.erro) return;
                var mapa = {
                    logradouro: dados.logradouro,
                    bairro: dados.bairro,
                    cidade: dados.localidade,
                };
                Object.keys(mapa).forEach(function (nome) {
                    var campo = form.querySelector('[name="' + nome + '"]');
                    if (campo && !campo.value && mapa[nome]) campo.value = mapa[nome];
                });
                var campoUf = form.querySelector('[name="uf"]');
                if (campoUf && !campoUf.value && dados.uf) campoUf.value = dados.uf;
            })
            .catch(function () { /* falha na consulta não deve travar o preenchimento manual */ });
    });
});
</script>
<?php require __DIR__ . '/../geral/footer.php'; ?>

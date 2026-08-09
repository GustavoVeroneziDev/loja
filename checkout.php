<?php
session_start();
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
require_once __DIR__ . '/config/marca.php';
exigirLoginCliente();
garantirTabelaUsuario();
garantirTabelaEndereco();
garantirTabelaCupom();
garantirTabelaPedido();
garantirTabelaMovimentoEstoque();
garantirTabelaItemPedido();
garantirTabelaHistoricoStatusPedido();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
garantirTabelaItemCarrinho();

global $pdo;

$itens = obterCarrinho();
if (!$itens) {
    header('Location: ' . URL_BASE . '/carrinho.php');
    exit;
}
$subtotal = array_sum(array_column($itens, 'subtotal'));
$enderecos = obterEnderecosPorUsuario($_SESSION['usuario_id']);

// Resolve o endereço de entrega — de um salvo (por ID) ou digitado na hora ($dados = $_POST).
// Usada tanto pra recalcular a prévia quanto pra confirmar de verdade, sempre a mesma lógica.
function resolverEndereco($enderecos, $idSelecionado, $dados) {
    if ($idSelecionado !== '' && $idSelecionado !== 'novo') {
        foreach ($enderecos as $e) {
            if ($e['IDEndereco'] === $idSelecionado) {
                return ['cep' => $e['CEP'], 'logradouro' => $e['Logradouro'], 'numero' => $e['Numero'], 'complemento' => $e['Complemento'] ?? '', 'bairro' => $e['Bairro'] ?? '', 'cidade' => $e['Cidade'], 'uf' => $e['UF']];
            }
        }
        return null;
    }
    $cep = preg_replace('/\D/', '', $dados['cep'] ?? '');
    $logradouro = trim($dados['logradouro'] ?? '');
    $numero = trim($dados['numero'] ?? '');
    $cidade = trim($dados['cidade'] ?? '');
    $uf = strtoupper(trim($dados['uf'] ?? ''));
    if (strlen($cep) !== 8 || $logradouro === '' || $numero === '' || $cidade === '' || !array_key_exists($uf, listaUfsBrasil())) {
        return null;
    }
    return ['cep' => substr($cep, 0, 5) . '-' . substr($cep, 5), 'logradouro' => $logradouro, 'numero' => $numero, 'complemento' => trim($dados['complemento'] ?? ''), 'bairro' => trim($dados['bairro'] ?? ''), 'cidade' => $cidade, 'uf' => $uf];
}

$metodoPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$acao = $_POST['action'] ?? '';

$enderecoSelecionadoId = $_POST['endereco_id'] ?? '';
if (!$metodoPost) {
    // Primeira carga: pré-seleciona o principal (ou o primeiro que tiver), sem precisar escolher.
    foreach ($enderecos as $e) {
        if ($e['Principal']) {
            $enderecoSelecionadoId = $e['IDEndereco'];
            break;
        }
    }
    if ($enderecoSelecionadoId === '' && $enderecos) {
        $enderecoSelecionadoId = $enderecos[0]['IDEndereco'];
    }
}
$enderecoVeioDeSelecao = false;
foreach ($enderecos as $e) {
    if ($e['IDEndereco'] === $enderecoSelecionadoId) {
        $enderecoVeioDeSelecao = true;
        break;
    }
}

$cupomCodigo = trim($_POST['cupom'] ?? '');
$erro = null;
$cupomErro = null;
$frete = 0;
$desconto = 0;

$enderecoResolvido = resolverEndereco($enderecos, $enderecoSelecionadoId, $_POST);
if ($enderecoResolvido !== null) {
    $frete = calcularFrete($enderecoResolvido['cep'], $subtotal);
    if ($cupomCodigo !== '') {
        $cupomAplicado = validarCupom($cupomCodigo);
        if ($cupomAplicado) {
            $desconto = calcularDescontoCupom($cupomAplicado, $subtotal);
        } else {
            $cupomErro = motivoCupomInvalido($cupomCodigo);
        }
    }
} elseif ($metodoPost && $acao === 'confirmar') {
    $erro = 'Escolha ou preencha um endereço de entrega válido.';
}

$total = max(0, $subtotal - $desconto) + $frete;

if ($metodoPost && $acao === 'confirmar' && $enderecoResolvido !== null && !$cupomErro) {
    $resultado = criarPedido($_SESSION['usuario_id'], $enderecoResolvido, $cupomCodigo);
    if ($resultado['sucesso']) {
        // Endereço digitado na hora (não veio de um já salvo) também vira Endereco salvo pra
        // próxima compra — se isso falhar por algum motivo não trava nada, o pedido já foi
        // criado e é o que importa de verdade.
        if (!$enderecoVeioDeSelecao) {
            try {
                $stmt = $pdo->prepare("INSERT INTO Endereco (IDEndereco, FKUsuario, CEP, Logradouro, Numero, Complemento, Bairro, Cidade, UF, Principal) VALUES (:id, :u, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :uf, :principal)");
                $stmt->execute([
                    'id' => gerarUuid(),
                    'u' => $_SESSION['usuario_id'],
                    'cep' => $enderecoResolvido['cep'],
                    'logradouro' => $enderecoResolvido['logradouro'],
                    'numero' => $enderecoResolvido['numero'],
                    'complemento' => $enderecoResolvido['complemento'] !== '' ? $enderecoResolvido['complemento'] : null,
                    'bairro' => $enderecoResolvido['bairro'] !== '' ? $enderecoResolvido['bairro'] : null,
                    'cidade' => $enderecoResolvido['cidade'],
                    'uf' => $enderecoResolvido['uf'],
                    'principal' => count($enderecos) === 0 ? 1 : 0,
                ]);
            } catch (PDOException $e) {
                error_log('Erro ao salvar novo endereço do checkout: ' . $e->getMessage());
            }
        }
        header('Location: ' . URL_BASE . '/usuario/pedido.php?id=' . $resultado['id_pedido'] . '&novo=1');
        exit;
    }
    $erro = $resultado['erro'];
}

$ufs = listaUfsBrasil();
// Repopula os campos de "novo endereço" com o que já tinha sido digitado no POST anterior — sem
// isso, reenviar o formulário por qualquer motivo (aplicar cupom, tentar confirmar e faltar algum
// campo) limpava CEP/logradouro/etc. que a pessoa já tinha preenchido, obrigando a digitar tudo
// de novo. Só repopula quando tá no modo "novo endereço" — escolher um salvo continua começando
// em branco se depois voltar pra "novo", não faz sentido herdar dado do endereço salvo ali.
$endereco = null;
if ($metodoPost && !$enderecoVeioDeSelecao) {
    $endereco = [
        'IDEndereco' => null,
        'CEP' => $_POST['cep'] ?? '',
        'Logradouro' => $_POST['logradouro'] ?? '',
        'Numero' => $_POST['numero'] ?? '',
        'Complemento' => $_POST['complemento'] ?? '',
        'Bairro' => $_POST['bairro'] ?? '',
        'Cidade' => $_POST['cidade'] ?? '',
        'UF' => $_POST['uf'] ?? '',
        'Principal' => isset($_POST['principal']),
    ];
}

require __DIR__ . '/geral/header.php';
?>
<h1 class="h3 mb-4 titulo-estilizado">Finalizar compra</h1>

<?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<form method="post">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card p-4 mb-4">
                <h2 class="h5 mb-3">Endereço de entrega</h2>
                <?php if ($enderecos): ?>
                    <div class="d-flex flex-column gap-2 mb-3">
                        <?php foreach ($enderecos as $e): ?>
                            <label class="card p-3 mb-0" style="cursor: pointer;">
                                <div class="d-flex gap-2 align-items-start">
                                    <input type="radio" name="endereco_id" value="<?= htmlspecialchars($e['IDEndereco']) ?>" class="form-check-input mt-1" <?= $enderecoSelecionadoId === $e['IDEndereco'] ? 'checked' : '' ?> onchange="document.getElementById('camposNovoEndereco').classList.add('d-none')">
                                    <div>
                                        <?= htmlspecialchars($e['Logradouro']) ?>, <?= htmlspecialchars($e['Numero']) ?><?= $e['Complemento'] ? ' — ' . htmlspecialchars($e['Complemento']) : '' ?><br>
                                        <span class="text-secundario small"><?= htmlspecialchars($e['Cidade']) ?>/<?= htmlspecialchars($e['UF']) ?> — CEP <?= htmlspecialchars($e['CEP']) ?></span>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                        <label class="card p-3 mb-0" style="cursor: pointer;">
                            <div class="d-flex gap-2 align-items-center">
                                <input type="radio" name="endereco_id" value="novo" class="form-check-input" <?= !$enderecoVeioDeSelecao ? 'checked' : '' ?> onchange="document.getElementById('camposNovoEndereco').classList.remove('d-none')">
                                <span>Usar um novo endereço</span>
                            </div>
                        </label>
                    </div>
                <?php endif; ?>
                <div id="camposNovoEndereco" class="form-endereco <?= ($enderecos && $enderecoVeioDeSelecao) ? 'd-none' : '' ?>">
                    <?php require __DIR__ . '/usuario/_campos-endereco.php'; ?>
                </div>
            </div>

            <div class="card p-4">
                <h2 class="h5 mb-3">Cupom de desconto</h2>
                <div class="d-flex gap-2">
                    <input type="text" name="cupom" class="form-control text-uppercase" placeholder="Código do cupom" value="<?= htmlspecialchars($cupomCodigo) ?>">
                    <button type="submit" name="action" value="recalcular" class="btn btn-outline-secondary rounded-pill text-nowrap">Aplicar</button>
                </div>
                <?php if ($cupomErro): ?>
                    <div class="badge-atencao px-2 py-1 small d-inline-flex align-items-center gap-1 mt-2">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($cupomErro) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card p-4">
                <h2 class="h5 mb-3">Resumo do pedido</h2>
                <?php foreach ($itens as $item): $v = $item['variacao']; ?>
                    <div class="d-flex justify-content-between gap-2 mb-2 small">
                        <span class="text-secundario"><?= (int) $item['Quantidade'] ?>x <?= htmlspecialchars($v['NomeProduto']) ?><?= descricaoVariacao($v) ? ' (' . htmlspecialchars(descricaoVariacao($v)) . ')' : '' ?></span>
                        <span class="text-nowrap"><?= formatarPreco($item['subtotal']) ?></span>
                    </div>
                <?php endforeach; ?>
                <hr>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-secundario">Subtotal</span>
                    <span><?= formatarPreco($subtotal) ?></span>
                </div>
                <?php if ($desconto > 0): ?>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-secundario">Desconto</span>
                        <span class="text-sucesso">-<?= formatarPreco($desconto) ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-secundario">Frete</span>
                    <span><?= $frete > 0 ? formatarPreco($frete) : 'Grátis' ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-semibold">Total</span>
                    <span class="fw-semibold h4 mb-0"><?= formatarPreco($total) ?></span>
                </div>
                <button type="submit" name="action" value="confirmar" class="btn btn-marca rounded-pill w-100 py-2">Confirmar pedido</button>
                <p class="text-secundario small mt-2 mb-0 text-center">O pagamento é configurado na próxima etapa.</p>
            </div>
        </div>
    </div>
</form>
<?php require __DIR__ . '/geral/footer.php'; ?>

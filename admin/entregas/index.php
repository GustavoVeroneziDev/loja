<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/funcoes.php';
require_once __DIR__ . '/../../config/chaves.php';
exigirLoginAdmin();
garantirTabelaCaixaEnvio();
garantirTabelaUsuario();
garantirTabelaConfiguracaoSistema();

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sucesso = null;

    if ($action === 'salvar_config_envio') {
        $uf = strtoupper(trim($_POST['remetente_uf'] ?? ''));
        definirConfigEnvio([
            'cep_origem' => preg_replace('/\D/', '', $_POST['cep_origem'] ?? ''),
            'remetente_nome' => trim($_POST['remetente_nome'] ?? ''),
            'remetente_documento' => preg_replace('/\D/', '', $_POST['remetente_documento'] ?? ''),
            'remetente_telefone' => preg_replace('/\D/', '', $_POST['remetente_telefone'] ?? ''),
            'remetente_email' => trim($_POST['remetente_email'] ?? ''),
            'remetente_logradouro' => trim($_POST['remetente_logradouro'] ?? ''),
            'remetente_numero' => trim($_POST['remetente_numero'] ?? ''),
            'remetente_complemento' => trim($_POST['remetente_complemento'] ?? ''),
            'remetente_bairro' => trim($_POST['remetente_bairro'] ?? ''),
            'remetente_cidade' => trim($_POST['remetente_cidade'] ?? ''),
            'remetente_uf' => array_key_exists($uf, listaUfsBrasil()) ? $uf : '',
        ]);
        $sucesso = true;
    }

    if ($action === 'criar' || $action === 'editar') {
        $id = $_POST['id'] ?? '';
        $nome = trim($_POST['nome'] ?? '');
        $peso = (float) str_replace(',', '.', $_POST['peso'] ?? '0');
        $altura = (float) str_replace(',', '.', $_POST['altura'] ?? '0');
        $largura = (float) str_replace(',', '.', $_POST['largura'] ?? '0');
        $comprimento = (float) str_replace(',', '.', $_POST['comprimento'] ?? '0');

        if ($nome !== '' && $peso > 0 && $altura > 0 && $largura > 0 && $comprimento > 0) {
            if ($action === 'criar') {
                $stmt = $pdo->prepare("INSERT INTO CaixaEnvio (IDCaixaEnvio, Nome, Peso, Altura, Largura, Comprimento) VALUES (:id, :nome, :peso, :altura, :largura, :comprimento)");
                $stmt->execute([
                    'id' => gerarUuid(), 'nome' => $nome, 'peso' => $peso,
                    'altura' => $altura, 'largura' => $largura, 'comprimento' => $comprimento,
                ]);
            } elseif ($id !== '') {
                $stmt = $pdo->prepare("UPDATE CaixaEnvio SET Nome = :nome, Peso = :peso, Altura = :altura, Largura = :largura, Comprimento = :comprimento WHERE IDCaixaEnvio = :id");
                $stmt->execute([
                    'nome' => $nome, 'peso' => $peso, 'altura' => $altura,
                    'largura' => $largura, 'comprimento' => $comprimento, 'id' => $id,
                ]);
            }
            $sucesso = true;
        }
    }

    if ($action === 'excluir') {
        $id = $_POST['id'] ?? '';
        if ($id !== '') {
            // Produto que usava essa caixa não trava a exclusão — FKCaixaEnvio vai pra NULL
            // sozinho (ON DELETE SET NULL), o produto só fica "sem caixa definida" até escolher outra.
            $stmt = $pdo->prepare("DELETE FROM CaixaEnvio WHERE IDCaixaEnvio = :id");
            $stmt->execute(['id' => $id]);
            $sucesso = true;
        }
    }

    header('Location: ' . URL_BASE . '/admin/entregas/index.php' . ($sucesso ? '?ok=1' : '?erro=1'));
    exit;
}

$sucesso = isset($_GET['ok']) ? 'Operação realizada com sucesso.' : null;
$erro = isset($_GET['erro']) ? 'Preencha nome, peso e as 3 dimensões (todos maiores que zero).' : null;
$caixas = obterCaixasEnvio();
$melhorEnvioConectado = melhorEnvioConectado();
$configEnvio = obterConfigEnvio();
$ufs = listaUfsBrasil();

require __DIR__ . '/../_topo.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Entregas</h1>
    <button class="btn btn-marca rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCriarCaixa">
        <i class="bi bi-plus-lg"></i> Nova caixa
    </button>
</div>

<?php if ($sucesso): ?><script>document.addEventListener('DOMContentLoaded', function () { mostrarToastSucesso(<?= json_encode($sucesso) ?>); });</script><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>

<div class="card p-4 mb-4">
    <h2 class="h5 mb-3">Integração Melhor Envio</h2>
    <?php if ($melhorEnvioConectado): ?>
        <?php $expiraEm = melhorEnvioTokenExpiraEm(); ?>
        <p class="mb-0"><span class="badge-sucesso px-2 py-1 d-inline-flex align-items-center gap-1"><i class="bi bi-check-circle-fill"></i> Token configurado</span> — o frete no checkout já usa cotação real (ambiente: <strong><?= MELHOR_ENVIO_AMBIENTE === 'producao' ? 'produção' : 'sandbox (teste)' ?></strong>).</p>
        <?php if ($expiraEm): ?>
            <p class="text-secundario small mb-0 mt-2">Token válido até <?= date('d/m/Y', $expiraEm) ?> — quando expirar, gera um novo no painel do Melhor Envio e atualiza <code>config/chaves.php</code> no servidor.</p>
        <?php endif; ?>
    <?php else: ?>
        <p class="mb-0"><span class="badge-atencao px-2 py-1 d-inline-flex align-items-center gap-1"><i class="bi bi-exclamation-triangle"></i> Não configurado</span> — falta o token em <code>config/chaves.php</code> (gera em Melhor Envio &gt; Integrações &gt; Tokens de Acesso). Até lá, o checkout usa o frete fixo de reserva (<code>config/marca.php</code>).</p>
    <?php endif; ?>
</div>

<div class="card p-4 mb-4">
    <h2 class="h5 mb-3">CEP de origem e remetente</h2>
    <p class="text-secundario small">Endereço de onde os pedidos saem e quem aparece como remetente na etiqueta — mesma ideia dos dados que o cliente preenche em "Minha conta", só que da loja. Sem isso, a cotação e a geração de etiqueta não funcionam.</p>
    <?php if (!configEnvioCompleta($configEnvio)): ?>
        <p class="mb-3"><span class="badge-atencao px-2 py-1 d-inline-flex align-items-center gap-1"><i class="bi bi-exclamation-triangle"></i> Incompleto</span></p>
    <?php else: ?>
        <p class="mb-3"><span class="badge-sucesso px-2 py-1 d-inline-flex align-items-center gap-1"><i class="bi bi-check-circle-fill"></i> Configurado</span></p>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="action" value="salvar_config_envio">
        <div class="row g-3">
            <div class="col-sm-4">
                <label class="form-label">CEP de origem</label>
                <input type="text" name="cep_origem" class="form-control campo-cep" inputmode="numeric" maxlength="9" placeholder="00000-000" value="<?= htmlspecialchars($configEnvio['cep_origem']) ?>" required>
            </div>
            <div class="col-sm-8">
                <label class="form-label">Nome do remetente</label>
                <input type="text" name="remetente_nome" class="form-control" value="<?= htmlspecialchars($configEnvio['remetente_nome']) ?>" required>
            </div>
            <div class="col-sm-4">
                <label class="form-label">CPF ou CNPJ</label>
                <input type="text" name="remetente_documento" class="form-control mascara-cpf" inputmode="numeric" value="<?= htmlspecialchars($configEnvio['remetente_documento']) ?>" required>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Telefone</label>
                <input type="text" name="remetente_telefone" class="form-control mascara-telefone" inputmode="numeric" value="<?= htmlspecialchars($configEnvio['remetente_telefone']) ?>" required>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Email</label>
                <input type="email" name="remetente_email" class="form-control" value="<?= htmlspecialchars($configEnvio['remetente_email']) ?>" required>
            </div>
            <div class="col-sm-8">
                <label class="form-label">Logradouro</label>
                <input type="text" name="remetente_logradouro" class="form-control" value="<?= htmlspecialchars($configEnvio['remetente_logradouro']) ?>" required>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Número</label>
                <input type="text" name="remetente_numero" class="form-control" value="<?= htmlspecialchars($configEnvio['remetente_numero']) ?>" required>
            </div>
            <div class="col-sm-8">
                <label class="form-label">Complemento</label>
                <input type="text" name="remetente_complemento" class="form-control" placeholder="opcional" value="<?= htmlspecialchars($configEnvio['remetente_complemento']) ?>">
            </div>
            <div class="col-sm-4">
                <label class="form-label">Bairro</label>
                <input type="text" name="remetente_bairro" class="form-control" value="<?= htmlspecialchars($configEnvio['remetente_bairro']) ?>" required>
            </div>
            <div class="col-sm-3">
                <label class="form-label">UF</label>
                <select name="remetente_uf" class="form-select" required>
                    <option value="">--</option>
                    <?php foreach ($ufs as $sigla => $nome): ?>
                        <option value="<?= $sigla ?>" <?= $configEnvio['remetente_uf'] === $sigla ? 'selected' : '' ?>><?= $sigla ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Cidade</label>
                <input type="text" name="remetente_cidade" class="form-control" value="<?= htmlspecialchars($configEnvio['remetente_cidade']) ?>" required>
            </div>
        </div>
        <button type="submit" class="btn btn-marca rounded-pill mt-3">Salvar</button>
    </form>
</div>

<p class="text-secundario">Peso e tamanho de embalagem, pra calcular o frete de verdade. Cadastre uma vez (ex: "Caixa P", "Caixa M") e escolha qual cada produto usa na própria tela de produto.</p>

<div class="card">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Nome</th>
                <th class="d-none d-md-table-cell">Peso</th>
                <th class="d-none d-md-table-cell">Dimensões (A×L×C)</th>
                <th class="d-none d-md-table-cell" style="width: 100px; min-width: 100px; max-width: 100px;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($caixas as $caixa): ?>
                <tr class="linha-expandivel" data-alvo-expandir="detalhesCaixa<?= $caixa['IDCaixaEnvio'] ?>">
                    <td class="fw-semibold">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <span><?= htmlspecialchars($caixa['Nome']) ?></span>
                            <i class="bi bi-chevron-down icone-expandir d-md-none text-secundario"></i>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell"><?= number_format($caixa['Peso'], 3, ',', '.') ?> kg</td>
                    <td class="d-none d-md-table-cell text-secundario"><?= number_format($caixa['Altura'], 1, ',', '.') ?> × <?= number_format($caixa['Largura'], 1, ',', '.') ?> × <?= number_format($caixa['Comprimento'], 1, ',', '.') ?> cm</td>
                    <td class="d-none d-md-table-cell" style="width: 100px; min-width: 100px; max-width: 100px;">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $caixa['IDCaixaEnvio'] ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="post" class="d-inline" data-confirmar="Excluir a <?= htmlspecialchars($caixa['Nome']) ?>? Produtos que usam ela ficam sem caixa definida.">
                            <input type="hidden" name="action" value="excluir">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($caixa['IDCaixaEnvio']) ?>">
                            <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <tr id="detalhesCaixa<?= $caixa['IDCaixaEnvio'] ?>" class="d-none d-md-none">
                    <td colspan="1" class="bg-light-subtle">
                        <div class="d-flex justify-content-between small mb-2">
                            <span class="text-secundario">Peso</span>
                            <span><?= number_format($caixa['Peso'], 3, ',', '.') ?> kg</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-3">
                            <span class="text-secundario">Dimensões (A×L×C)</span>
                            <span><?= number_format($caixa['Altura'], 1, ',', '.') ?> × <?= number_format($caixa['Largura'], 1, ',', '.') ?> × <?= number_format($caixa['Comprimento'], 1, ',', '.') ?> cm</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $caixa['IDCaixaEnvio'] ?>">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                            <form method="post" data-confirmar="Excluir a <?= htmlspecialchars($caixa['Nome']) ?>? Produtos que usam ela ficam sem caixa definida.">
                                <input type="hidden" name="action" value="excluir">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($caixa['IDCaixaEnvio']) ?>">
                                <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i> Excluir</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$caixas): ?>
                <tr><td colspan="4" class="text-secundario text-center py-4">Nenhuma caixa cadastrada ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php foreach ($caixas as $caixa): ?>
    <div class="modal fade" id="modalEditar<?= $caixa['IDCaixaEnvio'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar caixa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($caixa['IDCaixaEnvio']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($caixa['Nome']) ?>" placeholder="ex: Caixa P" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Peso (kg)</label>
                            <input type="text" name="peso" class="form-control mascara-peso" inputmode="decimal" value="<?= number_format($caixa['Peso'], 3, ',', '.') ?>" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-4">
                                <label class="form-label">Altura (cm)</label>
                                <input type="text" name="altura" class="form-control mascara-dimensao" inputmode="decimal" value="<?= number_format($caixa['Altura'], 1, ',', '.') ?>" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Largura (cm)</label>
                                <input type="text" name="largura" class="form-control mascara-dimensao" inputmode="decimal" value="<?= number_format($caixa['Largura'], 1, ',', '.') ?>" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Comprimento (cm)</label>
                                <input type="text" name="comprimento" class="form-control mascara-dimensao" inputmode="decimal" value="<?= number_format($caixa['Comprimento'], 1, ',', '.') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-marca rounded-pill">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="modalCriarCaixa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Nova caixa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="criar">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" placeholder="ex: Caixa P" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Peso (kg)</label>
                        <input type="text" name="peso" class="form-control mascara-peso" inputmode="decimal" placeholder="0,300" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-4">
                            <label class="form-label">Altura (cm)</label>
                            <input type="text" name="altura" class="form-control mascara-dimensao" inputmode="decimal" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Largura (cm)</label>
                            <input type="text" name="largura" class="form-control mascara-dimensao" inputmode="decimal" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Comprimento (cm)</label>
                            <input type="text" name="comprimento" class="form-control mascara-dimensao" inputmode="decimal" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-marca rounded-pill">Criar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../_rodape.php'; ?>

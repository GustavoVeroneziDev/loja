<?php
session_start();
require_once __DIR__ . '/../../config/conexao.php';
require_once __DIR__ . '/../../config/funcoes.php';
exigirLoginAdmin();
garantirTabelaCategoria();
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
garantirTabelaCaixaEnvio();

global $pdo;

$idProduto = $_GET['id'] ?? '';
$produto = obterProdutoPorId($idProduto);
if (!$produto) {
    header('Location: ' . URL_BASE . '/admin/produto/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sucesso = null;
    $erroCodigo = null;
    $avisoCodigo = null;

    if ($action === 'editar') {
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $fkCategoria = $_POST['fk_categoria'] ?? '';
        $fkCaixaEnvio = $_POST['fk_caixa_envio'] ?? '';
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $tipoProduto = $_POST['tipo_produto'] ?? 'simples';
        $nomeAtributo1 = $tipoProduto === 'variavel' ? trim($_POST['nome_atributo_1'] ?? '') : '';
        $nomeAtributo2 = $tipoProduto === 'variavel' ? trim($_POST['nome_atributo_2'] ?? '') : '';

        // Não deixa voltar pra "produto simples" com mais de 1 variação cadastrada — ia sobrar
        // variação sem rótulo nenhum pra diferenciar uma da outra. Precisa excluir as extras primeiro.
        if ($tipoProduto === 'simples') {
            $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM VariacaoProduto WHERE FKProduto = :produto");
            $stmtTotal->execute(['produto' => $idProduto]);
            if ((int) $stmtTotal->fetchColumn() > 1) {
                $avisoCodigo = 'nao_e_simples';
                $nomeAtributo1 = trim($_POST['nome_atributo_1'] ?? '');
                $nomeAtributo2 = trim($_POST['nome_atributo_2'] ?? '');
            }
        }

        // Nunca deixa ficar visível na loja com o cadastro incompleto — sem essa checagem dava
        // pra ativar um produto sem nenhuma foto, ou que venderia por R$ 0,00.
        if ($ativo === 1) {
            $stmtPreco = $pdo->prepare("SELECT COUNT(*) FROM VariacaoProduto WHERE FKProduto = :produto AND Preco > 0");
            $stmtPreco->execute(['produto' => $idProduto]);
            $temPreco = (int) $stmtPreco->fetchColumn() > 0;

            $stmtImagem = $pdo->prepare("SELECT COUNT(*) FROM ImagemProduto WHERE FKProduto = :produto");
            $stmtImagem->execute(['produto' => $idProduto]);
            $temImagem = (int) $stmtImagem->fetchColumn() > 0;

            if (!$temPreco || !$temImagem) {
                $ativo = 0;
                // Não sobrescreve o aviso de "não dá pra simplificar" se ele já disparou acima.
                $avisoCodigo = $avisoCodigo ?? (!$temPreco && !$temImagem ? 'incompleto_preco_imagem' : (!$temPreco ? 'incompleto_preco' : 'incompleto_imagem'));
            }
        }

        if ($nome !== '') {
            $stmt = $pdo->prepare("UPDATE Produto SET Nome = :nome, Descricao = :descricao, FKCategoria = :categoria, FKCaixaEnvio = :caixa, Ativo = :ativo, NomeAtributo1 = :atributo1, NomeAtributo2 = :atributo2 WHERE IDProduto = :id");
            $stmt->execute([
                'nome' => $nome,
                'descricao' => $descricao,
                'categoria' => $fkCategoria !== '' ? $fkCategoria : null,
                'caixa' => $fkCaixaEnvio !== '' ? $fkCaixaEnvio : null,
                'ativo' => $ativo,
                'atributo1' => $nomeAtributo1 !== '' ? $nomeAtributo1 : null,
                // Sem 1º eixo não faz sentido ter 2º — evita ficar só com NomeAtributo2 preenchido.
                'atributo2' => ($nomeAtributo1 !== '' && $nomeAtributo2 !== '') ? $nomeAtributo2 : null,
                'id' => $idProduto,
            ]);
            $sucesso = true;
        }
    }

    if ($action === 'criar_variacao') {
        $valor1 = trim($_POST['valor_atributo_1'] ?? '');
        $valor2 = trim($_POST['valor_atributo_2'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        // Preço vem em centavos digitados (a máscara de JS já mostra formatado) — 5000 = R$ 50,00.
        $preco = ((int) preg_replace('/\D/', '', $_POST['preco'] ?? '0')) / 100;
        $estoque = max(0, (int) ($_POST['estoque'] ?? 0));

        if ($preco <= 0) {
            $erroCodigo = 'preco_invalido';
        } else {
            $idVariacao = gerarUuid();
            // SKU é código interno, não precisa ser digitado — gera um a partir da própria variação se deixarem em branco.
            if ($sku === '') {
                $sku = 'VAR-' . strtoupper(substr($idVariacao, 0, 8));
            }

            $stmt = $pdo->prepare("INSERT INTO VariacaoProduto (IDVariacao, FKProduto, ValorAtributo1, ValorAtributo2, SKU, Preco, Estoque) VALUES (:id, :produto, :valor1, :valor2, :sku, :preco, :estoque)");
            $stmt->execute([
                'id' => $idVariacao,
                'produto' => $idProduto,
                'valor1' => $valor1 !== '' ? $valor1 : null,
                'valor2' => $valor2 !== '' ? $valor2 : null,
                'sku' => $sku,
                'preco' => $preco,
                'estoque' => $estoque,
            ]);
            $sucesso = true;
        }
    }

    if ($action === 'editar_variacao') {
        $idVariacao = $_POST['id_variacao'] ?? '';
        $valor1 = trim($_POST['valor_atributo_1'] ?? '');
        $valor2 = trim($_POST['valor_atributo_2'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $preco = ((int) preg_replace('/\D/', '', $_POST['preco'] ?? '0')) / 100;
        $estoque = max(0, (int) ($_POST['estoque'] ?? 0));
        if ($sku === '') {
            $sku = 'VAR-' . strtoupper(substr($idVariacao, 0, 8));
        }

        if ($preco <= 0) {
            $erroCodigo = 'preco_invalido';
        } elseif ($idVariacao !== '') {
            $stmt = $pdo->prepare("UPDATE VariacaoProduto SET ValorAtributo1 = :valor1, ValorAtributo2 = :valor2, SKU = :sku, Preco = :preco, Estoque = :estoque WHERE IDVariacao = :id AND FKProduto = :produto");
            $stmt->execute([
                'valor1' => $valor1 !== '' ? $valor1 : null,
                'valor2' => $valor2 !== '' ? $valor2 : null,
                'sku' => $sku,
                'preco' => $preco,
                'estoque' => $estoque,
                'id' => $idVariacao,
                'produto' => $idProduto,
            ]);
            $sucesso = true;
        }
    }

    if ($action === 'excluir_variacao') {
        $idVariacao = $_POST['id_variacao'] ?? '';

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM VariacaoProduto WHERE FKProduto = :produto");
        $stmtCount->execute(['produto' => $idProduto]);
        $totalVariacoes = (int) $stmtCount->fetchColumn();

        // Nunca deixa o produto sem nenhuma variação — storefront sempre lê preço/estoque por ali.
        if ($idVariacao !== '' && $totalVariacoes > 1) {
            $stmt = $pdo->prepare("DELETE FROM VariacaoProduto WHERE IDVariacao = :id AND FKProduto = :produto");
            $stmt->execute(['id' => $idVariacao, 'produto' => $idProduto]);
            $sucesso = true;
        } else {
            $sucesso = false;
        }
    }

    if ($action === 'adicionar_imagem') {
        if (!empty($_FILES['imagem']['tmp_name'])) {
            $midia = uploadImagem($_FILES['imagem'], 'produtos');
            if ($midia) {
                $valor1 = trim($_POST['valor_atributo_1'] ?? '');
                $valor2 = trim($_POST['valor_atributo_2'] ?? '');

                $stmtOrdem = $pdo->prepare("SELECT COALESCE(MAX(Ordem), -1) + 1 FROM ImagemProduto WHERE FKProduto = :produto");
                $stmtOrdem->execute(['produto' => $idProduto]);
                $proximaOrdem = (int) $stmtOrdem->fetchColumn();

                $stmt = $pdo->prepare("INSERT INTO ImagemProduto (IDImagem, FKProduto, ValorAtributo1, ValorAtributo2, Url, TipoMidia, Ordem) VALUES (:id, :produto, :valor1, :valor2, :url, :tipo, :ordem)");
                $stmt->execute([
                    'id' => gerarUuid(),
                    'produto' => $idProduto,
                    'valor1' => $valor1 !== '' ? $valor1 : null,
                    'valor2' => $valor2 !== '' ? $valor2 : null,
                    'url' => $midia['url'],
                    'tipo' => $midia['tipo'],
                    'ordem' => $proximaOrdem,
                ]);
                $sucesso = true;
            } else {
                $sucesso = false;
            }
        }
    }

    if ($action === 'excluir_imagem') {
        $idImagem = $_POST['id_imagem'] ?? '';
        if ($idImagem !== '') {
            $stmt = $pdo->prepare("DELETE FROM ImagemProduto WHERE IDImagem = :id AND FKProduto = :produto");
            $stmt->execute(['id' => $idImagem, 'produto' => $idProduto]);
            $sucesso = true;
        }
    }

    $destino = URL_BASE . '/admin/produto/editar.php?id=' . urlencode($idProduto);
    $destino .= $sucesso ? '&ok=1' : '&erro=' . urlencode($erroCodigo ?? 'geral');
    if ($avisoCodigo) {
        $destino .= '&aviso=' . urlencode($avisoCodigo);
    }
    header('Location: ' . $destino);
    exit;
}

$errosMap = [
    'geral' => 'Não foi possível concluir a ação (o produto precisa de ao menos 1 variação e 1 imagem válida).',
    'preco_invalido' => 'O preço da variação precisa ser maior que R$ 0,00.',
];
$avisosMap = [
    'incompleto_preco_imagem' => 'Salvo, mas continua em rascunho: falta uma variação com preço e pelo menos 1 foto pra poder ficar visível na loja.',
    'incompleto_preco' => 'Salvo, mas continua em rascunho: nenhuma variação tem preço definido (maior que R$ 0,00) ainda.',
    'incompleto_imagem' => 'Salvo, mas continua em rascunho: o produto ainda não tem nenhuma foto.',
    'nao_e_simples' => 'Continua como "com variações": pra virar produto simples, primeiro exclua as variações extras até sobrar só 1.',
];

$sucesso = isset($_GET['ok']) ? 'Operação realizada com sucesso.' : null;
$erro = isset($_GET['erro']) ? ($errosMap[$_GET['erro']] ?? $errosMap['geral']) : null;
$aviso = isset($_GET['aviso']) ? ($avisosMap[$_GET['aviso']] ?? null) : null;

$categorias = obterCategoriasArvore();
$caixasEnvio = obterCaixasEnvio();
$variacoes = obterVariacoesPorProduto($idProduto);
$imagens = obterImagensPorProduto($idProduto);
// Sem eixo configurado = produto simples (1 variação só, sem "Cor"/"Tamanho" pra distinguir) —
// esconde toda a complexidade de variação pra quem só quer cadastrar algo com preço e estoque.
$modoSimples = empty($produto['NomeAtributo1']);
// Valores de eixo já usados em alguma variação — popula os seletores de "pra qual valor essa
// foto/vídeo vale" na hora de anexar mídia.
$valoresEixo1Existentes = array_values(array_unique(array_filter(array_column($variacoes, 'ValorAtributo1'))));
$valoresEixo2Existentes = array_values(array_unique(array_filter(array_column($variacoes, 'ValorAtributo2'))));

require __DIR__ . '/../_topo.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Editar produto</h1>
    <div class="d-flex gap-2">
        <a href="<?= URL_BASE ?>/produto.php?id=<?= urlencode($idProduto) ?>&preview=1" target="_blank" rel="noopener" class="btn btn-outline-secondary rounded-pill btn-sm">
            <i class="bi bi-eye"></i> Pré-visualizar
        </a>
        <a href="<?= URL_BASE ?>/admin/produto/index.php" class="btn btn-outline-secondary rounded-pill btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<?php if ($sucesso): ?><script>document.addEventListener('DOMContentLoaded', function () { mostrarToastSucesso('Operação realizada com sucesso.'); });</script><?php endif; ?>
<?php if ($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>
<?php if ($aviso): ?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> <?= $aviso ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card p-4">
            <h2 class="h5 mb-3">Dados básicos</h2>
            <form method="post">
                <input type="hidden" name="action" value="editar">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($produto['Nome']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="4"><?= htmlspecialchars($produto['Descricao'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoria</label>
                    <select name="fk_categoria" class="form-select">
                        <option value="" class="opcao-titulo">Sem categoria</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= htmlspecialchars($categoria['IDCategoria']) ?>" <?= $categoria['IDCategoria'] === $produto['FKCategoria'] ? 'selected' : '' ?>>
                                <?= str_repeat('— ', $categoria['Nivel']) ?><?= htmlspecialchars($categoria['Nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Caixa de envio</label>
                    <select name="fk_caixa_envio" class="form-select">
                        <option value="" class="opcao-titulo">Sem caixa definida</option>
                        <?php foreach ($caixasEnvio as $caixa): ?>
                            <option value="<?= htmlspecialchars($caixa['IDCaixaEnvio']) ?>" <?= $caixa['IDCaixaEnvio'] === $produto['FKCaixaEnvio'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($caixa['Nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Mesma caixa pra todas as variações desse produto (cor/tamanho não muda o tamanho da embalagem). <a href="<?= URL_BASE ?>/admin/entregas/index.php" class="link-marca">Gerenciar caixas</a></div>
                </div>
                <div class="mb-3">
                    <label class="form-label d-block">Tipo de produto</label>
                    <input type="radio" class="btn-check" name="tipo_produto" id="tipoSimples" value="simples" <?= $modoSimples ? 'checked' : '' ?> onchange="document.getElementById('camposEixos').classList.add('d-none')">
                    <label class="btn btn-outline-secondary" for="tipoSimples">Produto simples</label>
                    <input type="radio" class="btn-check" name="tipo_produto" id="tipoVariavel" value="variavel" <?= !$modoSimples ? 'checked' : '' ?> onchange="document.getElementById('camposEixos').classList.remove('d-none')">
                    <label class="btn btn-outline-secondary" for="tipoVariavel">Com variações (cor, tamanho...)</label>
                </div>
                <div class="mb-3 <?= $modoSimples ? 'd-none' : '' ?>" id="camposEixos">
                    <label class="form-label">Esse produto varia por</label>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <input type="text" name="nome_atributo_1" class="form-control" placeholder="ex: Cor" value="<?= htmlspecialchars($produto['NomeAtributo1'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <input type="text" name="nome_atributo_2" class="form-control" placeholder="ex: Tamanho" value="<?= htmlspecialchars($produto['NomeAtributo2'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-text">Ex: "Cor" + "Tamanho" — nem toda combinação precisa existir (pode ter Azul só no 40, sem ter Preto no 40). Pra voltar a "produto simples" depois, exclua as variações extras até sobrar só 1.</div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="checkbox" name="ativo" class="form-check-input" id="ativoSwitch" <?= $produto['Ativo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ativoSwitch">Visível na loja</label>
                </div>
                <button type="submit" class="btn btn-marca rounded-pill">Salvar</button>
            </form>
        </div>

        <div class="card p-4 mt-4">
            <h2 class="h5 mb-3">Imagens e vídeos</h2>
            <div class="row g-2 mb-3">
                <?php foreach ($imagens as $imagem): ?>
                    <div class="col-4">
                        <div class="position-relative">
                            <?php if ($imagem['TipoMidia'] === 'video'): ?>
                                <video src="<?= htmlspecialchars(urlAsset($imagem['Url'])) ?>" class="img-fluid rounded" style="aspect-ratio: 1; object-fit: cover; background: #000;" muted></video>
                                <span class="position-absolute bottom-0 start-0 m-1 badge-admin px-2 py-1 small"><i class="bi bi-camera-video-fill"></i></span>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars(urlAsset($imagem['Url'])) ?>" class="img-fluid rounded" style="aspect-ratio: 1; object-fit: cover;" alt="">
                            <?php endif; ?>
                            <form method="post" data-confirmar="Excluir <?= $imagem['TipoMidia'] === 'video' ? 'este vídeo' : 'esta imagem' ?>?" class="position-absolute top-0 end-0 m-1">
                                <input type="hidden" name="action" value="excluir_imagem">
                                <input type="hidden" name="id_imagem" value="<?= htmlspecialchars($imagem['IDImagem']) ?>">
                                <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                        <?php if (!$modoSimples): ?>
                            <div class="text-secundario small text-center mt-1">
                                <?= htmlspecialchars(descricaoVariacao($imagem) ?? 'todas') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$imagens): ?>
                    <p class="text-secundario mb-0">Nenhuma imagem ainda.</p>
                <?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                <input type="hidden" name="action" value="adicionar_imagem">
                <?php if (!$modoSimples): ?>
                    <div class="row g-2">
                        <div class="col">
                            <select name="valor_atributo_1" class="form-select">
                                <option value="" class="opcao-titulo">Todas (<?= htmlspecialchars($produto['NomeAtributo1']) ?>)</option>
                                <?php foreach ($valoresEixo1Existentes as $valor): ?>
                                    <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($valor) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($produto['NomeAtributo2']): ?>
                            <div class="col">
                                <select name="valor_atributo_2" class="form-select">
                                    <option value="" class="opcao-titulo">Todos (<?= htmlspecialchars($produto['NomeAtributo2']) ?>)</option>
                                    <?php foreach ($valoresEixo2Existentes as $valor): ?>
                                        <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($valor) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-text mt-0">Deixe em "Todas/Todos" pra essa foto/vídeo aparecer em qualquer valor daquele eixo — ex: escolhendo só "Vermelho" (sem travar <?= htmlspecialchars($produto['NomeAtributo2'] ?? 'o outro eixo') ?>), a foto vale pra Vermelho em qualquer <?= htmlspecialchars($produto['NomeAtributo2'] ?? 'variação') ?>, sem precisar subir de novo pra cada uma.</div>
                <?php endif; ?>
                <div class="d-flex gap-2">
                    <input type="file" name="imagem" class="form-control" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.mov" required>
                    <button type="submit" class="btn btn-marca rounded-pill text-nowrap">Enviar</button>
                </div>
                <div class="form-text mt-0">Foto: até 5MB. Vídeo: até 30MB. Arquivo maior que isso pode fazer a página travar sem aviso — se acontecer, tente um arquivo menor.</div>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card p-4">
            <?php if ($modoSimples): ?>
                <h2 class="h5 mb-3">Preço e estoque</h2>
                <?php $variacaoUnica = $variacoes[0] ?? null; ?>
                <?php if ($variacaoUnica): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="editar_variacao">
                        <input type="hidden" name="id_variacao" value="<?= htmlspecialchars($variacaoUnica['IDVariacao']) ?>">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">Preço</label>
                                <input type="text" name="preco" class="form-control mascara-preco" inputmode="numeric" value="<?= htmlspecialchars(formatarPreco($variacaoUnica['Preco'])) ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Estoque</label>
                                <input type="number" name="estoque" class="form-control" min="0" value="<?= (int) $variacaoUnica['Estoque'] ?>" required>
                            </div>
                        </div>
                        <div class="form-text mb-3">SKU: <?= htmlspecialchars($variacaoUnica['SKU']) ?></div>
                        <button type="submit" class="btn btn-marca rounded-pill">Salvar</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Variações</h2>
                    <button class="btn btn-sm btn-marca rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCriarVariacao">
                        <i class="bi bi-plus-lg"></i> Nova variação
                    </button>
                </div>
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= htmlspecialchars(implode(' / ', array_filter([$produto['NomeAtributo1'] ?? null, $produto['NomeAtributo2'] ?? null])) ?: 'Variação') ?></th>
                            <th>SKU</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th style="width: 110px; min-width: 110px; max-width: 110px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($variacoes as $variacao): ?>
                            <tr>
                                <td><?= htmlspecialchars(descricaoVariacao($variacao) ?? 'Padrão') ?></td>
                                <td class="text-secundario"><?= htmlspecialchars($variacao['SKU']) ?></td>
                                <td><?= formatarPreco($variacao['Preco']) ?></td>
                                <td><?= $variacao['Estoque'] == 0 ? '<span class="badge-perigo px-2 py-1">0</span>' : (int) $variacao['Estoque'] ?></td>
                                <td style="width: 110px; min-width: 110px; max-width: 110px;">
                                    <button class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalEditarVariacao<?= $variacao['IDVariacao'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" class="d-inline" data-confirmar="Excluir esta variação?">
                                        <input type="hidden" name="action" value="excluir_variacao">
                                        <input type="hidden" name="id_variacao" value="<?= htmlspecialchars($variacao['IDVariacao']) ?>">
                                        <button type="submit" class="btn btn-sm btn-perigo rounded-pill"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$modoSimples): ?>
<?php foreach ($variacoes as $variacao): ?>
    <div class="modal fade" id="modalEditarVariacao<?= $variacao['IDVariacao'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar variação</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar_variacao">
                        <input type="hidden" name="id_variacao" value="<?= htmlspecialchars($variacao['IDVariacao']) ?>">
                        <?php if ($produto['NomeAtributo1']): ?>
                            <div class="row g-3 mb-3">
                                <div class="<?= $produto['NomeAtributo2'] ? 'col-sm-6' : '' ?>">
                                    <label class="form-label"><?= htmlspecialchars($produto['NomeAtributo1']) ?></label>
                                    <input type="text" name="valor_atributo_1" class="form-control" value="<?= htmlspecialchars($variacao['ValorAtributo1'] ?? '') ?>">
                                </div>
                                <?php if ($produto['NomeAtributo2']): ?>
                                    <div class="col-sm-6">
                                        <label class="form-label"><?= htmlspecialchars($produto['NomeAtributo2']) ?></label>
                                        <input type="text" name="valor_atributo_2" class="form-control" value="<?= htmlspecialchars($variacao['ValorAtributo2'] ?? '') ?>">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label">Atributo (opcional)</label>
                                <input type="text" name="valor_atributo_1" class="form-control" value="<?= htmlspecialchars($variacao['ValorAtributo1'] ?? '') ?>">
                                <div class="form-text">Pra ter 2 eixos separados (ex: Cor + Tamanho), configure em "Esse produto varia por" nos Dados básicos.</div>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($variacao['SKU']) ?>" placeholder="gerado automaticamente se deixar em branco">
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">Preço</label>
                                <input type="text" name="preco" class="form-control mascara-preco" inputmode="numeric" value="<?= htmlspecialchars(formatarPreco($variacao['Preco'])) ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Estoque</label>
                                <input type="number" name="estoque" class="form-control" min="0" value="<?= (int) $variacao['Estoque'] ?>" required>
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

<div class="modal fade" id="modalCriarVariacao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Nova variação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="criar_variacao">
                    <?php if ($produto['NomeAtributo1']): ?>
                        <div class="row g-3 mb-3">
                            <div class="<?= $produto['NomeAtributo2'] ? 'col-sm-6' : '' ?>">
                                <label class="form-label"><?= htmlspecialchars($produto['NomeAtributo1']) ?></label>
                                <input type="text" name="valor_atributo_1" class="form-control">
                            </div>
                            <?php if ($produto['NomeAtributo2']): ?>
                                <div class="col-sm-6">
                                    <label class="form-label"><?= htmlspecialchars($produto['NomeAtributo2']) ?></label>
                                    <input type="text" name="valor_atributo_2" class="form-control">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label">Atributo (opcional)</label>
                            <input type="text" name="valor_atributo_1" class="form-control">
                            <div class="form-text">Pra ter 2 eixos separados (ex: Cor + Tamanho), configure em "Esse produto varia por" nos Dados básicos.</div>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" placeholder="gerado automaticamente se deixar em branco">
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Preço</label>
                            <input type="text" name="preco" class="form-control mascara-preco" inputmode="numeric" placeholder="R$ 0,00" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Estoque</label>
                            <input type="number" name="estoque" class="form-control" min="0" value="0" required>
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
<?php endif; ?>
<?php require __DIR__ . '/../_rodape.php'; ?>

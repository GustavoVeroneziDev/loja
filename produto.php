<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
garantirTabelaProduto();
garantirTabelaVariacaoProduto();
garantirTabelaImagemProduto();
garantirTabelaFavorito();

$idProduto = $_GET['id'] ?? '';
$produto = obterProdutoPorId($idProduto);

// Admin pode ver o produto mesmo em rascunho via link de pré-visualização (?preview=1) — precisa
// ser um link explícito, não "todo admin logado vê tudo", senão a checagem de Ativo não serve pra nada.
$modoPreview = adminLogado() && isset($_GET['preview']);

if (!$produto || (!$produto['Ativo'] && !$modoPreview)) {
    header('Location: ' . URL_BASE . '/index.php');
    exit;
}

$variacoes = obterVariacoesPorProduto($idProduto);
$imagens = obterImagensPorProduto($idProduto);
$favoritado = ehFavorito($idProduto);

require __DIR__ . '/geral/header.php';
?>
<?php if ($modoPreview && !$produto['Ativo']): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <span><i class="bi bi-eye"></i> Pré-visualização — este produto está em rascunho, ainda não aparece na loja pros clientes.</span>
        <a href="<?= URL_BASE ?>/admin/produto/editar.php?id=<?= urlencode($idProduto) ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-left"></i> Voltar pro admin
        </a>
    </div>
<?php endif; ?>
<div class="row g-4">
    <div class="col-md-6">
        <?php if ($imagens): ?>
            <div id="carrosselProduto" class="carousel slide">
                <div class="carousel-inner rounded">
                    <?php foreach ($imagens as $i => $imagem): ?>
                        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                            <img src="<?= htmlspecialchars(urlAsset($imagem['Url'])) ?>" class="d-block w-100" style="aspect-ratio: 1; object-fit: cover;" alt="<?= htmlspecialchars($produto['Nome']) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($imagens) > 1): ?>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carrosselProduto" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carrosselProduto" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <img src="<?= htmlspecialchars(urlAsset('/geral/img/uscountry-logosolo.svg')) ?>" class="w-100 rounded" style="aspect-ratio: 1; object-fit: contain; background: #f5f5f5;" alt="<?= htmlspecialchars($produto['Nome']) ?>">
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <h1 class="h3 mb-0 titulo-estilizado"><?= htmlspecialchars($produto['Nome']) ?></h1>
            <?php if (clienteLogado()): ?>
                <form method="post" action="<?= URL_BASE ?>/usuario/favoritos.php" class="flex-shrink-0">
                    <input type="hidden" name="action" value="alternar">
                    <input type="hidden" name="produto_id" value="<?= htmlspecialchars($produto['IDProduto']) ?>">
                    <input type="hidden" name="voltar_para" value="<?= htmlspecialchars(URL_BASE . '/produto.php?id=' . $produto['IDProduto']) ?>">
                    <button type="submit" class="btn-favorito <?= $favoritado ? 'ativo' : '' ?>" aria-label="<?= $favoritado ? 'Remover dos favoritos' : 'Favoritar' ?>">
                        <i class="bi <?= $favoritado ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                    </button>
                </form>
            <?php else: ?>
                <a href="<?= URL_BASE ?>/usuario/login.php" class="btn-favorito flex-shrink-0" aria-label="Entrar pra favoritar">
                    <i class="bi bi-heart"></i>
                </a>
            <?php endif; ?>
        </div>
        <p class="text-secundario"><?= nl2br(htmlspecialchars($produto['Descricao'] ?? '')) ?></p>

        <?php
            // Valores distintos de cada eixo, na ordem em que aparecem entre as variações —
            // nem toda combinação de eixo 1 x eixo 2 precisa existir (ex: Azul só no 40).
            $valoresEixo1 = [];
            $valoresEixo2 = [];
            foreach ($variacoes as $v) {
                if ($v['ValorAtributo1'] !== null && !in_array($v['ValorAtributo1'], $valoresEixo1, true)) {
                    $valoresEixo1[] = $v['ValorAtributo1'];
                }
                if ($v['ValorAtributo2'] !== null && !in_array($v['ValorAtributo2'], $valoresEixo2, true)) {
                    $valoresEixo2[] = $v['ValorAtributo2'];
                }
            }
            $modoEixos = count($variacoes) > 1 && $produto['NomeAtributo1'] && $valoresEixo1;
        ?>
        <form method="post" action="<?= URL_BASE ?>/carrinho.php" id="formAdicionarCarrinho">
            <input type="hidden" name="action" value="adicionar">

            <?php if ($modoEixos): ?>
                <input type="hidden" name="variacao_id" id="campoVariacaoId" value="">
                <div class="mb-3">
                    <label class="form-label d-block"><?= htmlspecialchars($produto['NomeAtributo1']) ?></label>
                    <div class="btn-group flex-wrap" role="group">
                        <?php foreach ($valoresEixo1 as $i => $valor): ?>
                            <input type="radio" class="btn-check" name="opcao_eixo_1" value="<?= htmlspecialchars($valor) ?>" id="eixo1-<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary" for="eixo1-<?= $i ?>"><?= htmlspecialchars($valor) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($produto['NomeAtributo2'] && $valoresEixo2): ?>
                    <div class="mb-3">
                        <label class="form-label d-block"><?= htmlspecialchars($produto['NomeAtributo2']) ?></label>
                        <div class="btn-group flex-wrap" role="group">
                            <?php foreach ($valoresEixo2 as $i => $valor): ?>
                                <input type="radio" class="btn-check" name="opcao_eixo_2" value="<?= htmlspecialchars($valor) ?>" id="eixo2-<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>>
                                <label class="btn btn-outline-secondary" for="eixo2-<?= $i ?>"><?= htmlspecialchars($valor) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <p class="badge-atencao px-2 py-1 small d-none mb-3" id="avisoIndisponivel">Essa combinação não está disponível.</p>
                <script id="dadosVariacoes" type="application/json"><?= json_encode(array_map(fn($v) => [
                    'id' => $v['IDVariacao'],
                    'valor1' => $v['ValorAtributo1'],
                    'valor2' => $v['ValorAtributo2'],
                    'preco' => (float) $v['Preco'],
                    'estoque' => (int) $v['Estoque'],
                ], $variacoes)) ?></script>
            <?php elseif (count($variacoes) > 1): ?>
                <div class="mb-3">
                    <label class="form-label d-block">Opção</label>
                    <div class="btn-group flex-wrap" role="group">
                        <?php foreach ($variacoes as $i => $variacao): ?>
                            <input type="radio" class="btn-check" name="variacao_id" value="<?= htmlspecialchars($variacao['IDVariacao']) ?>" id="variacao<?= $variacao['IDVariacao'] ?>"
                                   data-preco="<?= $variacao['Preco'] ?>" data-estoque="<?= (int) $variacao['Estoque'] ?>"
                                   <?= $i === 0 ? 'checked' : '' ?> onchange="atualizarVariacaoSelecionada(this)">
                            <label class="btn btn-outline-secondary" for="variacao<?= $variacao['IDVariacao'] ?>">
                                <?= htmlspecialchars(descricaoVariacao($variacao) ?? 'Padrão') ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="variacao_id" value="<?= htmlspecialchars($variacoes[0]['IDVariacao'] ?? '') ?>">
            <?php endif; ?>

            <p class="h3 text-marca" id="precoSelecionado"><?= formatarPreco($variacoes[0]['Preco'] ?? 0) ?></p>
            <?php $estoqueInicial = (int) ($variacoes[0]['Estoque'] ?? 0); ?>
            <p class="mb-3" id="estoqueSelecionado">
                <?php if ($estoqueInicial === 0): ?>
                    <span class="text-secundario small">Fora de estoque</span>
                <?php elseif ($estoqueInicial <= 5): ?>
                    <span class="badge-atencao px-2 py-1 small"><i class="bi bi-fire"></i> Só restam <?= $estoqueInicial ?> em estoque</span>
                <?php endif; ?>
            </p>

            <div class="d-flex gap-2 align-items-center mb-3">
                <label for="quantidade" class="form-label mb-0 text-secundario small">Quantidade</label>
                <div class="stepper-quantidade">
                    <input type="number" name="quantidade" id="quantidade" value="1" min="1" max="<?= (int) ($variacoes[0]['Estoque'] ?? 0) ?>" class="campo-quantidade" inputmode="numeric">
                    <div class="stepper-botoes">
                        <button type="button" class="stepper-botao" data-passo="1" aria-label="Aumentar quantidade">
                            <i class="bi bi-chevron-up"></i>
                        </button>
                        <button type="button" class="stepper-botao" data-passo="-1" aria-label="Diminuir quantidade">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button type="submit" id="btnAdicionar" class="btn btn-marca rounded-pill btn-lg" <?= ($variacoes[0]['Estoque'] ?? 0) > 0 ? '' : 'disabled' ?>>
                <i class="bi bi-cart-plus"></i> Adicionar ao carrinho
            </button>
        </form>
    </div>
</div>

<script>
// Estoque saudável não mostra nada (não é informação útil pro cliente) — só avisa quando
// zerou ou quando tá acabando (<=5), com o fogo pra chamar atenção pra urgência.
function textoEstoque(estoque) {
    if (estoque <= 0) return '<span class="text-secundario small">Fora de estoque</span>';
    if (estoque <= 5) return '<span class="badge-atencao px-2 py-1 small"><i class="bi bi-fire"></i> Só restam ' + estoque + ' em estoque</span>';
    return '';
}

// Botões +/- do stepper de quantidade ficam desabilitados nos limites — chamado toda vez
// que o máximo muda (troca de variação) e a cada clique/digitação no campo.
function atualizarBotoesQuantidade() {
    var campo = document.getElementById('quantidade');
    var stepper = campo.closest('.stepper-quantidade');
    if (!stepper) return;
    var min = parseInt(campo.min, 10) || 1;
    var max = parseInt(campo.max, 10) || 0;
    var valor = parseInt(campo.value, 10) || 0;
    stepper.querySelector('[data-passo="-1"]').disabled = valor <= min;
    stepper.querySelector('[data-passo="1"]').disabled = valor >= max;
}

function atualizarVariacaoSelecionada(input) {
    const preco = parseFloat(input.dataset.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const estoque = parseInt(input.dataset.estoque, 10);
    document.getElementById('precoSelecionado').textContent = preco;
    document.getElementById('estoqueSelecionado').innerHTML = textoEstoque(estoque);

    const campoQuantidade = document.getElementById('quantidade');
    campoQuantidade.max = estoque;
    if (parseInt(campoQuantidade.value, 10) > estoque) {
        campoQuantidade.value = estoque > 0 ? 1 : 0;
    }

    document.getElementById('btnAdicionar').disabled = estoque <= 0;
    atualizarBotoesQuantidade();
}

(function () {
    var campo = document.getElementById('quantidade');
    var stepper = campo.closest('.stepper-quantidade');
    if (!stepper) return;

    stepper.querySelectorAll('.stepper-botao').forEach(function (botao) {
        botao.addEventListener('click', function () {
            var min = parseInt(campo.min, 10) || 1;
            var max = parseInt(campo.max, 10) || 0;
            var passo = parseInt(botao.dataset.passo, 10);
            campo.value = Math.max(min, Math.min(max, (parseInt(campo.value, 10) || 0) + passo));
            atualizarBotoesQuantidade();
        });
    });

    campo.addEventListener('input', atualizarBotoesQuantidade);
    atualizarBotoesQuantidade();
})();

(function () {
    var dadosEl = document.getElementById('dadosVariacoes');
    if (!dadosEl) return;
    var variacoes = JSON.parse(dadosEl.textContent);
    var grupo1 = document.querySelectorAll('input[name="opcao_eixo_1"]');
    var grupo2 = document.querySelectorAll('input[name="opcao_eixo_2"]');
    var campoVariacaoId = document.getElementById('campoVariacaoId');
    var aviso = document.getElementById('avisoIndisponivel');
    var btnAdicionar = document.getElementById('btnAdicionar');
    var campoQuantidade = document.getElementById('quantidade');

    function valorMarcado(radios) {
        for (var i = 0; i < radios.length; i++) {
            if (radios[i].checked) return radios[i].value;
        }
        return null;
    }

    // Valores do OUTRO eixo que têm alguma variação com estoque de verdade combinando com
    // (eixo, valor) — trata "não existe essa combinação" e "existe mas estoque 0" do mesmo
    // jeito, porque pro cliente as duas coisas significam a mesma coisa: não dá pra comprar.
    function valoresDisponiveis(eixo, valor) {
        var chave = eixo === 1 ? 'valor1' : 'valor2';
        var chaveOutro = eixo === 1 ? 'valor2' : 'valor1';
        var disponiveis = new Set();
        variacoes.forEach(function (v) {
            if (v[chave] === valor && v.estoque > 0) disponiveis.add(v[chaveOutro]);
        });
        return disponiveis;
    }

    // Desabilita (apaga) no grupo os valores que não combinam com o valor atualmente marcado
    // no OUTRO eixo — e se o valor marcado nesse grupo virou inválido, pula sozinho pro
    // primeiro que ainda está disponível, sem esperar o cliente escolher errado primeiro.
    function filtrarGrupo(grupo, disponiveis) {
        if (!grupo.length || !disponiveis) return;
        var marcado = valorMarcado(grupo);
        var aindaValido = marcado !== null && disponiveis.has(marcado);
        grupo.forEach(function (r) {
            r.disabled = !disponiveis.has(r.value);
        });
        if (!aindaValido) {
            var proximo = Array.prototype.slice.call(grupo).find(function (r) { return disponiveis.has(r.value); });
            if (proximo) proximo.checked = true;
        }
    }

    function atualizar() {
        var v1 = grupo1.length ? valorMarcado(grupo1) : null;
        var v2 = grupo2.length ? valorMarcado(grupo2) : null;

        if (grupo1.length && grupo2.length) {
            filtrarGrupo(grupo2, v1 !== null ? valoresDisponiveis(1, v1) : null);
            v2 = valorMarcado(grupo2);
            filtrarGrupo(grupo1, v2 !== null ? valoresDisponiveis(2, v2) : null);
            v1 = valorMarcado(grupo1);
        }

        var encontrada = variacoes.find(function (v) {
            return (v1 === null || v.valor1 === v1) && (v2 === null || v.valor2 === v2);
        });

        if (encontrada) {
            campoVariacaoId.value = encontrada.id;
            document.getElementById('precoSelecionado').textContent = encontrada.preco.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            document.getElementById('estoqueSelecionado').innerHTML = textoEstoque(encontrada.estoque);
            campoQuantidade.max = encontrada.estoque;
            if (parseInt(campoQuantidade.value, 10) > encontrada.estoque) {
                campoQuantidade.value = encontrada.estoque > 0 ? 1 : 0;
            }
            btnAdicionar.disabled = encontrada.estoque <= 0;
            atualizarBotoesQuantidade();
            aviso.classList.add('d-none');
        } else {
            campoVariacaoId.value = '';
            btnAdicionar.disabled = true;
            aviso.classList.remove('d-none');
        }
    }

    grupo1.forEach(function (r) { r.addEventListener('change', atualizar); });
    grupo2.forEach(function (r) { r.addEventListener('change', atualizar); });
    atualizar();
})();
</script>
<?php require __DIR__ . '/geral/footer.php'; ?>

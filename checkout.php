<?php
session_start();
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/funcoes.php';
require_once __DIR__ . '/config/marca.php';
require_once __DIR__ . '/config/chaves.php';
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
$enderecoResolvido = resolverEndereco($enderecos, $enderecoSelecionadoId, $_POST);

// Cupom não depende de endereço — validar isso aqui não devia travar em "escolha um endereço".
$cupomCodigo = trim($_POST['cupom'] ?? '');
$erro = null;
$cupomErro = null;
$desconto = 0;
if ($cupomCodigo !== '') {
    $cupomAplicado = validarCupom($cupomCodigo);
    if ($cupomAplicado) {
        $desconto = calcularDescontoCupom($cupomAplicado, $subtotal);
    } else {
        $cupomErro = motivoCupomInvalido($cupomCodigo);
    }
}

$freteGratis = $subtotal >= FRETE_GRATIS_ACIMA_DE;

if ($metodoPost && $acao === 'confirmar') {
    if ($enderecoResolvido === null) {
        $erro = 'Escolha ou preencha um endereço de entrega válido.';
    } elseif ($cupomErro) {
        $erro = 'Corrija o cupom antes de confirmar.';
    } else {
        // Nunca confia no frete que a tela mostrou — recota aqui e cobra o valor real de agora.
        // Se a opção escolhida sumiu da cotação nova (rota mudou, preço mudou), não substitui pela
        // mais barata sem avisar: erro claro pedindo pra calcular de novo, é o cliente que escolhe.
        $frete = 0.0;
        $freteInfo = null;
        if ($freteGratis) {
            // segue com frete 0
        } else {
            $freteEscolhidoId = $_POST['frete_servico'] ?? '';
            $opcoesFrete = obterOpcoesFrete($enderecoResolvido['cep'], $itens) ?? [];
            if ($opcoesFrete) {
                $escolhida = null;
                foreach ($opcoesFrete as $op) {
                    if ($op['id'] === $freteEscolhidoId) {
                        $escolhida = $op;
                        break;
                    }
                }
                if (!$escolhida) {
                    $erro = 'A forma de envio escolhida não está mais disponível — calcule o frete de novo.';
                } else {
                    $frete = $escolhida['preco'];
                    $freteInfo = $escolhida;
                }
            } else {
                // Sem cotação real disponível (desconectado, API fora, produto sem caixa) — só
                // esse caso aceita seguir sem uma opção escolhida, porque só existia 1 valor possível.
                $frete = calcularFrete($enderecoResolvido['cep'], $subtotal);
            }
        }

        if (!$erro) {
            $resultado = criarPedido($_SESSION['usuario_id'], $enderecoResolvido, $cupomCodigo, $frete, $freteInfo);
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
    }
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

<form method="post" id="formCheckout">
    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <div class="card p-4 mb-4">
                <h2 class="h5 mb-3">Endereço de entrega</h2>
                <?php if ($enderecos): ?>
                    <div class="d-flex flex-column gap-2 mb-3">
                        <?php foreach ($enderecos as $e): ?>
                            <label class="card p-3 mb-0" style="cursor: pointer;">
                                <div class="d-flex gap-2 align-items-start">
                                    <input type="radio" name="endereco_id" value="<?= htmlspecialchars($e['IDEndereco']) ?>" class="form-check-input mt-1" <?= $enderecoSelecionadoId === $e['IDEndereco'] ? 'checked' : '' ?> onchange="alternarCamposNovoEndereco(false)">
                                    <div>
                                        <?= htmlspecialchars($e['Logradouro']) ?>, <?= htmlspecialchars($e['Numero']) ?><?= $e['Complemento'] ? ' — ' . htmlspecialchars($e['Complemento']) : '' ?><br>
                                        <span class="text-secundario small"><?= htmlspecialchars($e['Cidade']) ?>/<?= htmlspecialchars($e['UF']) ?> — CEP <?= htmlspecialchars($e['CEP']) ?></span>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                        <label class="card p-3 mb-0" style="cursor: pointer;">
                            <div class="d-flex gap-2 align-items-center">
                                <input type="radio" name="endereco_id" value="novo" class="form-check-input" <?= !$enderecoVeioDeSelecao ? 'checked' : '' ?> onchange="alternarCamposNovoEndereco(true)">
                                <span>Usar um novo endereço</span>
                            </div>
                        </label>
                    </div>
                <?php endif; ?>
                <div id="camposNovoEndereco" class="form-endereco <?= ($enderecos && $enderecoVeioDeSelecao) ? 'd-none' : '' ?>">
                    <?php require __DIR__ . '/usuario/_campos-endereco.php'; ?>
                </div>
                <script>
                    // Campo obrigatório escondido (display:none) não devia travar o envio do
                    // formulário — mas no navegador real ele trava mesmo assim ("is not focusable").
                    // Tira o "required" de verdade quando esconde, em vez de confiar que o navegador
                    // vai ignorar sozinho.
                    function alternarCamposNovoEndereco(mostrar) {
                        var container = document.getElementById('camposNovoEndereco');
                        container.classList.toggle('d-none', !mostrar);
                        ['cep', 'logradouro', 'numero', 'uf', 'cidade'].forEach(function (nome) {
                            var campo = container.querySelector('[name="' + nome + '"]');
                            if (campo) { campo.required = mostrar; }
                        });
                    }
                    alternarCamposNovoEndereco(!document.getElementById('camposNovoEndereco').classList.contains('d-none'));
                </script>
                <?php if (!$freteGratis): ?>
                    <button type="button" id="btnCalcularFrete" class="btn btn-outline-secondary rounded-pill mt-3">
                        <i class="bi bi-truck"></i> Calcular frete
                    </button>
                <?php endif; ?>
            </div>

            <div class="card p-4 mb-4">
                <h2 class="h5 mb-3">Frete</h2>
                <div id="freteConteudo">
                    <?php if ($freteGratis): ?>
                        <p class="text-sucesso fw-semibold mb-0"><i class="bi bi-check-circle-fill"></i> Frete grátis nessa compra!</p>
                    <?php else: ?>
                        <p class="text-secundario mb-0"><i class="bi bi-hourglass-split"></i> Escolha o endereço e clique em "Calcular frete".</p>
                    <?php endif; ?>
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

        <div class="col-12 col-lg-5">
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
                    <span class="text-secundario">
                        Frete
                        <span class="d-block text-secundario small" id="resumoFreteInfo"></span>
                    </span>
                    <span id="resumoFreteValor" class="<?= $freteGratis ? '' : 'text-secundario' ?>"><?= $freteGratis ? 'Grátis' : 'A calcular' ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-semibold">Total</span>
                    <span class="fw-semibold h4 mb-0" id="resumoTotal"><?= formatarPreco($subtotal - $desconto) ?></span>
                </div>
                <button type="submit" name="action" value="confirmar" id="btnConfirmarPedido" class="btn btn-marca rounded-pill w-100 py-2" <?= $freteGratis ? '' : 'disabled' ?>>Confirmar pedido</button>
                <p class="text-secundario small mt-2 mb-0 text-center">O pagamento é configurado na próxima etapa.</p>
            </div>
        </div>
    </div>
</form>

<?php if (!$freteGratis): ?>
<script>
(function () {
    var subtotalComDesconto = <?= json_encode(round($subtotal - $desconto, 2)) ?>;
    var freteConteudo = document.getElementById('freteConteudo');
    var resumoFreteValor = document.getElementById('resumoFreteValor');
    var resumoFreteInfo = document.getElementById('resumoFreteInfo');
    var resumoTotal = document.getElementById('resumoTotal');
    var btnConfirmar = document.getElementById('btnConfirmarPedido');
    var btnCalcularFrete = document.getElementById('btnCalcularFrete');

    // Cópia local — a escaparHtml() de geral/footer.php vive dentro de outro escopo (não é global de
    // verdade), chamar ela daqui dá ReferenceError. Mesma lógica (cobre texto E atributo).
    function escaparHtml(texto) {
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatarPrecoJs(valor) {
        return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function atualizarResumo(valorFrete, infoTexto) {
        resumoFreteValor.textContent = valorFrete > 0 ? formatarPrecoJs(valorFrete) : 'Grátis';
        resumoFreteValor.classList.remove('text-secundario');
        resumoFreteInfo.textContent = infoTexto || '';
        resumoTotal.textContent = formatarPrecoJs(subtotalComDesconto + valorFrete);
    }

    function estadoEsperando(mensagem) {
        btnConfirmar.disabled = true;
        resumoFreteValor.textContent = 'A calcular';
        resumoFreteValor.classList.add('text-secundario');
        resumoFreteInfo.textContent = '';
        resumoTotal.textContent = formatarPrecoJs(subtotalComDesconto);
        freteConteudo.innerHTML = '<p class="text-secundario mb-0"><i class="bi bi-hourglass-split"></i> ' + escaparHtml(mensagem) + '</p>';
    }

    function estadoErro(mensagem) {
        btnConfirmar.disabled = true;
        freteConteudo.innerHTML = '<p class="text-secundario mb-0"><i class="bi bi-exclamation-triangle text-danger"></i> ' + escaparHtml(mensagem) + '</p>';
    }

    function selecionarOpcao(opcao) {
        idSelecionadoAtual = opcao.id;
        atualizarResumo(opcao.preco, opcao.transportadora + (opcao.servico ? ' — ' + opcao.servico : ''));
        btnConfirmar.disabled = false;
    }

    // Mais barata primeiro por padrão (já é "melhor custo-benefício" na ausência de outro critério
    // explícito) — os 2 botões deixam escolher preço ou prazo, cada um alternando crescente/decrescente.
    var opcoesAtuais = [];
    var idSelecionadoAtual = null;
    var ordenacao = { campo: 'preco', direcao: 'asc' };

    function ordenarOpcoes(opcoes) {
        var copia = opcoes.slice();
        copia.sort(function (a, b) {
            var diff = a[ordenacao.campo] - b[ordenacao.campo];
            return ordenacao.direcao === 'asc' ? diff : -diff;
        });
        return copia;
    }

    function renderOpcoes(opcoes) {
        opcoesAtuais = opcoes;
        // Preserva a escolha atual ao reordenar — só cai na mais barata na primeira vez (ou se a
        // opção escolhida deixou de existir numa cotação nova).
        if (idSelecionadoAtual === null || !opcoes.some(function (o) { return o.id === idSelecionadoAtual; })) {
            idSelecionadoAtual = opcoes.length ? opcoes[0].id : null;
        }
        var ordenadas = ordenarOpcoes(opcoes);

        function rotuloBotao(campo, texto) {
            var ativo = ordenacao.campo === campo;
            var classe = ativo ? 'btn-marca' : 'btn-outline-secondary';
            var icone = ativo ? (ordenacao.direcao === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up';
            return '<button type="button" class="btn btn-sm rounded-pill ' + classe + '" data-campo="' + campo + '"><i class="bi ' + icone + '"></i> ' + texto + '</button>';
        }

        var html = '<div class="d-flex gap-2 mb-3">' +
            '<span class="text-secundario small align-self-center">Ordenar por:</span>' +
            rotuloBotao('preco', 'Preço') + rotuloBotao('prazo_dias', 'Prazo') +
            '</div><div class="d-flex flex-column gap-2">';
        ordenadas.forEach(function (op) {
            html += '<label class="card p-3 mb-0" style="cursor: pointer;">' +
                '<div class="d-flex justify-content-between align-items-center gap-3">' +
                '<div class="d-flex gap-2 align-items-start">' +
                '<input type="radio" name="frete_servico" value="' + escaparHtml(op.id) + '" class="form-check-input mt-1"' + (op.id === idSelecionadoAtual ? ' checked' : '') + '>' +
                '<div><div class="fw-semibold">' + escaparHtml(op.transportadora) + (op.servico ? ' — ' + escaparHtml(op.servico) : '') + '</div>' +
                '<span class="text-secundario small">' + (op.prazo_dias > 0 ? 'Chega em até ' + op.prazo_dias + ' dia(s) útil(eis)' : 'Prazo a confirmar') + '</span></div>' +
                '</div>' +
                '<span class="fw-semibold text-nowrap">' + formatarPrecoJs(op.preco) + '</span>' +
                '</div></label>';
        });
        html += '</div>';
        freteConteudo.innerHTML = html;

        freteConteudo.querySelectorAll('button[data-campo]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                var campo = botao.dataset.campo;
                if (ordenacao.campo === campo) {
                    ordenacao.direcao = ordenacao.direcao === 'asc' ? 'desc' : 'asc';
                } else {
                    ordenacao.campo = campo;
                    ordenacao.direcao = 'asc';
                }
                renderOpcoes(opcoesAtuais);
            });
        });
        freteConteudo.querySelectorAll('input[name="frete_servico"]').forEach(function (radio, indice) {
            radio.addEventListener('change', function () { selecionarOpcao(ordenadas[indice]); });
        });

        var opcaoSelecionada = opcoes.find(function (o) { return o.id === idSelecionadoAtual; });
        selecionarOpcao(opcaoSelecionada);
    }

    function coletarDadosEndereco() {
        var dados = new FormData();
        var enderecoIdRadio = document.querySelector('input[name="endereco_id"]:checked');
        dados.append('endereco_id', enderecoIdRadio ? enderecoIdRadio.value : '');
        ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'uf', 'cidade'].forEach(function (campo) {
            var el = document.querySelector('#camposNovoEndereco [name="' + campo + '"]');
            if (el) { dados.append(campo, el.value); }
        });
        return dados;
    }

    function buscarFrete() {
        idSelecionadoAtual = null; // cotação nova — não herda seleção de um endereço/busca anterior
        estadoEsperando('Calculando frete...');
        btnCalcularFrete.disabled = true;
        fetch('<?= URL_BASE ?>/checkout_frete.php', { method: 'POST', body: coletarDadosEndereco() })
            .then(function (resp) { return resp.json(); })
            .then(function (data) {
                btnCalcularFrete.disabled = false;
                if (!data.sucesso) {
                    estadoErro(data.erro || 'Não foi possível calcular o frete.');
                    return;
                }
                if (data.gratis) {
                    freteConteudo.innerHTML = '<p class="text-sucesso fw-semibold mb-0"><i class="bi bi-check-circle-fill"></i> Frete grátis nessa compra!</p>';
                    atualizarResumo(0, '');
                    return;
                }
                if (data.opcoes && data.opcoes.length) {
                    renderOpcoes(data.opcoes);
                    return;
                }
                if (data.fallback) {
                    freteConteudo.innerHTML = '<p class="mb-0">Frete: <strong>' + formatarPrecoJs(data.valor_fallback) + '</strong></p>' +
                        '<p class="text-secundario small mb-0 mt-1">Não foi possível consultar as transportadoras agora — usando valor padrão.</p>';
                    atualizarResumo(data.valor_fallback, '');
                    btnConfirmar.disabled = false;
                    return;
                }
                estadoErro('Não foi possível calcular o frete agora.');
            })
            .catch(function () {
                btnCalcularFrete.disabled = false;
                estadoErro('Falha de conexão ao calcular o frete. Tente de novo.');
            });
    }

    function resetarFrete() {
        idSelecionadoAtual = null;
        opcoesAtuais = [];
        estadoEsperando('Endereço alterado — clique em "Calcular frete" pra ver as opções.');
    }

    document.querySelectorAll('input[name="endereco_id"]').forEach(function (radio) {
        radio.addEventListener('change', resetarFrete);
    });
    var camposNovo = document.getElementById('camposNovoEndereco');
    camposNovo.addEventListener('input', resetarFrete);
    camposNovo.addEventListener('change', resetarFrete);

    btnCalcularFrete.addEventListener('click', buscarFrete);
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/geral/footer.php'; ?>

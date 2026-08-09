<?php /** @var string $nomeLoja Definida por geral/header.php, sempre incluído antes deste arquivo. */ ?>
</main>
<footer class="rodape-loja mt-5">
    <div class="container py-4 text-secundario small">
        <div class="row g-4">
            <div class="col-md-6">
                <strong class="text-marca"><?= htmlspecialchars($nomeLoja) ?></strong>
                <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars(TEXTO_SOBRE)) ?></p>
            </div>
            <div class="col-md-3">
                <strong>Contato</strong>
                <div class="d-flex gap-2">
                    <a href="mailto:<?= htmlspecialchars(TEXTO_CONTATO) ?>" class="icone-contato" aria-label="E-mail">
                        <i class="bi bi-envelope"></i>
                    </a>
                    <a href="https://wa.me/<?= htmlspecialchars(WHATSAPP_NUMERO) ?>" target="_blank" rel="noopener" class="icone-contato" aria-label="WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                    <a href="tel:<?= htmlspecialchars(preg_replace('/\D/', '', TELEFONE_CONTATO)) ?>" class="icone-contato" aria-label="Telefone">
                        <i class="bi bi-telephone"></i>
                    </a>
                    <a href="<?= htmlspecialchars(INSTAGRAM_URL) ?>" target="_blank" rel="noopener" class="icone-contato" aria-label="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="<?= htmlspecialchars(FACEBOOK_URL) ?>" target="_blank" rel="noopener" class="icone-contato" aria-label="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-3">
                <strong>Trocas e devoluções</strong>
                <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars(TEXTO_POLITICA_TROCA)) ?></p>
            </div>
        </div>
        <p class="mt-4 mb-0">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($nomeLoja) ?>. Todos os direitos reservados.
            <a href="<?= URL_BASE ?>/termos-de-uso.php" class="link-marca">Termos de Uso</a>
            <span class="opacity-50">v<?= htmlspecialchars(obterVersaoSistema()) ?></span>
        </p>
    </div>
</footer>

<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="bi bi-question-circle text-marca" style="font-size: 2rem;"></i>
                <p class="mt-3 mb-4" id="modalConfirmarTexto"></p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-perigo rounded-pill px-4" id="modalConfirmarBotao">Confirmar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.btn-toggle-senha').forEach(function(botao) {
        botao.addEventListener('click', function() {
            var campo = document.getElementById(this.dataset.alvo);
            var mostrando = campo.type === 'text';
            campo.type = mostrando ? 'password' : 'text';
            this.querySelector('i').className = mostrando ? 'bi bi-eye' : 'bi bi-eye-slash';
            this.setAttribute('aria-label', mostrando ? 'Mostrar senha' : 'Ocultar senha');
        });
    });

    // Telefone BR — vai alternando entre formato de fixo (XXXX-XXXX) e celular (XXXXX-XXXX)
    // conforme a quantidade de dígitos digitados, sem precisar escolher o tipo antes.
    document.querySelectorAll('.mascara-telefone').forEach(function (campo) {
        campo.addEventListener('input', function () {
            var d = campo.value.replace(/\D/g, '').slice(0, 11);
            if (d.length === 0) campo.value = '';
            else if (d.length <= 2) campo.value = '(' + d;
            else if (d.length <= 6) campo.value = '(' + d.slice(0, 2) + ') ' + d.slice(2);
            else if (d.length <= 10) campo.value = '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
            else campo.value = '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
        });
    });

    // CPF — formato fixo (sempre 11 dígitos), sem precisar alternar como o telefone.
    document.querySelectorAll('.mascara-cpf').forEach(function (campo) {
        campo.addEventListener('input', function () {
            var d = campo.value.replace(/\D/g, '').slice(0, 11);
            if (d.length <= 3) campo.value = d;
            else if (d.length <= 6) campo.value = d.slice(0, 3) + '.' + d.slice(3);
            else if (d.length <= 9) campo.value = d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
            else campo.value = d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
        });
    });

    // CEP — 00000-000, mesmo padrão dos outros (não mexe se já tiver menos de 6 dígitos digitados).
    document.querySelectorAll('.campo-cep').forEach(function (campo) {
        campo.addEventListener('input', function () {
            var d = campo.value.replace(/\D/g, '').slice(0, 8);
            campo.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
        });
    });

    // Endereço: Estado → Cidade em cascata (API do IBGE, nunca hardcode de município — muda de
    // vez em quando e é cidade demais pra manter na mão) + CEP preenchendo tudo automaticamente
    // (ViaCEP). Cada campo .form-endereco (pode ter mais de um na mesma página — um modal por
    // endereço salvo) tem sua própria instância isolada, sem vazar estado de um form pro outro.
    document.querySelectorAll('.form-endereco').forEach(function (form) {
        var campoUf = form.querySelector('.campo-uf');
        var campoCidade = form.querySelector('.campo-cidade');
        if (!campoUf || !campoCidade) return;

        // Nome de cidade vem de API externa — escapa antes de virar HTML (cobre tanto texto
        // quanto dentro de atributo, tipo o htmlspecialchars() do PHP), nunca confia cegamente em
        // dado de fora mesmo sendo fonte oficial (IBGE).
        function escaparHtml(texto) {
            return String(texto)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // Remove acento e caixa pra comparar nome de cidade entre ViaCEP e IBGE sem depender de
        // bater a grafia exata (as duas fontes são oficiais e devem bater, mas não custa nada
        // tratar com cuidado em vez de confiar cegamente).
        function normalizar(texto) {
            var codigoInicio = 0x0300;
            var codigoFim = 0x036f;
            var semAcento = (texto || '').normalize('NFD').split('').filter(function (ch) {
                var codigo = ch.charCodeAt(0);
                return codigo < codigoInicio || codigo > codigoFim;
            }).join('');
            return semAcento.toLowerCase().trim();
        }

        // Contador de geração — se o estado mudar nesse meio tempo (ou o CEP for trocado de novo
        // rapidinho), a resposta antiga que chegar depois é descartada em vez de sobrescrever o
        // que já é mais recente. Sem isso, duas respostas fora de ordem podem deixar a cidade
        // errada (ex: número de outra requisição indo parar onde devia ser nome de cidade).
        var geracaoAtual = 0;

        function carregarCidades(uf, cidadeParaSelecionar) {
            var minhaGeracao = ++geracaoAtual;
            campoCidade.disabled = true;
            campoCidade.innerHTML = '<option value="">Carregando...</option>';

            if (!uf) {
                campoCidade.innerHTML = '<option value="">Selecione o estado primeiro</option>';
                return;
            }

            fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados/' + uf + '/municipios')
                .then(function (r) {
                    if (!r.ok) throw new Error('IBGE respondeu ' + r.status);
                    return r.json();
                })
                .then(function (cidades) {
                    if (minhaGeracao !== geracaoAtual) return; // já tem requisição mais nova em andamento
                    var alvo = cidadeParaSelecionar ? normalizar(cidadeParaSelecionar) : null;
                    campoCidade.innerHTML = '<option value="">Selecione a cidade</option>' + cidades.map(function (c) {
                        var selecionada = alvo && normalizar(c.nome) === alvo;
                        var nomeEscapado = escaparHtml(c.nome);
                        return '<option value="' + nomeEscapado + '"' + (selecionada ? ' selected' : '') + '>' + nomeEscapado + '</option>';
                    }).join('');
                    campoCidade.disabled = false;
                })
                .catch(function () {
                    if (minhaGeracao !== geracaoAtual) return;
                    campoCidade.innerHTML = '<option value="">Não deu pra carregar as cidades agora — recarregue a página</option>';
                });
        }

        campoUf.addEventListener('change', function () {
            carregarCidades(campoUf.value, null);
        });

        // Endereço já vem com UF preenchido (editando um salvo) — carrega a cidade certa, mas só
        // quando o modal aparece de verdade, não em toda carga de página (evita 1 chamada de API
        // por endereço salvo mesmo pra quem nunca abre o modal de editar).
        var modal = form.closest('.modal');
        var cidadeInicial = campoCidade.dataset.cidadeInicial || null;
        if (modal) {
            modal.addEventListener('shown.bs.modal', function () {
                if (campoUf.value) carregarCidades(campoUf.value, cidadeInicial);
            }, { once: true });
        } else if (campoUf.value) {
            carregarCidades(campoUf.value, cidadeInicial);
        }

        var campoCep = form.querySelector('.campo-cep');
        if (!campoCep) return;
        var geracaoCep = 0;

        campoCep.addEventListener('blur', function () {
            var cep = campoCep.value.replace(/\D/g, '');
            if (cep.length !== 8) return;
            var minhaGeracaoCep = ++geracaoCep;

            fetch('https://viacep.com.br/ws/' + cep + '/json/')
                .then(function (r) {
                    if (!r.ok) throw new Error('ViaCEP respondeu ' + r.status);
                    return r.json();
                })
                .then(function (dados) {
                    if (minhaGeracaoCep !== geracaoCep || dados.erro) return;
                    // CEP é a fonte de verdade a partir daqui — sempre sobrescreve o que já
                    // estava no campo, não só completa o que tava vazio.
                    ['logradouro', 'bairro'].forEach(function (nome) {
                        var campo = form.querySelector('[name="' + nome + '"]');
                        if (campo && dados[nome]) campo.value = dados[nome];
                    });
                    if (dados.uf) {
                        campoUf.value = dados.uf;
                        carregarCidades(dados.uf, dados.localidade);
                    }
                })
                .catch(function () { /* falha na consulta não deve travar o preenchimento manual */ });
        });
    });

    (function () {
        var modalEl = document.getElementById('modalConfirmar');
        if (!modalEl) return;
        var modal = new bootstrap.Modal(modalEl);
        var texto = document.getElementById('modalConfirmarTexto');
        var botaoConfirmar = document.getElementById('modalConfirmarBotao');
        var formPendente = null;

        document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                formPendente = form;
                texto.textContent = form.dataset.confirmar;
                modal.show();
            });
        });

        botaoConfirmar.addEventListener('click', function () {
            modal.hide();
            if (formPendente) {
                formPendente.submit();
                formPendente = null;
            }
        });
    })();

    // Todo POST daqui recarrega a página (padrão do site inteiro) e o navegador, por padrão, volta
    // pro topo — chato em ação tipo favoritar no meio da vitrine. Guarda o scroll antes de qualquer
    // formulário enviar e restaura assim que a página volta, sem precisar declarar nada por página.
    (function () {
        var chave = 'scrollY:' + location.pathname;

        var salvo = sessionStorage.getItem(chave);
        if (salvo !== null) {
            sessionStorage.removeItem(chave);
            window.scrollTo(0, parseInt(salvo, 10));
        }

        document.addEventListener('submit', function () {
            sessionStorage.setItem(chave, window.scrollY);
        }, true);
    })();
</script>
</body>

</html>
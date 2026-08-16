</main>
<div class="text-center text-secundario small opacity-50 pb-3">v<?= htmlspecialchars(obterVersaoSistema()) ?></div>

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

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090">
    <div id="toastSucesso" class="toast toast-sucesso align-items-center border-0" role="status" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body"><i class="bi bi-check-circle-fill"></i> <span id="toastSucessoTexto"></span></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Confirmação de ação passa rápido no canto e some sozinha — nunca trava a tela pedindo pra
// "confirmar que confirmou". Erro continua sendo alert-danger persistente (isso o usuário
// precisa de fato ler), só o "deu certo" que virou passageiro.
function mostrarToastSucesso(mensagem) {
    var toastEl = document.getElementById('toastSucesso');
    if (!toastEl) return;
    document.getElementById('toastSucessoTexto').textContent = mensagem;
    new bootstrap.Toast(toastEl, { delay: 1200 }).show();
}

document.querySelectorAll('.mascara-preco').forEach(function (campo) {
    function formatar() {
        var centavos = parseInt(campo.value.replace(/\D/g, ''), 10) || 0;
        campo.value = (centavos / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }
    campo.addEventListener('input', function () {
        formatar();
        campo.setSelectionRange(campo.value.length, campo.value.length);
    });
});

// Peso (kg, 3 casas) e dimensão (cm, 1 casa) — mesmo princípio do preço, mas com quantidade de
// casas decimais diferente (peso precisa de mais precisão que altura/largura/comprimento).
function aplicarMascaraDecimal(seletor, casas) {
    var divisor = Math.pow(10, casas);
    document.querySelectorAll(seletor).forEach(function (campo) {
        campo.addEventListener('input', function () {
            var numeros = parseInt(campo.value.replace(/\D/g, ''), 10) || 0;
            campo.value = (numeros / divisor).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas });
            campo.setSelectionRange(campo.value.length, campo.value.length);
        });
    });
}
aplicarMascaraDecimal('.mascara-peso', 3);
aplicarMascaraDecimal('.mascara-dimensao', 1);

// Telefone, CPF e CEP — mesma máscara do site público (geral/footer.php), só que aqui é usada na
// config de remetente do admin em vez de formulário de cliente.
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

document.querySelectorAll('.mascara-cpf').forEach(function (campo) {
    campo.addEventListener('input', function () {
        var d = campo.value.replace(/\D/g, '').slice(0, 11);
        if (d.length <= 3) campo.value = d;
        else if (d.length <= 6) campo.value = d.slice(0, 3) + '.' + d.slice(3);
        else if (d.length <= 9) campo.value = d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
        else campo.value = d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
    });
});

document.querySelectorAll('.campo-cep').forEach(function (campo) {
    campo.addEventListener('input', function () {
        var d = campo.value.replace(/\D/g, '').slice(0, 8);
        campo.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
    });
});

// Linha de tabela expansível — mobile esconde coluna menos essencial (d-none d-md-table-cell) e
// mostra elas + as ações numa linha de detalhe escondida embaixo, que abre ao tocar na linha
// resumida. Reaproveitado em qualquer tabela do admin: só precisa da linha ter
// class="linha-expandivel" + data-alvo-expandir="idDaLinhaDeDetalhe", e opcionalmente um
// <i class="icone-expandir"> pra girar a setinha.
document.querySelectorAll('.linha-expandivel').forEach(function (linha) {
    linha.addEventListener('click', function (e) {
        if (e.target.closest('a, button, input, form')) {
            return; // deixa o próprio controle agir normal, não expande por cima
        }
        var alvo = document.getElementById(linha.dataset.alvoExpandir);
        if (!alvo) return;
        alvo.classList.toggle('d-none');
        var icone = linha.querySelector('.icone-expandir');
        if (icone) {
            icone.classList.toggle('bi-chevron-down');
            icone.classList.toggle('bi-chevron-up');
        }
    });
});

document.querySelectorAll('.btn-toggle-senha').forEach(function (botao) {
    botao.addEventListener('click', function () {
        var campo = document.getElementById(this.dataset.alvo);
        var mostrando = campo.type === 'text';
        campo.type = mostrando ? 'password' : 'text';
        this.querySelector('i').className = mostrando ? 'bi bi-eye' : 'bi bi-eye-slash';
        this.setAttribute('aria-label', mostrando ? 'Mostrar senha' : 'Ocultar senha');
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

// Todo POST daqui recarrega a página (padrão do site inteiro) e o navegador, por padrão, volta pro
// topo — chato depois de editar algo no meio de uma lista longa. Guarda o scroll antes de qualquer
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

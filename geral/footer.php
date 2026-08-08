<?php /** @var string $nomeLoja Definida por geral/header.php, sempre incluído antes deste arquivo. */ ?>
</main>
<footer class="border-top mt-5" style="border-color: rgba(0,0,0,.06) !important;">
    <div class="container py-4 text-secundario small">
        <div class="row g-4">
            <div class="col-md-6">
                <strong class="text-marca"><?= htmlspecialchars($nomeLoja) ?></strong>
                <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars(obterConfiguracaoLoja('texto_sobre', ''))) ?></p>
            </div>
            <div class="col-md-3">
                <strong>Contato</strong>
                <p class="mt-2 mb-0"><?= htmlspecialchars(obterConfiguracaoLoja('texto_contato', '')) ?></p>
            </div>
            <div class="col-md-3">
                <strong>Trocas e devoluções</strong>
                <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars(obterConfiguracaoLoja('texto_politica_troca', ''))) ?></p>
            </div>
        </div>
        <p class="mt-4 mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($nomeLoja) ?>. Todos os direitos reservados. <span class="opacity-50">v<?= htmlspecialchars(obterVersaoSistema()) ?></span></p>
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
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
</script>
</body>
</html>

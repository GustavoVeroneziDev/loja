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
</script>
</body>
</html>

</main>
<div class="text-center text-secundario small opacity-50 pb-3">v<?= htmlspecialchars(obterVersaoSistema()) ?></div>
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

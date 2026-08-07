<?php
// Parcial compartilhado por login.php e cadastro.php — quem inclui define $modoInicial
// ('login' ou 'cadastro'), $erroLogin e $erroCadastro antes do require.
?>
<div class="row">
    <div class="col-md-5 mx-auto">
        <div class="card p-4">
            <div id="painelLogin" class="auth-painel <?= $modoInicial === 'cadastro' ? 'd-none' : '' ?>">
                <h1 class="h4 mb-3">Entrar</h1>
                <?php if ($erroLogin): ?><div class="alert alert-danger"><?= htmlspecialchars($erroLogin) ?></div><?php endif; ?>
                <form method="post" action="<?= URL_BASE ?>/usuario/login.php">
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha</label>
                        <input type="password" name="senha" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-marca rounded-pill w-100">Entrar</button>
                </form>
                <p class="text-secundario small mt-3 mb-1"><a href="<?= URL_BASE ?>/usuario/recuperar-senha.php" class="link-marca">Esqueci minha senha</a></p>
                <p class="text-secundario small mb-0">Ainda não tem conta? <a href="<?= URL_BASE ?>/usuario/cadastro.php" class="link-marca" data-auth-toggle="cadastro">Criar conta</a></p>
            </div>
            <div id="painelCadastro" class="auth-painel <?= $modoInicial === 'cadastro' ? '' : 'd-none' ?>">
                <h1 class="h4 mb-3">Criar conta</h1>
                <?php if ($erroCadastro): ?><div class="alert alert-danger"><?= htmlspecialchars($erroCadastro) ?></div><?php endif; ?>
                <form method="post" action="<?= URL_BASE ?>/usuario/cadastro.php">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha</label>
                        <input type="password" name="senha" class="form-control" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-marca rounded-pill w-100">Criar conta</button>
                </form>
                <p class="text-secundario small mt-3 mb-0">Já tem conta? <a href="<?= URL_BASE ?>/usuario/login.php" class="link-marca" data-auth-toggle="login">Entrar</a></p>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-auth-toggle]').forEach(function (link) {
    link.addEventListener('click', function (e) {
        var painelAlvo = document.getElementById(this.dataset.authToggle === 'cadastro' ? 'painelCadastro' : 'painelLogin');
        var painelAtual = document.querySelector('.auth-painel:not(.d-none)');
        if (!painelAlvo || painelAlvo === painelAtual) return;

        e.preventDefault();
        history.pushState({}, '', this.getAttribute('href'));

        painelAtual.classList.add('auth-saindo');
        setTimeout(function () {
            painelAtual.classList.add('d-none');
            painelAtual.classList.remove('auth-saindo');
            painelAlvo.classList.remove('d-none');
            painelAlvo.classList.add('auth-entrando');
            requestAnimationFrame(function () {
                painelAlvo.classList.remove('auth-entrando');
            });
        }, 200);
    });
});
</script>

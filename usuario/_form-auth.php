<?php
// Parcial compartilhado por login.php e cadastro.php — quem inclui define $modoInicial
// ('login' ou 'cadastro'), $erroLogin e $erroCadastro antes do require.
?>
<div class="row">
    <div class="col-md-5 mx-auto">
        <div class="card p-4 p-md-5">
            <div id="painelLogin" class="auth-painel <?= $modoInicial === 'cadastro' ? 'd-none' : '' ?>">
                <h1 class="h4 mb-1">Bem-vindo de volta</h1>
                <?php if ($erroLogin): ?><div class="alert alert-danger"><?= htmlspecialchars($erroLogin) ?></div><?php endif; ?>
                <form method="post" action="<?= URL_BASE ?>/usuario/login.php">
                    <div class="form-floating mb-3">
                        <input type="email" name="email" id="loginEmail" class="form-control" placeholder="seuemail@exemplo.com" required>
                        <label for="loginEmail">E-mail</label>
                    </div>
                    <div class="form-floating mb-3 tem-toggle-senha">
                        <input type="password" name="senha" id="loginSenha" class="form-control" placeholder="Sua senha" required>
                        <label for="loginSenha">Senha</label>
                        <button type="button" class="btn-toggle-senha" data-alvo="loginSenha" aria-label="Mostrar senha">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <button type="submit" class="btn btn-marca rounded-pill w-100 py-2">Entrar</button>
                </form>
                <p class="text-secundario small mt-3 mb-1"><a href="<?= URL_BASE ?>/usuario/recuperar-senha.php" class="link-marca">Esqueci minha senha</a></p>
                <p class="text-secundario small mb-0">Ainda não tem conta? <a href="<?= URL_BASE ?>/usuario/cadastro.php" class="link-marca" data-auth-toggle="cadastro">Criar conta</a></p>
            </div>
            <div id="painelCadastro" class="auth-painel <?= $modoInicial === 'cadastro' ? '' : 'd-none' ?>">
                <h1 class="h4 mb-1">Criar conta</h1>
                <p class="text-secundario mb-4">Leva menos de um minuto.</p>
                <?php if ($erroCadastro): ?><div class="alert alert-danger"><?= htmlspecialchars($erroCadastro) ?></div><?php endif; ?>
                <form method="post" action="<?= URL_BASE ?>/usuario/cadastro.php">
                    <div class="form-floating mb-3">
                        <input type="text" name="nome" id="cadastroNome" class="form-control" placeholder="Seu nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                        <label for="cadastroNome">Nome</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="email" name="email" id="cadastroEmail" class="form-control" placeholder="seuemail@exemplo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        <label for="cadastroEmail">E-mail</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" name="telefone" id="cadastroTelefone" class="form-control" placeholder="Telefone" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
                        <label for="cadastroTelefone">Telefone (opcional)</label>
                    </div>
                    <div class="form-floating mb-3 tem-toggle-senha">
                        <input type="password" name="senha" id="cadastroSenha" class="form-control" placeholder="Senha" minlength="4" required>
                        <label for="cadastroSenha">Senha (mín. 4 caracteres)</label>
                        <button type="button" class="btn-toggle-senha" data-alvo="cadastroSenha" aria-label="Mostrar senha">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <button type="submit" class="btn btn-marca rounded-pill w-100 py-2">Criar conta</button>
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

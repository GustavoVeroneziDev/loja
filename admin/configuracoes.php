<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/funcoes.php';
exigirLoginAdmin();
garantirTabelaConfiguracaoLoja();
garantirConfiguracaoLojaPadrao();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos = ['nome_loja', 'cor_primaria', 'cor_secundaria', 'texto_sobre', 'texto_politica_troca', 'texto_contato'];
    foreach ($campos as $campo) {
        if (isset($_POST[$campo])) {
            salvarConfiguracaoLoja($campo, trim($_POST[$campo]));
        }
    }

    if (!empty($_FILES['logo']['tmp_name'])) {
        $urlLogo = uploadImagem($_FILES['logo'], 'marca');
        if ($urlLogo) {
            salvarConfiguracaoLoja('logo_url', $urlLogo);
        }
    }

    if (!empty($_FILES['favicon']['tmp_name'])) {
        $urlFavicon = uploadImagem($_FILES['favicon'], 'marca');
        if ($urlFavicon) {
            salvarConfiguracaoLoja('favicon_url', $urlFavicon);
        }
    }

    header('Location: ' . URL_BASE . '/admin/configuracoes.php?ok=1');
    exit;
}

$sucesso = isset($_GET['ok']) ? 'Configurações salvas com sucesso.' : null;

$nomeLoja = obterConfiguracaoLoja('nome_loja');
$corPrimaria = obterConfiguracaoLoja('cor_primaria');
$corSecundaria = obterConfiguracaoLoja('cor_secundaria');
$logoUrl = obterConfiguracaoLoja('logo_url');
$faviconUrl = obterConfiguracaoLoja('favicon_url', $logoUrl);
$textoSobre = obterConfiguracaoLoja('texto_sobre');
$textoPoliticaTroca = obterConfiguracaoLoja('texto_politica_troca');
$textoContato = obterConfiguracaoLoja('texto_contato');

require __DIR__ . '/_topo.php';
?>
<h1 class="h4 mb-4">Configurações da loja</h1>
<p class="text-secundario">Isso aqui é a "pele" — nome, cores, logo e textos institucionais. Fica salvo em <code>ConfiguracaoLoja</code> e é o que muda de cliente pra cliente sem tocar em código.</p>

<?php if ($sucesso): ?><div class="alert alert-success"><?= $sucesso ?></div><?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card p-4">
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Nome da loja</label>
                    <input type="text" name="nome_loja" class="form-control" value="<?= htmlspecialchars($nomeLoja) ?>" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label">Cor primária</label>
                        <input type="color" name="cor_primaria" class="form-control form-control-color w-100" value="<?= htmlspecialchars($corPrimaria) ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Cor secundária</label>
                        <input type="color" name="cor_secundaria" class="form-control form-control-color w-100" value="<?= htmlspecialchars($corSecundaria) ?>">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-sm-8">
                        <label class="form-label">Logo (navbar e rodapé)</label>
                        <div class="mb-2"><img src="<?= htmlspecialchars(urlAsset($logoUrl)) ?>" class="logo-loja" alt="Logo atual"></div>
                        <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.webp,.svg">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Favicon (aba do navegador)</label>
                        <div class="mb-2"><img src="<?= htmlspecialchars(urlAsset($faviconUrl)) ?>" style="height: 32px; width: 32px; object-fit: contain;" alt="Favicon atual"></div>
                        <input type="file" name="favicon" class="form-control" accept=".ico,.png,.svg">
                        <div class="form-text">Se não enviar, usa a logo.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sobre a loja</label>
                    <textarea name="texto_sobre" class="form-control" rows="3"><?= htmlspecialchars($textoSobre) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Política de trocas e devoluções</label>
                    <textarea name="texto_politica_troca" class="form-control" rows="3"><?= htmlspecialchars($textoPoliticaTroca) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contato</label>
                    <input type="text" name="texto_contato" class="form-control" value="<?= htmlspecialchars($textoContato) ?>">
                </div>
                <button type="submit" class="btn btn-marca rounded-pill">Salvar configurações</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_rodape.php'; ?>

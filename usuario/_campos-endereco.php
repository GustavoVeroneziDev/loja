<?php
/**
 * Campos de formulário de endereço — compartilhado entre usuario/enderecos.php (criar/editar) e
 * checkout.php (endereço novo inline). Espera $endereco em escopo (array do banco, ou null pra
 * formulário vazio) e $ufs (array UF => nome, de listaUfsBrasil()).
 */
$ufs = $ufs ?? listaUfsBrasil();
?>
<div class="row g-3">
    <div class="col-sm-5">
        <label class="form-label">CEP</label>
        <input type="text" name="cep" class="form-control campo-cep" inputmode="numeric" maxlength="9" placeholder="00000-000" value="<?= htmlspecialchars($endereco['CEP'] ?? '') ?>" required>
    </div>
    <div class="col-sm-7">
        <label class="form-label">Logradouro</label>
        <input type="text" name="logradouro" class="form-control" value="<?= htmlspecialchars($endereco['Logradouro'] ?? '') ?>" required>
    </div>
    <div class="col-sm-4">
        <label class="form-label">Número</label>
        <input type="text" name="numero" class="form-control" value="<?= htmlspecialchars($endereco['Numero'] ?? '') ?>" required>
    </div>
    <div class="col-sm-8">
        <label class="form-label">Complemento</label>
        <input type="text" name="complemento" class="form-control" placeholder="opcional" value="<?= htmlspecialchars($endereco['Complemento'] ?? '') ?>">
    </div>
    <div class="col-sm-4">
        <label class="form-label">Bairro</label>
        <input type="text" name="bairro" class="form-control" value="<?= htmlspecialchars($endereco['Bairro'] ?? '') ?>">
    </div>
    <div class="col-sm-2">
        <label class="form-label">UF</label>
        <select name="uf" class="form-select campo-uf" required>
            <option value="" class="opcao-titulo">--</option>
            <?php foreach ($ufs as $sigla => $nome): ?>
                <option value="<?= $sigla ?>" <?= ($endereco['UF'] ?? '') === $sigla ? 'selected' : '' ?>><?= $sigla ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-6">
        <label class="form-label">Cidade</label>
        <!-- Lista de município vem da API do IBGE (JS, no rodapé) — nunca hardcode, muda de vez em
             quando e é cidade demais pra manter na mão. Começa com só a cidade atual (se tiver, ao
             editar um endereço já salvo) até o JS trocar pela lista completa do estado escolhido. -->
        <select name="cidade" class="form-select campo-cidade" data-cidade-inicial="<?= htmlspecialchars($endereco['Cidade'] ?? '') ?>" <?= empty($endereco['UF']) ? 'disabled' : '' ?> required>
            <?php if (!empty($endereco['Cidade'])): ?>
                <option value="<?= htmlspecialchars($endereco['Cidade']) ?>" selected><?= htmlspecialchars($endereco['Cidade']) ?></option>
            <?php else: ?>
                <option value="" class="opcao-titulo">Selecione o estado primeiro</option>
            <?php endif; ?>
        </select>
    </div>
</div>
<div class="form-check mt-3">
    <input type="checkbox" name="principal" class="form-check-input" id="principal<?= htmlspecialchars($endereco['IDEndereco'] ?? 'novo') ?>" <?= ($endereco['Principal'] ?? false) ? 'checked' : '' ?>>
    <label class="form-check-label" for="principal<?= htmlspecialchars($endereco['IDEndereco'] ?? 'novo') ?>">Usar como endereço principal</label>
</div>

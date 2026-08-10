<?php
// Copie este arquivo para chaves.php e preencha com as credenciais reais.
// chaves.php fica fora do git (.gitignore) — cada clone/deploy tem sua própria integração.

// Melhor Envio — painel > Integrações > Tokens de Acesso > Gerar novo token. Token direto (JWT),
// sem Client ID/Secret, sem fluxo de autorização. Validade de ~1 ano; quando expirar, gera um novo
// no painel deles e substitui aqui.
define('MELHOR_ENVIO_AMBIENTE', 'sandbox'); // 'sandbox' ou 'producao' — precisa bater com onde o token foi gerado
define('MELHOR_ENVIO_TOKEN', '');

// CEP de origem e dados do remetente ficam no banco, não aqui — configuráveis em Admin > Entregas
// (é dado operacional, muda com mais frequência que credencial; edita igual o cliente edita os
// dados dele em "Minha conta").

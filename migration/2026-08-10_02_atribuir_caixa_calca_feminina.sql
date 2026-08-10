-- Complemento de 2026-08-10_01 — "Calça Jeans Boot Cut Country (Feminino)" tem esse sufixo no nome
-- em produção que não constava no mapeamento original (por nome), então ficou de fora. Pega direto
-- pelo ID (confirmado via admin/entregas/teste-frete.php) em vez de por nome dessa vez.

UPDATE Produto SET FKCaixaEnvio = (SELECT IDCaixaEnvio FROM CaixaEnvio WHERE Nome = 'Caixa M')
WHERE IDProduto = 'fd19b1a6-9368-11f1-a2dc-0200170312b6';

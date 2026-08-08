# Migration

Histórico de scripts SQL de dados (seed, reset, ajustes pontuais em produção) — não são
migração de schema (isso continua automático via `garantirTabelaX()` em `config/funcoes.php`,
nunca por arquivo).

Nome do arquivo: `AAAA-MM-DD_NN_descricao-curta.sql`, `NN` sequencial dentro do mesmo dia.

Cada script é feito pra rodar direto no phpMyAdmin de produção. Já rodado uma vez, fica aqui
só como registro — não roda de novo sozinho.

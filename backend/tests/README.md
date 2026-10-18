# Testes de integração

`browser.cjs` requer Playwright, Chromium e um banco MySQL **descartável** separado da produção. Não rode instalação ou limpeza sobre o banco da Hostinger em uso.

Valida instalação com contas definidas no teste, cookies HttpOnly/Secure, dois navegadores compartilhando pedidos, peso em kg, idempotência concorrente, bloqueio de privilégios de atendentes, CSRF, validação e conflito de versão, persistência de pendências e reenvio, edição/remoção, cadastros, revogação de sessão e proteção da administração.

O ambiente utilizado na validação local foi MySQL 8.4 e PHP 8.3/Apache (`Dockerfile.test`, extensão PDO MySQL compilada com verificação padrão de imagem). Para montar o teste, use os diretórios públicos e privados do pacote gerado, importe `schema.sql` em banco vazio e configure uma cópia **privada** de `config.php` com `origin` apontando para o endereço local do teste. Remova o redirecionamento HTTPS apenas da cópia descartável de `.htaccess` para teste em localhost; a distribuição mantém HTTPS e cookies seguros. Navegadores permitem cookies Secure no localhost de teste.

A variável `PADARIA_TEST_FIXTURE` aponta para um JSON local fora do checkout com `token`, `email`, `password`, `code` e `pin` exclusivamente fictícios, correspondentes ao `setup_token` da configuração de teste. O script não imprime esses valores. `PADARIA_PLAYWRIGHT_MODULE` permite apontar para uma instalação separada de Playwright. `PADARIA_TEST_URL` é a URL desse servidor (padrão `http://localhost:8787`).

Execute:

```sh
PADARIA_PLAYWRIGHT_MODULE=/caminho/node_modules/playwright \
PADARIA_TEST_FIXTURE=/caminho/privado/test.json \
node backend/tests/browser.cjs
```

O banco precisa estar vazio no começo: o teste executa o instalador uma vez. Para rodar novamente, recrie apenas o banco descartável. Não comite fixture, config.php ou senhas. Os testes de domínio continuam em `npm test`.

## Ponte de leitura VR

`python3 -m unittest discover -s tests -p 'test_*.py'` na raiz valida UN/KG sem usar pesavel como unidade, centavos, limites e reenvio durável da mesma operação. Não requer PostgreSQL ou Windows.

`tests/vr-fixture.sql` cria dados de exemplo somente em PostgreSQL descartável. O teste real de coleta usou PostgreSQL 14 e uma conta limitada a SELECT; uma tentativa de DELETE foi recusada. `tests/bridge_live.py` requer psycopg e `VR_TEST_FIXTURE` apontando a um JSON privado fora do checkout com credenciais e source/token exclusivamente de teste. Ele usa localhost:55432 para PostgreSQL e localhost:8788 para API PHP/MySQL; em produção, a configuração da ponte aceita apenas HTTPS. O teste usa uma origem/loja autorizada na cópia privada de config.php e a migração vr-schema.sql no MySQL descartável. `VR_TEST_SEQUENCE` permite testar uma próxima sequência sem apagar o espelho. O fixture SQL deve ser carregado por um administrador somente no banco descartável; a conta de leitura recebe sua senha dinamicamente fora do repositório.

`tests/vr_admin.cjs` usa Playwright e uma instalação vazia de teste em localhost:8788, inicializada pelo próprio teste. `VR_TEST_FIXTURE` contém install_token/admin_password gerados para esse banco descartável. Valida importação inativa, produtos incompatíveis bloqueados, cupom cancelado, ativação no catálogo, ausência de liberação automática e proibição de acesso do atendente ao espelho. Não rode em instalação existente: o teste pressupõe banco vazio e grava pedidos/cadastros de exemplo. DPAPI e scripts BAT requerem validação manual em Windows real, não disponível no executor Linux.

`python3 -m unittest discover -s tests -p 'test_bridge.py'` também testa a seleção automática por HTTPS: substituição da lista local, seleção vazia, mudança entre ciclos, falha sem fallback, origem/loja inválidas, limites e reenvio da pendência sem consultar nova seleção. `tests/bridge_selection_live.py` usa os mesmos fixtures descartáveis de `bridge_live.py` e `VR_TEST_FIXTURE`: verifica o endpoint PHP/MySQL com token e origem/loja válidos, recusa token/origem/loja incorretos e consulta os IDs selecionados (inclusive lista vazia) em PostgreSQL14. Não executa gravações no VR.

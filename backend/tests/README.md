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

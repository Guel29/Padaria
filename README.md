# Forno & Afeto — Comandas

Primeira etapa visual do sistema de atendimento da padaria. React, TypeScript, Tailwind CSS e Vite. Interface em português, adaptada para tablet horizontal e celular.

## Executar

Requer Node.js 22.18+ (validado com Node 24) e npm.

```sh
npm ci --cache /workspace/.npm-cache
npm run dev
```

O servidor usa a porta 5173. Para compilar: `npm run build`. Para validar as regras de demonstração: `npm test`.

Na tela inicial, escolha **Entrar na demonstração**. Os campos de login mostram o formato solicitado, mas **não autenticam usuários**. Não use credenciais reais.

## Funcionalidades desta etapa

- 25 comandas, filtros de estado e seis atendimentos fictícios iniciais.
- Seleção de novo pedido/pedido feito, catálogo por categoria e busca.
- Quantidade mínima 1, proteção de toque repetido, resumo e lançamentos separados por horário.
- Edição com confirmação, remoção com quantidade zero, totais em centavos e histórico de alterações.
- Encerramento simulado que arquiva o atendimento e libera o número para uma nova utilização.
- Persistência de demonstração no navegador e tratamento de erro de armazenamento.

Os dados persistem em `localStorage` (`forno-afeto-demo-v1`). A sessão de demonstração fica em `sessionStorage`. Fotografias ilustrativas são carregadas do Unsplash, com alternativa visual em caso de falha. Não há receita ou pagamento real.

## Limites e próximas etapas

Esta entrega atende à etapa visual com interações de demonstração. O armazenamento é isolado por navegador: **não atende ainda a operação compartilhada entre tablets**. A proteção de duplicidade é local, não uma garantia de idempotência no servidor. Sem backend, autenticação segura, retaguarda administrativa ou sincronização nesta etapa. A instalação PWA está disponível no build de produção. Não colocar em produção.

Após a aprovação visual, implementar backend compartilhado, transações, chaves de idempotência, versionamento, sessões seguras, senhas com hash, limitação de tentativas, autorização e HTTPS. Em seguida, retaguarda e evolução da operação offline, sem simular sucesso em gravações remotas.

`src/domain.ts` concentra os tipos e as operações de demonstração, separados da interface. A futura persistência deve mover as mutações para o servidor e expor um serviço de pedidos; a integração comercial ficará em adaptador próprio, sem acoplar telas a tabelas. Não há integração PostgreSQL nem suposição sobre o esquema do sistema comercial.

## Validação visual

1. Entrar na demonstração; verificar 25 comandas e filtros.
2. Abrir uma livre → Novo pedido → adicionar produtos e consultar resumo.
3. Adicionar o mesmo produto novamente e conferir linhas/horários separados.
4. Recarregar e conferir persistência; editar e remover um lançamento.
5. Simular pagamento, reabrir a mesma comanda e conferir atendimento sem itens anteriores.
6. No próximo atendimento, consultar o histórico para ver o atendimento encerrado.

## Pré-visualização em tempo real (HMR)

Execute `npm run dev`. O Vite usa a porta fixa **5173**, escuta em todas as interfaces e atualiza os componentes React e CSS automaticamente. Não precisa compilar ou publicar novamente. Mudanças em configuração podem reiniciar o servidor. `npm run preview` usa **4173** e serve o build estático; não oferece HMR.

Para acesso externo/celular, é necessário um encaminhamento HTTPS para a porta 5173, com suporte a WebSocket. Este ambiente não fornece automaticamente uma URL pública. Use uma origem exclusiva de desenvolvimento, diferente da versão publicada. Depois de obter a URL, copie `.env.example` para `.env.local`, defina `DEV_PREVIEW_URL` com a URL e reinicie `npm run dev`. Abra essa URL no navegador do celular. O hostname será autorizado de forma explícita; não desative a validação de hosts. O proxy deve encaminhar HTTP e WebSocket para a mesma porta 5173. Não sirva o desenvolvimento em subdiretório.

No computador que estiver executando o projeto em uma rede local, o celular na mesma rede pode usar o IP desse computador e a porta 5173. O IP privado da máquina cloud não é um endereço acessível pela sua rede doméstica.

### Cache e separação da publicação

O desenvolvimento responde com `Cache-Control: no-store`. O título recebe `[DEV]`. Só no modo de desenvolvimento, registros antigos de Service Worker da origem de prévia são removidos e caches com prefixo `padaria-` são limpos; dados de pedidos em localStorage não são apagados. Por isso é obrigatório usar uma origem separada da produção. Nenhum Service Worker é registrado pelo aplicativo atual. O módulo de limpeza é eliminado do build de produção.

A versão publicada continua sendo gerada com `npm run build` em `dist/`, independente do servidor de desenvolvimento. Para a futura hospedagem/PWA: servir HTML, manifest e script do Service Worker com revalidação (`Cache-Control: no-cache`); assets com hash em `/assets/` podem usar `public, max-age=31536000, immutable`. As regras devem ser aplicadas no servidor de hospedagem. O PWA está implementado para o build de produção; no GitHub Pages, os cabeçalhos HTTP são controlados pelo GitHub. O Service Worker verifica novas versões a cada minuto enquanto a página está aberta e ao voltar ao aplicativo. Nunca habilitar cache offline/HMR no mesmo ambiente.

## Publicação automática no GitHub Pages

Endereço padrão deste repositório: **https://guel29.github.io/Padaria/** (disponível após um deploy bem-sucedido, sem domínio personalizado).

1. Envie todos os arquivos do projeto para a branch `main` de `Guel29/Padaria`, inclusive `.github/workflows/pages.yml` e `package-lock.json`.
2. No GitHub, abra o repositório → **Settings** → **Pages**.
3. Em **Build and deployment** → **Source**, selecione **GitHub Actions**.
4. Na aba **Actions**, abra **Publicar no GitHub Pages**. Se não houver execução, escolha **Run workflow** na branch `main`. Se Actions estiver desativado, habilite-o nas configurações do repositório.
5. Aguarde os jobs `build` e `deploy` ficarem verdes. Abra a URL exibida no deployment `github-pages` ou em Settings → Pages.
6. Se o ambiente `github-pages` exigir aprovação, aprove o deployment ou ajuste suas regras em Settings → Environments. Repositórios privados precisam de um plano que permita Pages; caso contrário, use um repositório público com apenas dados fictícios.

Cada novo `push` na `main` executa instalação pelo lockfile, testes, compilação e publicação. Alterações apenas no ambiente do ChatGPT não chegam ao site até serem enviadas ao GitHub. GitHub Pages não oferece HMR: o deploy é automático, mas leva o tempo de execução do workflow.

### Instalar e atualizar

Abra a URL no computador ou celular. No Android/Chrome, use **Instalar aplicativo** / **Adicionar à tela inicial**. No iPhone/iPad/Safari, use **Compartilhar → Adicionar à Tela de Início**. Após a primeira visita online, a interface poderá abrir offline; as fotografias externas não são armazenadas pelo PWA.

O build usa `/Padaria/` para scripts, manifesto, ícones, início e escopo do Service Worker. O Vite mantém `/` no desenvolvimento. Não há rotas de URL que dependam de fallback do servidor. Se mudar o nome do repositório ou usar domínio próprio, ajuste `base`, `id`, `start_url`, `scope` e `navigateFallback` em `vite.config.ts`.

O Service Worker pré-armazena os assets de cada build, limpa caches antigos e verifica novas versões periodicamente. Um aviso **Atualizar agora** permite trocar de versão sem interromper o atendimento automaticamente. Os pedidos locais não são apagados na atualização. **Depois** adia a troca até uma próxima verificação/abertura. A primeira publicação não requer limpar o navegador. Não é possível substituir os cabeçalhos HTTP do GitHub Pages com um arquivo `_headers`.

Este é um **ambiente de demonstração público**, sem login seguro ou backend: use apenas dados fictícios. Cada dispositivo mantém dados próprios. Não registre dados reais de clientes. A publicação não cria integração comercial, sincronização entre tablets ou pagamento real.

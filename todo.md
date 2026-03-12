# Lista de Tarefas - Aplicação Gestão de Terceiros

## Fase 1: Planejamento e Estrutura

- [X] 001: Ler arquivo de instruções (Aplicação.txt)
- [X] 002: Analisar requisitos detalhados e confirmar escopo
- [X] 003: Criar estrutura de diretórios e arquivos iniciais
  - [X] Criar diretórios: `/css`, `/js`, `/uploads`, `/includes`, `/admin`, `/db`
  - [X] Criar arquivos base: `index.php`, `login.php`, `dashboard.php`, `terceiros.php`, `monitoramento.php`, `admin/usuarios.php`, `db/connection.php`, `includes/header.php`, `includes/footer.php`, `css/style.css`, `js/script.js`

## Fase 2: Banco de Dados

- [X] 004: Configurar Banco de Dados
  - [X] Definir esquema do banco de dados (tabelas: usuarios, empresas, filiais, terceiros, terceiros_documentos)
  - [X] Criar script SQL (`database.sql`) para criação das tabelas
  - [X] Implementar `db/connection.php` (usando PDO)
  - [X] Criar script SQL para popular minimamente o banco (usuário admin, exemplo de empresa/filial)

## Fase 3: Backend (PHP)

- [X] 005: Desenvolver Backend PHP
  - [X] Implementar sistema de autenticação (login, logout, verificação de sessão)
  - [X] Implementar criptografia de senha (password_hash)
  - [X] Criar funções/lógica para CRUD de Empresas
  - [X] Criar funções/lógica para CRUD de Filiais
  - [X] Criar funções/lógica para CRUD de Terceiros (incluindo upload de foto)
  - [X] Criar funções/lógica para gestão de documentos/NRs dos terceiros
  - [X] Implementar cálculo de validade e status (LIBERADO/BLOQUEADO)
  - [X] Implementar lógica de permissões (Admin vs. Usuário Comum)
  - [X] Criar funções/lógica para CRUD de Usuários (Admin)
  - [X] Implementar busca e filtros na tela de monitoramento
  - [X] Implementar agregação de dados para o Dashboard
  - [X] Implementar validações no lado do servidor (PHP)
  - [X] Garantir prevenção contra SQL Injection (Prepared Statements com PDO)

## Fase 4: Frontend (HTML/CSS/JS/Bootstrap)

- [X] 006: Desenvolver Frontend Responsivo
  - [X] Estruturar HTML para `login.php`
  - [X] Estruturar HTML e integrar dados do backend para `dashboard.php` (incluir gráficos simples se possível)
  - [X] Estruturar HTML e integrar dados do backend para `terceiros.php` (formulário de cadastro/edição)
  - [X] Estruturar HTML e integrar dados do backend para `monitoramento.php` (lista e filtros)
  - [X] Estruturar HTML e integrar dados do backend para `admin/usuarios.php`
  - [X] Estruturar HTML para cadastro de Empresas e Filiais
  - [X] Aplicar Bootstrap para layout responsivo e estilização
  - [X] Implementar validações no lado do cliente (JavaScript)
  - [X] Garantir responsividade (desktop, tablet, mobile)

## Fase 5: Testes e Finalização

- [X] 007: Testar Aplicação
  - [X] Testar funcionalidade de login/logout/sessão
  - [X] Testar CRUDs (Empresas, Filiais, Terceiros, Usuários)
  - [X] Testar upload de fotos
  - [X] Testar cálculo de status e validade de documentos
  - [X] Testar filtros e busca
  - [X] Testar dashboard
  - [X] Testar permissões de usuário
  - [X] Testar validações (frontend e backend)
  - [X] Testar responsividade em diferentes dispositivos/tamanhos de tela
  - [X] Verificar segurança (SQL Injection)
  - [X] Revisar código quanto à organização, comentários e limite de linhas
- [X] 008: Preparar Documentação e Entregar
  - [X] Finalizar script `database.sql` (criação e população)
  - [X] Organizar todos os arquivos do projeto
  - [X] Escrever um breve README.md (instruções de instalação/configuração)
  - [X] Compactar o projeto em um arquivo .zip
  - [X] Enviar mensagem ao usuário com o arquivo .zip e instruções

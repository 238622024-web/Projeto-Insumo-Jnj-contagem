# Organização CSS e JavaScript - Guia

## 📁 Estrutura de Arquivos

O projeto foi organizado para separar CSS e JavaScript em arquivos externos, melhorando a manutenção e carregamento de páginas.

### Diretórios Criados

```
assets/
├── css/
│   ├── login.css                    # Estilos da página de login
│   ├── header-footer.css            # Estilos do header e footer
│   ├── forgot-password.css          # Estilos da página de recuperação de senha
│   └── (demais estilos de páginas)
└── js/
    ├── login.js                     # Scripts da página de login
    ├── header-footer.js             # Scripts do header e footer (DataTables)
    ├── profile.js                   # Scripts da página de perfil
    ├── dashboard.js                 # Scripts do dashboard
    ├── create-account.js            # Scripts de criação de conta
    └── delete-confirmation.js       # Scripts de confirmação de exclusão
```

## 📋 Arquivos CSS Externos

### 1. `assets/css/login.css`
- Estilos para a página de login
- Inclui fallbacks para quando o Bootstrap CDN não carrega
- Botões, formulários, inputs e campos de senha

**Páginas que usam:**
- `login.php`

### 2. `assets/css/header-footer.css`
- Estilos do header e footer da aplicação
- Estilos da navbar, buttons, badges e alertas
- Compatibilidade com fallbacks

**Páginas que usam:**
- `includes/header.php`
- `includes/footer.php`
- Todas as páginas que incluem header/footer

### 3. `assets/css/forgot-password.css`
- Estilos da página de recuperação de senha
- Utiliza Tailwind CSS

**Páginas que usam:**
- `forgot-password.php`

## 🎯 Arquivos JavaScript Externos

### 1. `assets/js/login.js`
**Funcionalidades:**
- Toggle de visibilidade da senha (mostrar/ocultar)
- Validação de campos preenchidos para flutuação de labels
- IIFE para evitar conflitos de escopo

**Páginas que usam:**
- `login.php`

### 2. `assets/js/header-footer.js`
**Funcionalidades:**
- Inicialização do DataTables para todas as tabelas
- Auto-fechamento de alertas após 5 segundos
- Ajuste automático de colspan em tabelas
- Configuração de idioma português (BR) do DataTables

**Páginas que usam:**
- `includes/footer.php`
- Todas as páginas com tabelas

### 3. `assets/js/profile.js`
**Funcionalidades:**
- Toggle de visibilidade da nova senha no perfil do usuário
- Mesma lógica do login mas para campos de perfil

**Páginas que usam:**
- `perfil.php`

### 4. `assets/js/dashboard.js`
**Funcionalidades:**
- Renderização do dashboard
- Recuperação de dados do localStorage
- Função de escape HTML para evitar XSS
- Logout do usuário

**Páginas que usam:**
- `dashboard.html`

### 5. `assets/js/create-account.js`
**Funcionalidades:**
- Toggle de visibilidade de dois campos de senha:
  - Campo "Senha"
  - Campo "Confirmar Senha"
- Valida entrada do usuário em tempo real

**Páginas que usam:**
- `create-account.php`

### 6. `assets/js/delete-confirmation.js`
**Funcionalidades:**
- Validação de texto de confirmação na exclusão de itens
- Desabilita botão "Excluir" até digitar "EXCLUIR"
- Validação case-insensitive

**Páginas que usam:**
- `excluir.php`

### 7. `assets/js/settings.js`
**Funcionalidades:**
- Gerencia o logout do usuário
- Redireciona para página de login

**Páginas que usam:**
- `configuracoes.html`

### 8. `assets/js/cadastro.js`
**Funcionalidades:**
- Calcula automaticamente data de validade (+2 anos)
- Atualiza campo de validade quando a data de entrada é alterada
- Validação de datas

**Páginas que usam:**
- `cadastrar.php`

## 🔄 Migrações Realizadas

### Antes (Inline)
```html
<style>
  /* Muitos estilos misturados no HTML */
</style>
<script>
  // Scripts inline no final da página
</script>
```

### Depois (Externo)
```html
<link rel="stylesheet" href="assets/css/login.css" />
<script src="assets/js/login.js"></script>
```

## ✅ Benefícios

1. **Melhor Manutenção**: CSS e JS organizados em arquivos específicos
2. **Cache do Navegador**: Arquivos podem ser cacheados pelo navegador
3. **Reutilização**: Estilos e scripts podem ser compartilhados entre páginas
4. **Legibilidade**: Código mais limpo e fácil de ler
5. **Performance**: Separação de responsabilidades
6. **SEO**: HTML mais limpo e semântico

## 🚀 Como Usar

Não há mudanças na forma como usar o projeto. Todas as funcionalidades continuam exatamente como antes, mas agora com melhor organização.

### Exemplo de Inclusão em Nova Página
```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="assets/css/seu-arquivo.css" />
</head>
<body>
    <!-- conteúdo -->
    <script src="assets/js/seu-arquivo.js"></script>
</body>
</html>
```

## 📝 Notas Importantes

1. **Fallbacks CSS**: Os fallbacks inline ainda existem em alguns arquivos CSS para garantir que o layout funcione mesmo se os CDNs (Bootstrap, FontAwesome) não carregarem.

2. **IIFE (Immediately Invoked Function Expression)**: Todos os scripts usam IIFE para evitar conflitos de variáveis globais.

3. **Compatibilidade**: Os scripts funcionam em todos os navegadores modernos (Chrome, Firefox, Safari, Edge).

4. **DataTables**: A inicialização do DataTables é feita automaticamente em todas as tabelas com a classe `table`.

## 🔧 Manutenção Futura

Para adicionar novos estilos ou scripts:

1. **Crie um novo arquivo** em `assets/css/` ou `assets/js/`
2. **Documente as funcionalidades** com comentários
3. **Use nomes descritivos** para o arquivo (ex: `notifications.js`)
4. **Inclua o arquivo** na página correspondente

---

**Organização concluída em:** 16 de janeiro de 2026

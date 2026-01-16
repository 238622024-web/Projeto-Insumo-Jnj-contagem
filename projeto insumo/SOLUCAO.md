# 🐳 SOLUÇÃO: Docker não consegue baixar imagens

## ❌ Problema Atual
O Docker Hub CDN está com problemas de conectividade:
```
Error: failed to copy: httpReadSeeker: failed open - EOF
```
**Isso afeta TODAS as imagens (PHP, MySQL, etc)**

---

## ✅ SOLUÇÃO 1: Usar XAMPP (Recomendado)

### Passo 1: Baixar e Instalar XAMPP
1. Acesse: https://www.apachefriends.org/pt_BR/index.html
2. Baixe a versão com **PHP 8.1 ou superior**
3. Instale na pasta padrão: `C:\xampp\`

### Passo 2: Copiar projeto para XAMPP
```powershell
# Copie toda a pasta para htdocs
Copy-Item -Path "C:\Users\Vitor\ProjetoInsumos\Projeto-Insumo-Jnj-contagem\projeto insumo" `
          -Destination "C:\xampp\htdocs\projeto-insumo" -Recurse -Force
```

### Passo 3: Iniciar XAMPP
1. Abra `C:\xampp\xampp-control.exe`
2. Clique em **Start** para Apache
3. Clique em **Start** para MySQL
4. Acesse: `http://localhost/projeto-insumo/`

### Passo 4: Criar banco de dados
1. Abra: `http://localhost/phpmyadmin`
2. Login: `root` / (sem senha)
3. Crie a base de dados: `controle_insumos_jnj`
4. Importe o arquivo: `database/schema.sql`

---

## ✅ SOLUÇÃO 2: Usar servidor PHP local (sem MySQL)
Se tiver PHP instalado localmente:

```powershell
cd "C:\Users\Vitor\ProjetoInsumos\Projeto-Insumo-Jnj-contagem\projeto insumo"
php -S localhost:8080
```

Acesse: `http://localhost:8080`

---

## ✅ SOLUÇÃO 3: Tentar Docker novamente depois
Se quiser resolver o Docker:

```powershell
# Reinicie o computador
Restart-Computer

# Ou tente novamente em alguns minutos:
cd "C:\Users\Vitor\ProjetoInsumos\Projeto-Insumo-Jnj-contagem\projeto insumo"
docker compose up -d
```

---

## 📋 Checklist de Configuração

### XAMPP Setup:
- [ ] XAMPP instalado em `C:\xampp\`
- [ ] Projeto copiado para `C:\xampp\htdocs\projeto-insumo\`
- [ ] Apache rodando
- [ ] MySQL rodando
- [ ] phpMyAdmin acessível
- [ ] Base de dados criada
- [ ] Schema.sql importado

### Banco de Dados:
- [ ] Host: `localhost`
- [ ] Usuário: `root`
- [ ] Senha: (em branco)
- [ ] Database: `controle_insumos_jnj`
- [ ] Porta: `3306`

---

## 🔧 Configurar conexão no PHP

Edite o arquivo `config.php`:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'controle_insumos_jnj');
define('DB_PORT', 3306);
?>
```

---

## 🆘 Problemas Comuns

### "PHP não encontrado"
- Instale XAMPP ou configure PHP no PATH do Windows

### "Conexão recusada na porta 3306"
- Inicie o MySQL no painel de controle do XAMPP

### "Base de dados não existe"
- Crie manualmente via phpMyAdmin
- Importe `database/schema.sql`

---

## 📞 Próximos Passos

1. **Instale XAMPP**
2. **Copie os arquivos**
3. **Inicie Apache + MySQL**
4. **Acesse o site**

Pronto! 🚀

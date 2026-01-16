# ⚠️ DOCKER NÃO CONSEGUE DOWNLOAD

## Problema
```
Error: failed to copy - EOF
URL: docker-images-prod.6aa30f8b08e16409b46e0173d6de2f56.r2.cloudflarestorage.com
```

**Afeta:** TODAS as imagens (MySQL, PHP, etc)

---

## 🔧 SOLUÇÃO RÁPIDA - INSTALAR XAMPP

### Download
```
https://www.apachefriends.org/pt_BR/index.html
```

### Passos:
1. **Baixe** a versão com PHP 8.x
2. **Instale** em: `C:\xampp\`
3. **Copie** este projeto para: `C:\xampp\htdocs\projeto-insumo\`
4. **Abra**: `C:\xampp\xampp-control.exe`
5. **Clique** em "Start" (Apache + MySQL)
6. **Acesse**: `http://localhost/projeto-insumo/`

### Criar Base de Dados
```
1. Abra: http://localhost/phpmyadmin
2. Crie banco: controle_insumos_jnj
3. Importe: database/schema.sql
```

---

## ✅ Status
- ✓ Docker Desktop instalado
- ✓ Docker funcionando
- ✗ Docker Hub CDN inacessível (problema de rede)
- ✓ XAMPP é alternativa rápida

---

## 💡 PRÓXIMO PASSO
**Instale XAMPP agora!** É a solução mais rápida.

Depois disso:
- Abra `C:\xampp\xampp-control.exe`
- Clique "Start" nos botões Apache e MySQL
- Acesse o site

🚀 Pronto!

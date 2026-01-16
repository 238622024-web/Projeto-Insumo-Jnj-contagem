========================================
  SOLUÇÃO PARA: Run Services não funciona
========================================

PROBLEMA:
- Docker não consegue baixar imagens
- Erro: EOF/TLS timeout
- PHP/Node/Python não instalados localmente

SOLUÇÃO: INSTALAR XAMPP (5 MINUTOS)

PASSO 1: Download
- Acesse: https://www.apachefriends.org/pt_BR/index.html
- Baixe "XAMPP" com PHP 8.1+

PASSO 2: Instalar
- Execute o instalador
- Instale em: C:\xampp\
- Marque: Apache, MySQL, PHP

PASSO 3: Copiar Projeto
- Copie esta pasta para: C:\xampp\htdocs\projeto-insumo\

PASSO 4: Iniciar
- Abra: C:\xampp\xampp-control.exe
- Clique "Start" em Apache
- Clique "Start" em MySQL
- Aguarde ficar verde

PASSO 5: Acessar
- Navegador: http://localhost/projeto-insumo/
- phpMyAdmin: http://localhost/phpmyadmin

PASSO 6: Banco de Dados
- Entre em phpMyAdmin
- Create Database: controle_insumos_jnj
- Import: projeto insumo > database > schema.sql

PRONTO! 🚀

========================================
Dúvidas? Veja SOLUCAO.md
========================================

# Script para tentar novamente com Docker Compose
# Se não funcionar, use XAMPP (veja SOLUCAO.md)

$projectPath = "c:\Users\Vitor\ProjetoInsumos\Projeto-Insumo-Jnj-contagem\projeto insumo"

Write-Host "========================================" -ForegroundColor Green
Write-Host "Docker Compose - Sistema de Insumos JNJ" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""

# Verificar se Docker está rodando
Write-Host "Verificando Docker..." -ForegroundColor Yellow
docker --version

if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Docker não está instalado!" -ForegroundColor Red
    Write-Host "Instale Docker Desktop: https://www.docker.com/products/docker-desktop" -ForegroundColor Yellow
    exit 1
}

Write-Host "✓ Docker encontrado" -ForegroundColor Green
Write-Host ""

# Ir para o diretório do projeto
Set-Location $projectPath

# Parar containers antigos
Write-Host "Parando containers antigos..." -ForegroundColor Yellow
docker compose down -v 2>&1 | Out-Null

# Iniciar containers
Write-Host "Iniciando containers..." -ForegroundColor Yellow
docker compose up -d

# Verificar status
Write-Host ""
Write-Host "Status dos containers:" -ForegroundColor Green
docker compose ps

Write-Host ""
Write-Host "✓ Sistema iniciado!" -ForegroundColor Green
Write-Host ""
Write-Host "Acessar:" -ForegroundColor Green
Write-Host "  - Aplicação: http://localhost:8080" -ForegroundColor Cyan
Write-Host "  - phpMyAdmin: http://localhost:8081" -ForegroundColor Cyan
Write-Host ""
Write-Host "Credenciais MySQL:" -ForegroundColor Green
Write-Host "  - Usuário: root" -ForegroundColor Cyan
Write-Host "  - Senha: rootpass" -ForegroundColor Cyan
Write-Host "  - Database: controle_insumos_jnj" -ForegroundColor Cyan
Write-Host ""

# Se falhar, sugerir XAMPP
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Erro ao iniciar containers!" -ForegroundColor Red
    Write-Host ""
    Write-Host "SOLUÇÃO: Use XAMPP em vez de Docker" -ForegroundColor Yellow
    Write-Host "Veja o arquivo SOLUCAO.md para instruções" -ForegroundColor Yellow
}

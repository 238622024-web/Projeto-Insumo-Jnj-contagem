<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
requireLogin();

if (!class_exists('ZipArchive')) {
    $batInstaller = __DIR__ . '/INSTALAR-ATALHO-APP.bat';
    if (!is_file($batInstaller)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "ZipArchive nao disponivel no PHP e o instalador .bat nao foi encontrado.";
        exit;
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="INSTALAR-ATALHO-APP.bat"');
    header('Content-Length: ' . (string)filesize($batInstaller));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    readfile($batInstaller);
    exit;
}

$filesToPack = [
    'INSTALAR-ATALHO-APP.bat',
    'APP-LAUNCHER.bat',
    'XAMPP-START.bat',
    'README-SETUP.txt',
];

$tmpZipPath = tempnam(sys_get_temp_dir(), 'insumo_instalador_');
if ($tmpZipPath === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Falha ao criar arquivo temporario para o zip.';
    exit;
}

$zipPath = $tmpZipPath . '.zip';
@rename($tmpZipPath, $zipPath);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($tmpZipPath);
    @unlink($zipPath);
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Nao foi possivel criar o pacote zip.';
    exit;
}

foreach ($filesToPack as $relativeFile) {
    $absoluteFile = __DIR__ . '/' . $relativeFile;
    if (is_file($absoluteFile)) {
        $zip->addFile($absoluteFile, $relativeFile);
    }
}

$zip->addFromString('LEIA-ME-PRIMEIRO.txt', "PASSOS RAPIDOS:\r\n1) Extraia o ZIP.\r\n2) Execute INSTALAR-ATALHO-APP.bat.\r\n3) Use o atalho 'Projeto Insumo JNJ' criado na Area de Trabalho.\r\n");
$zip->close();

if (!is_file($zipPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Falha ao gerar o pacote zip.';
    exit;
}

$downloadName = 'Projeto-Insumo-Instalador-PC.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . (string)filesize($zipPath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

readfile($zipPath);
@unlink($zipPath);
@unlink($tmpZipPath);
exit;

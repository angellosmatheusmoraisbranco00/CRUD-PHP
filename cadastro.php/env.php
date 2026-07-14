<?php
$caminho = __DIR__ . '/config.php';

if (!file_exists($caminho)){
    die("Arquivo config.php não encontrado. Copie config.example.php para config.php e preencha suas credenciais.");

}

require $caminho;



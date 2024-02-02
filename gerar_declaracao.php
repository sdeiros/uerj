<?php
require 'index.php'; // Substitua por seu nome de arquivo principal

if (isset($_GET['id'])) {
    $idEstagiario = $_GET['id'];
    $estagiario = $conn->query("SELECT * FROM estagiarios WHERE id = $idEstagiario")->fetch_assoc();

    if ($estagiario) {
        gerarDeclaracao([$estagiario]);
    } else {
        echo 'Estagiário não encontrado.';
    }
} else {
    echo 'ID do estagiário não fornecido.';
}
?>

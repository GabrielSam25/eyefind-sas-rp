<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conteudo'])) {

    $conteudo = trim($_POST['conteudo']);

    if (empty($conteudo)) {
        $_SESSION['erro'] = "O post não pode estar vazio!";
        header('Location: lifeinvader.php');
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO posts (usuario_id, conteudo) VALUES (?, ?)");
        $stmt->execute([$_SESSION['usuario_id'], $conteudo]);

        $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_post = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);

        $_SESSION['sucesso'] = "Post publicado com sucesso!";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao publicar o post: " . $e->getMessage();
    }

    header('Location index.php');
    exit();
} else {

    header('Location: index.php');
    exit();
}

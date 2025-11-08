<?php
require_once 'config.php';

if (!isset($_GET['post_id'])) {
    die(json_encode(['error' => 'Post ID não fornecido']));
}

$post_id = (int)$_GET['post_id'];
$usuario_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 0;

// Buscar comentários
$stmt = $pdo->prepare("
    SELECT c.*, u.nome as autor, u.foto_perfil 
    FROM comentarios c
    JOIN usuarios u ON c.usuario_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.data_comentario DESC
");
$stmt->execute([$post_id]);
$comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar contagem de curtidas
$stmt = $pdo->prepare("SELECT COUNT(*) as curtidas FROM curtidas WHERE post_id = ?");
$stmt->execute([$post_id]);
$curtidas = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar se o usuário atual curtiu o post
$curtido = false;
if ($usuario_id) {
    $stmt = $pdo->prepare("SELECT 1 FROM curtidas WHERE post_id = ? AND usuario_id = ?");
    $stmt->execute([$post_id, $usuario_id]);
    $curtido = (bool)$stmt->fetch();
}

header('Content-Type: application/json');
echo json_encode([
    'comentarios' => $comentarios,
    'curtidas' => $curtidas['curtidas'] ?? 0,
    'curtido' => $curtido
]);

<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isLogado()) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$website_id = $data['website_id'] ?? 0;
$block_type = $data['type'] ?? 'custom';

// Verificar se o website pertence ao usuário
$usuario = getUsuarioAtual($pdo);
$stmt = $pdo->prepare("SELECT id FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$website_id, $usuario['id']]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Website não encontrado']);
    exit;
}

// Criar bloco vazio
$stmt = $pdo->prepare("INSERT INTO dynamic_blocks (website_id, block_type, block_data) VALUES (?, ?, '{}')");
$stmt->execute([$website_id, $block_type]);
$block_id = $pdo->lastInsertId();

echo json_encode(['success' => true, 'block_id' => $block_id]);
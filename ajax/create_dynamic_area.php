<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isLogado()) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$website_id = intval($data['website_id'] ?? 0);
$element_id = $data['element_id'] ?? '';

if (!$website_id || !$element_id) {
    echo json_encode(['error' => 'Dados incompletos']);
    exit;
}

// Verificar se o website pertence ao usuário
$usuario = getUsuarioAtual($pdo);
$stmt = $pdo->prepare("SELECT id FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$website_id, $usuario['id']]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Website não encontrado']);
    exit;
}

// Verificar se já existe uma área com esse element_id
$stmt = $pdo->prepare("SELECT id FROM dynamic_areas WHERE website_id = ? AND element_id = ?");
$stmt->execute([$website_id, $element_id]);
if ($stmt->fetch()) {
    echo json_encode(['success' => true, 'element_id' => $element_id]); // já existe
    exit;
}

// Criar nova área com conteúdo vazio
$stmt = $pdo->prepare("INSERT INTO dynamic_areas (website_id, element_id, content_data) VALUES (?, ?, '[]')");
$stmt->execute([$website_id, $element_id]);

echo json_encode(['success' => true, 'element_id' => $element_id]);
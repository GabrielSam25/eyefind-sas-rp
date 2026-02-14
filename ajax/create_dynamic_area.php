<?php
require_once '../config.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isLogado()) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Receber dados
$data = json_decode(file_get_contents('php://input'), true);
$website_id = intval($data['website_id'] ?? 0);
$element_id = $data['element_id'] ?? '';

if (!$website_id || !$element_id) {
    echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
    exit;
}

// Verificar se o website pertence ao usuário
$usuario = getUsuarioAtual($pdo);
$stmt = $pdo->prepare("SELECT id FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$website_id, $usuario['id']]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Website não encontrado']);
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
if ($stmt->execute([$website_id, $element_id])) {
    echo json_encode(['success' => true, 'element_id' => $element_id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Erro ao criar área no banco']);
}
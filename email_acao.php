<?php
require_once 'email_config.php';

if (!isLogado()) {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

$usuario = getUsuarioAtual($pdo);
$acao = $_GET['acao'] ?? '';
$id = intval($_GET['id'] ?? 0);

header('Content-Type: application/json');

if (!$id) {
    echo json_encode(['erro' => 'ID inválido']);
    exit;
}

switch ($acao) {
    case 'estrela':
        // Verificar se já tem estrela
        $stmt = $pdo->prepare("SELECT id FROM email_estrelas WHERE email_id = ? AND usuario_id = ?");
        $stmt->execute([$id, $usuario['id']]);
        $temEstrela = $stmt->fetch();
        
        if ($temEstrela) {
            // Remover estrela
            $stmt = $pdo->prepare("DELETE FROM email_estrelas WHERE email_id = ? AND usuario_id = ?");
            $stmt->execute([$id, $usuario['id']]);
            echo json_encode(['estrela' => false, 'mensagem' => 'Estrela removida']);
        } else {
            // Adicionar estrela
            $stmt = $pdo->prepare("INSERT INTO email_estrelas (email_id, usuario_id) VALUES (?, ?)");
            $stmt->execute([$id, $usuario['id']]);
            echo json_encode(['estrela' => true, 'mensagem' => 'Estrela adicionada']);
        }
        break;
        
    case 'lixeira':
        // Mover para lixeira
        $stmt = $pdo->prepare("INSERT IGNORE INTO email_lixeira (email_id, usuario_id) VALUES (?, ?)");
        $stmt->execute([$id, $usuario['id']]);
        echo json_encode(['sucesso' => true, 'mensagem' => 'Movido para lixeira']);
        break;
        
    case 'restaurar':
        // Restaurar da lixeira
        $stmt = $pdo->prepare("DELETE FROM email_lixeira WHERE email_id = ? AND usuario_id = ?");
        $stmt->execute([$id, $usuario['id']]);
        echo json_encode(['sucesso' => true, 'mensagem' => 'Restaurado da lixeira']);
        break;
        
    case 'excluir':
        // Excluir permanentemente
        $stmt = $pdo->prepare("
            DELETE FROM emails 
            WHERE id = ? AND (destinatario_id = ? OR remetente_id = ?)
        ");
        $stmt->execute([$id, $usuario['id'], $usuario['id']]);
        echo json_encode(['sucesso' => true, 'mensagem' => 'Excluído permanentemente']);
        break;
        
    default:
        echo json_encode(['erro' => 'Ação inválida']);
}
?>
<?php
require_once 'config.php';

// Funções específicas para o Eyefind.mail

function getEmailsRecebidos($pdo, $usuario_id, $limite = 50) {
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            u.nome as remetente_nome,
            u.email as remetente_email,
            CASE WHEN es.id IS NOT NULL THEN 1 ELSE 0 END as tem_estrela,
            CASE WHEN el.id IS NOT NULL THEN 1 ELSE 0 END as na_lixeira
        FROM emails e
        JOIN usuarios u ON e.remetente_id = u.id
        LEFT JOIN email_estrelas es ON e.id = es.email_id AND es.usuario_id = ?
        LEFT JOIN email_lixeira el ON e.id = el.email_id AND el.usuario_id = ?
        WHERE e.destinatario_id = ? 
        AND el.id IS NULL
        ORDER BY e.data_envio DESC
        LIMIT ?
    ");
    $stmt->execute([$usuario_id, $usuario_id, $usuario_id, $limite]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEmailsEnviados($pdo, $usuario_id, $limite = 50) {
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            u.nome as destinatario_nome,
            u.email as destinatario_email
        FROM emails e
        JOIN usuarios u ON e.destinatario_id = u.id
        WHERE e.remetente_id = ?
        ORDER BY e.data_envio DESC
        LIMIT ?
    ");
    $stmt->execute([$usuario_id, $limite]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEmailsComEstrela($pdo, $usuario_id, $limite = 50) {
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            u.nome as remetente_nome,
            u.email as remetente_email
        FROM emails e
        JOIN usuarios u ON e.remetente_id = u.id
        JOIN email_estrelas es ON e.id = es.email_id
        WHERE es.usuario_id = ? AND e.destinatario_id = ?
        ORDER BY es.data_estrela DESC
        LIMIT ?
    ");
    $stmt->execute([$usuario_id, $usuario_id, $limite]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEmailsLixeira($pdo, $usuario_id, $limite = 50) {
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            u.nome as remetente_nome,
            u.email as remetente_email,
            el.data_exclusao
        FROM emails e
        JOIN usuarios u ON e.remetente_id = u.id
        JOIN email_lixeira el ON e.id = el.email_id
        WHERE el.usuario_id = ?
        ORDER BY el.data_exclusao DESC
        LIMIT ?
    ");
    $stmt->execute([$usuario_id, $limite]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function enviarEmail($pdo, $remetente_id, $destinatario_email, $assunto, $corpo, $anexos = []) {
    // Buscar ID do destinatário pelo email
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$destinatario_email]);
    $destinatario = $stmt->fetch();
    
    if (!$destinatario) {
        return ['erro' => 'Destinatário não encontrado'];
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO emails (remetente_id, destinatario_id, assunto, corpo) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$remetente_id, $destinatario['id'], $assunto, $corpo]);
    
    $email_id = $pdo->lastInsertId();
    
    // Processar anexos
    foreach ($anexos as $anexo) {
        $stmt = $pdo->prepare("
            INSERT INTO email_anexos (email_id, nome_arquivo, caminho_arquivo, tamanho, tipo_arquivo) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $email_id, 
            $anexo['nome'], 
            $anexo['caminho'], 
            $anexo['tamanho'], 
            $anexo['tipo']
        ]);
    }
    
    return ['sucesso' => true, 'email_id' => $email_id];
}

function marcarEstrela($pdo, $email_id, $usuario_id) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO email_estrelas (email_id, usuario_id) VALUES (?, ?)");
    return $stmt->execute([$email_id, $usuario_id]);
}

function removerEstrela($pdo, $email_id, $usuario_id) {
    $stmt = $pdo->prepare("DELETE FROM email_estrelas WHERE email_id = ? AND usuario_id = ?");
    return $stmt->execute([$email_id, $usuario_id]);
}

function moverParaLixeira($pdo, $email_id, $usuario_id) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO email_lixeira (email_id, usuario_id) VALUES (?, ?)");
    return $stmt->execute([$email_id, $usuario_id]);
}

function restaurarEmail($pdo, $email_id, $usuario_id) {
    $stmt = $pdo->prepare("DELETE FROM email_lixeira WHERE email_id = ? AND usuario_id = ?");
    return $stmt->execute([$email_id, $usuario_id]);
}

function excluirPermanente($pdo, $email_id, $usuario_id) {
    // Verificar se é destinatário ou remetente
    $stmt = $pdo->prepare("
        DELETE FROM emails 
        WHERE id = ? AND (destinatario_id = ? OR remetente_id = ?)
    ");
    return $stmt->execute([$email_id, $usuario_id, $usuario_id]);
}

function getTotalNaoLidos($pdo, $usuario_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM emails 
        WHERE destinatario_id = ? AND data_leitura IS NULL
    ");
    $stmt->execute([$usuario_id]);
    return $stmt->fetchColumn();
}

function marcarComoLido($pdo, $email_id, $usuario_id) {
    $stmt = $pdo->prepare("
        UPDATE emails SET data_leitura = NOW() 
        WHERE id = ? AND destinatario_id = ? AND data_leitura IS NULL
    ");
    return $stmt->execute([$email_id, $usuario_id]);
}

function criarPasta($pdo, $usuario_id, $nome, $cor = '#067191') {
    $stmt = $pdo->prepare("
        INSERT INTO email_pastas (usuario_id, nome, cor) 
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$usuario_id, $nome, $cor]);
}

function moverParaPasta($pdo, $email_id, $pasta_id, $usuario_id) {
    $stmt = $pdo->prepare("
        INSERT INTO email_em_pasta (email_id, pasta_id, usuario_id) 
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$email_id, $pasta_id, $usuario_id]);
}

function salvarRascunho($pdo, $usuario_id, $para, $assunto, $corpo, $email_original_id = null) {
    // Verificar se já existe rascunho
    if ($email_original_id) {
        $stmt = $pdo->prepare("
            UPDATE email_rascunhos 
            SET para = ?, assunto = ?, corpo = ? 
            WHERE usuario_id = ? AND email_original_id = ?
        ");
        return $stmt->execute([$para, $assunto, $corpo, $usuario_id, $email_original_id]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO email_rascunhos (usuario_id, para, assunto, corpo) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$usuario_id, $para, $assunto, $corpo]);
    }
}
<?php
require_once 'email_config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$usuario = getUsuarioAtual($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $para = $_POST['para'];
    $assunto = $_POST['assunto'];
    $corpo = $_POST['corpo'];
    $email_original_id = $_POST['email_original_id'] ?? 0;
    
    if (empty($para) || empty($assunto) || empty($corpo)) {
        $_SESSION['erro'] = 'Preencha todos os campos';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Adicionar citação do email original se não tiver
    if (strlen($corpo) < 100 && $email_original_id) {
        $stmt = $pdo->prepare("SELECT * FROM emails WHERE id = ?");
        $stmt->execute([$email_original_id]);
        $original = $stmt->fetch();
        
        if ($original) {
            $corpo .= "\n\n\n----- Mensagem original -----\n" . $original['corpo'];
        }
    }
    
    $resultado = enviarEmail($pdo, $usuario['id'], $para, $assunto, $corpo, []);
    
    if (isset($resultado['erro'])) {
        $_SESSION['erro'] = $resultado['erro'];
    } else {
        $_SESSION['sucesso'] = 'Resposta enviada com sucesso!';
    }
    
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
?>
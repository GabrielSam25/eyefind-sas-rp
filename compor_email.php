<?php
require_once 'email_config.php';

if (!isLogado()) {
    header('Location: login.php?redirect=compor_email.php');
    exit;
}

$usuario = getUsuarioAtual($pdo);
$erro = '';
$sucesso = '';

// Verificar se está respondendo a algum email
$responder_id = $_GET['responder'] ?? 0;
$assunto = '';
$para = '';
$corpo = '';

if ($responder_id) {
    $stmt = $pdo->prepare("
        SELECT e.*, u.email as remetente_email, u.nome as remetente_nome 
        FROM emails e 
        JOIN usuarios u ON e.remetente_id = u.id 
        WHERE e.id = ? AND e.destinatario_id = ?
    ");
    $stmt->execute([$responder_id, $usuario['id']]);
    $original = $stmt->fetch();
    
    if ($original) {
        $para = $original['remetente_email'];
        $assunto = 'Re: ' . $original['assunto'];
        $corpo = "\n\n\n----- Mensagem original -----\n" . $original['corpo'];
    }
}

// Processar envio
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $para = $_POST['para'];
    $assunto = $_POST['assunto'];
    $corpo = $_POST['corpo'];
    
    if (empty($para) || empty($assunto) || empty($corpo)) {
        $erro = 'Preencha todos os campos';
    } else {
        $resultado = enviarEmail($pdo, $usuario['id'], $para, $assunto, $corpo, []);
        
        if (isset($resultado['erro'])) {
            $erro = $resultado['erro'];
        } else {
            $sucesso = 'Email enviado com sucesso!';
            // Limpar campos
            $para = $assunto = $corpo = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Compor Email - Eyefind.mail</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.ckeditor.com/4.22.1/full-all/ckeditor.js"></script>
</head>
<body class="bg-eyefind-light">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-eyefind-blue">Compor Email</h1>
                <a href="email.php" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </a>
            </div>
            
            <?php if ($erro): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $erro; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($sucesso): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $sucesso; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-4">
                    <label class="block font-bold mb-2">Para:</label>
                    <input type="email" name="para" value="<?php echo htmlspecialchars($para); ?>" 
                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue"
                           placeholder="email@eyefind.mail" required>
                </div>
                
                <div class="mb-4">
                    <label class="block font-bold mb-2">Assunto:</label>
                    <input type="text" name="assunto" value="<?php echo htmlspecialchars($assunto); ?>" 
                           class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue"
                           required>
                </div>
                
                <div class="mb-4">
                    <label class="block font-bold mb-2">Mensagem:</label>
                    <textarea name="corpo" id="corpo" rows="10" class="w-full px-3 py-2 border rounded"><?php echo htmlspecialchars($corpo); ?></textarea>
                </div>
                
                <div class="flex justify-end gap-2">
                    <a href="email.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        Cancelar
                    </a>
                    <button type="submit" class="bg-eyefind-blue text-white px-6 py-2 rounded hover:bg-eyefind-dark">
                        Enviar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        CKEDITOR.replace('corpo');
    </script>
</body>
</html>
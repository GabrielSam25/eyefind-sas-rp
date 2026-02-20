<?php
require_once 'email_config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$usuario = getUsuarioAtual($pdo);
$email_id = intval($_GET['id'] ?? 0);

if (!$email_id) {
    header('Location: email.php');
    exit;
}

// Buscar email
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        r.nome as remetente_nome,
        r.email as remetente_email,
        d.nome as destinatario_nome,
        d.email as destinatario_email,
        CASE WHEN es.id IS NOT NULL THEN 1 ELSE 0 END as tem_estrela
    FROM emails e
    JOIN usuarios r ON e.remetente_id = r.id
    JOIN usuarios d ON e.destinatario_id = d.id
    LEFT JOIN email_estrelas es ON e.id = es.email_id AND es.usuario_id = d.id
    WHERE e.id = ? AND (e.destinatario_id = ? OR e.remetente_id = ?)
");
$stmt->execute([$email_id, $usuario['id'], $usuario['id']]);
$email = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$email) {
    header('Location: email.php');
    exit;
}

// Marcar como lido se for destinatário
if ($email['destinatario_id'] == $usuario['id'] && !$email['data_leitura']) {
    marcarComoLido($pdo, $email_id, $usuario['id']);
}

// Buscar anexos
$stmt = $pdo->prepare("SELECT * FROM email_anexos WHERE email_id = ?");
$stmt->execute([$email_id]);
$anexos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($email['assunto']); ?> - Eyefind.mail</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap');
        body { font-family: 'Roboto Condensed', sans-serif; background-color: #F8FAFC; }
        .email-content {
            line-height: 1.6;
        }
        .email-content p {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-eyefind-light">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="bg-white p-6 rounded-lg shadow-md">
            
            <!-- Cabeçalho com ações -->
            <div class="flex justify-between items-center mb-6 pb-4 border-b">
                <a href="email.php" class="text-gray-500 hover:text-gray-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
                <div class="flex gap-3">
                    <button onclick="toggleStar(<?php echo $email_id; ?>)" 
                            class="text-gray-400 hover:text-yellow-400 transition" 
                            id="starBtn">
                        <i class="fa<?php echo $email['tem_estrela'] ? 's' : 'r'; ?> fa-star <?php echo $email['tem_estrela'] ? 'text-yellow-400' : ''; ?> text-xl"></i>
                    </button>
                    
                    <a href="compor_email.php?responder=<?php echo $email_id; ?>" 
                       class="text-blue-600 hover:text-blue-800 transition">
                        <i class="fas fa-reply mr-1"></i> Responder
                    </a>
                    
                    <?php if ($email['destinatario_id'] == $usuario['id']): ?>
                        <button onclick="moverLixeira(<?php echo $email_id; ?>)" 
                                class="text-red-600 hover:text-red-800 transition">
                            <i class="fas fa-trash mr-1"></i> Excluir
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Assunto -->
            <h1 class="text-2xl font-bold mb-6 text-eyefind-dark"><?php echo htmlspecialchars($email['assunto']); ?></h1>
            
            <!-- Informações do remetente -->
            <div class="flex items-start gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                <div class="w-12 h-12 bg-eyefind-blue rounded-full flex items-center justify-center text-white font-bold text-lg">
                    <?php echo strtoupper(substr($email['remetente_nome'], 0, 1)); ?>
                </div>
                <div class="flex-1">
                    <p class="font-bold text-gray-800"><?php echo htmlspecialchars($email['remetente_nome']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($email['remetente_email']); ?></p>
                    <div class="flex items-center gap-4 mt-2 text-xs text-gray-400">
                        <span>
                            <i class="far fa-clock mr-1"></i>
                            Para: <?php echo htmlspecialchars($email['destinatario_nome']); ?>
                        </span>
                        <span>
                            <i class="far fa-calendar mr-1"></i>
                            <?php echo date('d/m/Y H:i', strtotime($email['data_envio'])); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Corpo do email -->
            <div class="email-content prose max-w-none mb-6 p-4 bg-white rounded-lg border border-gray-100">
                <?php echo nl2br(htmlspecialchars($email['corpo'])); ?>
            </div>
            
            <!-- Anexos -->
            <?php if (!empty($anexos)): ?>
                <div class="border-t pt-4 mt-4">
                    <h3 class="font-bold mb-3 text-gray-700">
                        <i class="fas fa-paperclip mr-2"></i>Anexos (<?php echo count($anexos); ?>)
                    </h3>
                    <div class="flex flex-wrap gap-3">
                        <?php foreach ($anexos as $anexo): ?>
                            <a href="<?php echo htmlspecialchars($anexo['caminho_arquivo']); ?>" 
                               class="flex items-center gap-2 bg-gray-100 px-3 py-2 rounded hover:bg-gray-200 transition"
                               download>
                                <i class="fas fa-file text-gray-500"></i>
                                <span class="text-sm"><?php echo htmlspecialchars($anexo['nome_arquivo']); ?></span>
                                <span class="text-xs text-gray-400">(<?php echo round($anexo['tamanho'] / 1024, 1); ?> KB)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Rodapé com ações adicionais -->
            <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                <a href="compor_email.php?responder=<?php echo $email_id; ?>" 
                   class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition text-sm">
                    <i class="fas fa-reply mr-2"></i>Responder
                </a>
                <a href="compor_email.php?responder=todos&id=<?php echo $email_id; ?>" 
                   class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition text-sm">
                    <i class="fas fa-reply-all mr-2"></i>Responder a todos
                </a>
                <a href="compor_email.php?encaminhar=<?php echo $email_id; ?>" 
                   class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition text-sm">
                    <i class="fas fa-forward mr-2"></i>Encaminhar
                </a>
            </div>
        </div>
    </div>

    <script>
        function toggleStar(emailId) {
            fetch('email_acao.php?acao=estrela&id=' + emailId)
                .then(response => response.json())
                .then(data => {
                    const starBtn = document.getElementById('starBtn').querySelector('i');
                    if (data.estrela) {
                        starBtn.classList.remove('far', 'text-gray-400');
                        starBtn.classList.add('fas', 'text-yellow-400');
                    } else {
                        starBtn.classList.remove('fas', 'text-yellow-400');
                        starBtn.classList.add('far', 'text-gray-400');
                    }
                });
        }

        function moverLixeira(emailId) {
            if (confirm('Mover este email para a lixeira?')) {
                fetch('email_acao.php?acao=lixeira&id=' + emailId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.sucesso) {
                            window.location.href = 'email.php';
                        }
                    });
            }
        }
    </script>
</body>
</html>
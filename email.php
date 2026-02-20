<?php
require_once 'email_config.php';

if (!isLogado()) {
    header('Location: login.php?redirect=email.php');
    exit;
}

$usuario = getUsuarioAtual($pdo);
$pagina = $_GET['pagina'] ?? 'inbox';
$busca = $_GET['busca'] ?? '';
$email_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Buscar pastas do usuário
$stmt = $pdo->prepare("SELECT * FROM email_pastas WHERE usuario_id = ? ORDER BY ordem");
$stmt->execute([$usuario['id']]);
$pastas = $stmt->fetchAll();

// Determinar qual lista de emails carregar
switch ($pagina) {
    case 'inbox':
        $emails = getEmailsRecebidos($pdo, $usuario['id']);
        $titulo = 'Caixa de Entrada';
        $icone = 'fa-envelope';
        break;
    case 'enviados':
        $emails = getEmailsEnviados($pdo, $usuario['id']);
        $titulo = 'Enviados';
        $icone = 'fa-paper-plane';
        break;
    case 'estrelas':
        $emails = getEmailsComEstrela($pdo, $usuario['id']);
        $titulo = 'Com Estrela';
        $icone = 'fa-star';
        break;
    case 'lixeira':
        $emails = getEmailsLixeira($pdo, $usuario['id']);
        $titulo = 'Lixeira';
        $icone = 'fa-trash-alt';
        break;
    default:
        $emails = getEmailsRecebidos($pdo, $usuario['id']);
        $titulo = 'Caixa de Entrada';
        $icone = 'fa-envelope';
}

$naoLidos = getTotalNaoLidos($pdo, $usuario['id']);

// Buscar email específico se tiver ID
$emailVisualizado = null;
$threadEmails = [];
$anexos = [];

if ($email_id) {
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
        LEFT JOIN email_estrelas es ON e.id = es.email_id AND es.usuario_id = ?
        WHERE e.id = ? AND (e.destinatario_id = ? OR e.remetente_id = ?)
    ");
    $stmt->execute([$usuario['id'], $email_id, $usuario['id'], $usuario['id']]);
    $emailVisualizado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($emailVisualizado) {
        // Marcar como lido se for destinatário
        if ($emailVisualizado['destinatario_id'] == $usuario['id'] && !$emailVisualizado['data_leitura']) {
            marcarComoLido($pdo, $email_id, $usuario['id']);
        }
        
        // Buscar toda a thread se tiver thread_id
        if ($emailVisualizado['thread_id']) {
            $threadEmails = getThreadEmails($pdo, $emailVisualizado['thread_id'], $usuario['id']);
        } else {
            $threadEmails = [$emailVisualizado];
        }
        
        // Buscar anexos
        $stmt = $pdo->prepare("SELECT * FROM email_anexos WHERE email_id = ?");
        $stmt->execute([$email_id]);
        $anexos = $stmt->fetchAll();
    }
}

// Processar resposta rápida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resposta_rapida'])) {
    $para = $_POST['para'];
    $assunto = $_POST['assunto'];
    $corpo = $_POST['corpo'];
    $thread_id = $_POST['thread_id'] ?? null;
    $resposta_para = $_POST['resposta_para'] ?? null;
    
    if (!empty($para) && !empty($assunto) && !empty($corpo)) {
        $resultado = enviarEmailComThread($pdo, $usuario['id'], $para, $assunto, $corpo, $thread_id, $resposta_para);
        
        if (!isset($resultado['erro'])) {
            // Recarregar a página para mostrar a nova resposta
            header('Location: email.php?pagina=' . $pagina . '&id=' . $email_id . '&msg=resposta_enviada');
            exit;
        } else {
            $erro_resposta = $resultado['erro'];
        }
    } else {
        $erro_resposta = 'Preencha todos os campos';
    }
}

// Mensagem de sucesso
$mensagem_sucesso = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'resposta_enviada') {
        $mensagem_sucesso = 'Resposta enviada com sucesso!';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Eyefind.mail - <?php echo $titulo; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- CKEditor - apenas se estiver visualizando um email -->
    <?php if ($emailVisualizado): ?>
    <script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
    <?php endif; ?>
    
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    'eyefind-blue': '#067191',
                    'eyefind-light': '#F8FAFC',
                    'eyefind-dark': '#02343F',
                    'eyefind-container': '#DCE7EB'
                }
            }
        }
    }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap');
        body { font-family: 'Roboto Condensed', sans-serif; }
        .email-item:hover { background-color: #f3f4f6; }
        .email-item.unread { font-weight: bold; background-color: #f0f9ff; }
        .email-item.selected { background-color: #e6f7ff; border-left: 4px solid #067191; }
        .star-active { color: #fbbf24; }
        .sidebar-link.active {
            background-color: #f3f4f6;
            color: #067191;
            font-weight: bold;
        }
        .email-content {
            line-height: 1.6;
        }
        .email-content p {
            margin-bottom: 1rem;
        }
        .thread-message {
            transition: all 0.2s;
        }
        .thread-message:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .resposta-rapida {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body class="bg-eyefind-light h-screen flex flex-col">

    <!-- ================= HEADER ================= -->
    <section class="bg-[#488BC2] shadow-md">
        <div class="max-w-7xl mx-auto p-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex flex-col md:flex-row items-center gap-6 w-full">
                    <div class="w-64">
                        <a href="email.php">
                            <img src="imagens/eyefindmail.png" alt="Eyefind.mail" class="w-full">
                        </a>
                    </div>
                    
                    <!-- Busca -->
                    <div class="flex-1 max-w-2xl">
                        <form method="GET" class="relative">
                            <input type="hidden" name="pagina" value="<?php echo $pagina; ?>">
                            <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" 
                                   placeholder="Buscar emails..." 
                                   class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-eyefind-blue">
                            <button type="submit" class="absolute right-3 top-2.5 text-gray-400 hover:text-eyefind-blue">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Usuário -->
                    <div class="flex items-center gap-4">
                        <span class="text-white font-bold"><?php echo htmlspecialchars($usuario['nome']); ?></span>
                        <a href="logout.php" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition text-sm">
                            <i class="fas fa-sign-out-alt mr-1"></i>Sair
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="w-full h-2 bg-yellow-400"></div>

    <!-- ================= CONTEÚDO ================= -->
    <div class="flex flex-1 overflow-hidden">

        <!-- ===== SIDEBAR ===== -->
        <aside class="w-64 bg-white border-r border-gray-300 p-6 flex flex-col">
            
            <!-- Escrever -->
            <a href="compor_email.php" class="w-full bg-eyefind-blue text-white py-3 rounded-lg font-bold mb-6 flex items-center justify-center gap-2 hover:bg-eyefind-dark transition">
                <i class="fas fa-pen"></i> Escrever
            </a>

            <!-- Menu -->
            <nav class="space-y-1 text-gray-700 font-medium flex-1">
                <a href="email.php?pagina=inbox" class="flex items-center justify-between gap-3 px-3 py-2 rounded hover:bg-gray-100 <?php echo $pagina == 'inbox' ? 'sidebar-link active' : ''; ?>">
                    <span class="flex items-center gap-3">
                        <i class="far fa-envelope"></i> Caixa de entrada
                    </span>
                    <?php if ($naoLidos > 0): ?>
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $naoLidos; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="email.php?pagina=estrelas" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100 <?php echo $pagina == 'estrelas' ? 'sidebar-link active' : ''; ?>">
                    <i class="far fa-star"></i> Com estrela
                </a>
                
                <a href="email.php?pagina=enviados" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100 <?php echo $pagina == 'enviados' ? 'sidebar-link active' : ''; ?>">
                    <i class="far fa-paper-plane"></i> Enviados
                </a>
                
                <a href="email.php?pagina=lixeira" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100 <?php echo $pagina == 'lixeira' ? 'sidebar-link active' : ''; ?>">
                    <i class="far fa-trash-alt"></i> Lixeira
                </a>
                
                <!-- Pastas -->
                <div class="border-t border-gray-200 my-4 pt-4">
                    <h3 class="text-xs uppercase text-gray-400 font-bold mb-2 px-3">Pastas</h3>
                    <?php if (empty($pastas)): ?>
                        <p class="text-xs text-gray-400 px-3">Nenhuma pasta criada</p>
                    <?php else: ?>
                        <?php foreach ($pastas as $pasta): ?>
                            <a href="email.php?pasta=<?php echo $pasta['id']; ?>" 
                               class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100 text-sm">
                                <i class="fas fa-folder" style="color: <?php echo $pasta['cor']; ?>"></i>
                                <?php echo htmlspecialchars($pasta['nome']); ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </nav>
        </aside>

        <!-- ===== ÁREA PRINCIPAL ===== -->
        <main class="flex-1 bg-white overflow-y-auto">
            
            <?php if ($emailVisualizado): ?>
                <!-- ===== VISUALIZAÇÃO DO EMAIL (COM THREAD) ===== -->
                <div class="p-6">
                    <!-- Cabeçalho com ações -->
                    <div class="flex justify-between items-center mb-6 pb-4 border-b">
                        <a href="email.php?pagina=<?php echo $pagina; ?>" class="text-gray-500 hover:text-gray-700 transition">
                            <i class="fas fa-arrow-left mr-2"></i>Voltar para lista
                        </a>
                        <div class="flex gap-2">
                            <button onclick="toggleStar(<?php echo $emailVisualizado['id']; ?>)" 
                                    class="text-gray-400 hover:text-yellow-400 transition p-2 <?php echo $emailVisualizado['tem_estrela'] ? 'star-active' : ''; ?>" 
                                    id="starBtn">
                                <i class="fa<?php echo $emailVisualizado['tem_estrela'] ? 's' : 'r'; ?> fa-star text-xl"></i>
                            </button>
                            
                            <button onclick="moverLixeira(<?php echo $emailVisualizado['id']; ?>)" 
                                    class="text-red-600 hover:text-red-800 transition p-2">
                                <i class="fas fa-trash-alt text-xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Mensagem de sucesso -->
                    <?php if ($mensagem_sucesso): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 animate-pulse">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?php echo $mensagem_sucesso; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Assunto da conversa -->
                    <h1 class="text-2xl font-bold mb-6 text-eyefind-dark">
                        <?php echo htmlspecialchars($emailVisualizado['assunto']); ?>
                        <?php if (count($threadEmails) > 1): ?>
                            <span class="text-sm font-normal text-gray-500 ml-3">
                                (<?php echo count($threadEmails); ?> mensagens)
                            </span>
                        <?php endif; ?>
                    </h1>
                    
                    <!-- Thread de mensagens -->
                    <div class="space-y-6 mb-8">
                        <?php foreach ($threadEmails as $index => $msg): 
                            $isRemetente = $msg['remetente_id'] == $usuario['id'];
                            $isDestinatario = $msg['destinatario_id'] == $usuario['id'];
                        ?>
                            <div class="thread-message border rounded-lg p-4 <?php echo $isRemetente ? 'bg-blue-50 border-blue-200' : 'bg-white'; ?>" 
                                 id="msg-<?php echo $msg['id']; ?>">
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 <?php echo $isRemetente ? 'bg-green-600' : 'bg-eyefind-blue'; ?> rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                        <?php echo strtoupper(substr($msg['remetente_nome'], 0, 1)); ?>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-2">
                                            <div>
                                                <span class="font-bold text-gray-800"><?php echo htmlspecialchars($msg['remetente_nome']); ?></span>
                                                <span class="text-sm text-gray-500 ml-2">
                                                    &lt;<?php echo htmlspecialchars($msg['remetente_email']); ?>&gt;
                                                </span>
                                                <?php if ($isRemetente): ?>
                                                    <span class="ml-2 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Você</span>
                                                <?php endif; ?>
                                                <?php if ($msg['id'] == $email_id): ?>
                                                    <span class="ml-2 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">Atual</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="text-xs text-gray-400">
                                                <?php echo date('d/m/Y H:i', strtotime($msg['data_envio'])); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="email-content prose max-w-none">
                                            <?php echo $msg['corpo']; ?>
                                        </div>
                                        
                                        <?php if ($msg['id'] == $email_id && !empty($anexos)): ?>
                                            <div class="mt-4 pt-3 border-t">
                                                <p class="text-xs font-bold text-gray-500 mb-2">
                                                    <i class="fas fa-paperclip mr-1"></i>Anexos:
                                                </p>
                                                <div class="flex flex-wrap gap-2">
                                                    <?php foreach ($anexos as $anexo): ?>
                                                        <a href="<?php echo htmlspecialchars($anexo['caminho_arquivo']); ?>" 
                                                           class="text-xs bg-gray-100 px-2 py-1 rounded hover:bg-gray-200 flex items-center gap-1"
                                                           download>
                                                            <i class="fas fa-file"></i>
                                                            <?php echo htmlspecialchars($anexo['nome_arquivo']); ?>
                                                            <span class="text-gray-400">(<?php echo round($anexo['tamanho'] / 1024, 1); ?> KB)</span>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Resposta Rápida com Editor -->
                    <div class="resposta-rapida border-t-2 border-eyefind-blue">
                        <h3 class="font-bold text-lg mb-4 text-eyefind-blue">
                            <i class="fas fa-reply mr-2"></i>Responder a esta conversa
                        </h3>
                        
                        <?php if (isset($erro_resposta)): ?>
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-sm">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <?php echo $erro_resposta; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="formResposta">
                            <input type="hidden" name="resposta_rapida" value="1">
                            <input type="hidden" name="para" value="<?php echo $emailVisualizado['remetente_email']; ?>">
                            <input type="hidden" name="assunto" value="<?php echo htmlspecialchars($emailVisualizado['assunto']); ?>">
                            <input type="hidden" name="thread_id" value="<?php echo $emailVisualizado['thread_id']; ?>">
                            <input type="hidden" name="resposta_para" value="<?php echo $emailVisualizado['id']; ?>">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-bold mb-2">Sua resposta:</label>
                                <textarea name="corpo" id="resposta_editor" rows="8" class="w-full border rounded"><?php echo isset($_POST['corpo']) ? htmlspecialchars($_POST['corpo']) : ''; ?></textarea>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <div class="text-sm text-gray-500">
                                    <i class="far fa-user mr-1"></i>
                                    Respondendo a: <span class="font-bold"><?php echo htmlspecialchars($emailVisualizado['remetente_nome']); ?></span>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" onclick="cancelarResposta()" 
                                            class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-100 transition">
                                        Cancelar
                                    </button>
                                    <button type="submit" 
                                            class="bg-eyefind-blue text-white px-6 py-2 rounded hover:bg-eyefind-dark transition flex items-center gap-2">
                                        <i class="fas fa-paper-plane"></i>
                                        Enviar Resposta
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Inicialização do CKEditor (com as mesmas opções do compor_email) -->
                <script>
                    CKEDITOR.replace('resposta_editor', {
                        height: 250,
                        toolbar: [
                            { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike'] },
                            { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent'] },
                            { name: 'links', items: ['Link', 'Unlink'] },
                            { name: 'styles', items: ['Format', 'Font', 'FontSize'] },
                            { name: 'colors', items: ['TextColor', 'BGColor'] },
                            { name: 'tools', items: ['Maximize'] }
                        ]
                    });
                </script>
                
            <?php else: ?>
                <!-- ===== LISTA DE EMAILS ===== -->
                <div>
                    <!-- Barra superior -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-bold text-gray-700">
                            <i class="far <?php echo $icone; ?> mr-2 text-eyefind-blue"></i>
                            <?php echo $titulo; ?>
                        </h2>
                        <span class="text-sm text-gray-500"><?php echo count($emails); ?> mensagem(s)</span>
                    </div>

                    <!-- Lista de emails -->
                    <div class="divide-y divide-gray-200">
                        <?php if (empty($emails)): ?>
                            <div class="text-center py-12">
                                <i class="far fa-envelope-open text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">Nenhum email encontrado</p>
                                <?php if ($pagina == 'inbox'): ?>
                                    <p class="text-sm text-gray-400 mt-2">Quando você receber emails, eles aparecerão aqui</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($emails as $email): ?>
                                <a href="email.php?pagina=<?php echo $pagina; ?>&id=<?php echo $email['id']; ?>" 
                                   class="block email-item px-6 py-4 hover:bg-gray-50 <?php echo empty($email['data_leitura']) && $pagina == 'inbox' ? 'unread' : ''; ?> <?php echo $email_id == $email['id'] ? 'selected' : ''; ?>">
                                    <div class="flex items-center gap-4">
                                        <!-- Estrela -->
                                        <div onclick="event.preventDefault(); toggleStarList(<?php echo $email['id']; ?>, this)">
                                            <i class="fa<?php echo isset($email['tem_estrela']) && $email['tem_estrela'] ? 's' : 'r'; ?> fa-star <?php echo isset($email['tem_estrela']) && $email['tem_estrela'] ? 'star-active' : 'text-gray-400'; ?> hover:text-yellow-400"></i>
                                        </div>

                                        <!-- Remetente/Destinatário -->
                                        <div class="w-48 font-semibold text-gray-800 truncate">
                                            <?php 
                                            if ($pagina == 'enviados') {
                                                echo htmlspecialchars($email['destinatario_nome'] ?? 'Para: ' . $email['destinatario_email']);
                                            } else {
                                                echo htmlspecialchars($email['remetente_nome'] ?? $email['remetente_email']);
                                            }
                                            ?>
                                        </div>

                                        <!-- Assunto e preview -->
                                        <div class="flex-1 min-w-0">
                                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($email['assunto']); ?></span>
                                            <span class="text-gray-500 ml-2 truncate">– <?php echo htmlspecialchars(substr(strip_tags($email['corpo']), 0, 60)); ?>...</span>
                                        </div>

                                        <!-- Data -->
                                        <div class="text-sm text-gray-400 whitespace-nowrap">
                                            <?php 
                                            $data = new DateTime($email['data_envio']);
                                            $hoje = new DateTime();
                                            if ($data->format('Y-m-d') == $hoje->format('Y-m-d')) {
                                                echo $data->format('H:i');
                                            } else {
                                                echo $data->format('d/m');
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function toggleStar(emailId) {
            fetch('email_acao.php?acao=estrela&id=' + emailId)
                .then(response => response.json())
                .then(data => {
                    const icon = document.querySelector('#starBtn i');
                    if (icon) {
                        if (data.estrela) {
                            icon.classList.remove('far');
                            icon.classList.add('fas', 'star-active');
                        } else {
                            icon.classList.remove('fas', 'star-active');
                            icon.classList.add('far');
                        }
                    }
                    
                    // Atualizar também na lista se existir
                    const listIcon = document.querySelector(`a[href*="id=${emailId}"] i.fa-star`);
                    if (listIcon) {
                        if (data.estrela) {
                            listIcon.classList.remove('far', 'text-gray-400');
                            listIcon.classList.add('fas', 'star-active');
                        } else {
                            listIcon.classList.remove('fas', 'star-active');
                            listIcon.classList.add('far', 'text-gray-400');
                        }
                    }
                })
                .catch(error => console.error('Erro:', error));
        }

        function toggleStarList(emailId, element) {
            event.preventDefault();
            const icon = element.querySelector('i') || element;
            
            fetch('email_acao.php?acao=estrela&id=' + emailId)
                .then(response => response.json())
                .then(data => {
                    if (data.estrela) {
                        icon.classList.remove('far', 'text-gray-400');
                        icon.classList.add('fas', 'star-active');
                    } else {
                        icon.classList.remove('fas', 'star-active');
                        icon.classList.add('far', 'text-gray-400');
                    }
                })
                .catch(error => console.error('Erro:', error));
        }

        function moverLixeira(emailId) {
            if (confirm('Mover este email para a lixeira?')) {
                fetch('email_acao.php?acao=lixeira&id=' + emailId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.sucesso) {
                            window.location.href = 'email.php?pagina=<?php echo $pagina; ?>';
                        }
                    })
                    .catch(error => console.error('Erro:', error));
            }
        }

        function cancelarResposta() {
            if (confirm('Descartar esta resposta?')) {
                <?php if ($emailVisualizado): ?>
                CKEDITOR.instances.resposta_editor.setData('');
                <?php endif; ?>
            }
        }
    </script>
</body>
</html>
<?php
require_once 'email_config.php';

if (!isLogado()) {
    header('Location: login.php?redirect=email.php');
    exit;
}

$usuario = getUsuarioAtual($pdo);
$pagina = $_GET['pagina'] ?? 'inbox';
$busca = $_GET['busca'] ?? '';

// Determinar qual lista de emails carregar
switch ($pagina) {
    case 'inbox':
        $emails = getEmailsRecebidos($pdo, $usuario['id']);
        $titulo = 'Caixa de Entrada';
        break;
    case 'enviados':
        $emails = getEmailsEnviados($pdo, $usuario['id']);
        $titulo = 'Enviados';
        break;
    case 'estrelas':
        $emails = getEmailsComEstrela($pdo, $usuario['id']);
        $titulo = 'Com Estrela';
        break;
    case 'lixeira':
        $emails = getEmailsLixeira($pdo, $usuario['id']);
        $titulo = 'Lixeira';
        break;
    default:
        $emails = getEmailsRecebidos($pdo, $usuario['id']);
        $titulo = 'Caixa de Entrada';
}

$naoLidos = getTotalNaoLidos($pdo, $usuario['id']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Eyefind.mail - <?php echo $titulo; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
        .email-item.unread { font-weight: bold; }
        .star-active { color: #fbbf24; }
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
                            <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" 
                                   placeholder="Buscar emails..." 
                                   class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-eyefind-blue">
                            <button type="submit" class="absolute right-3 top-2.5 text-gray-400">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Usuário -->
                    <div class="flex items-center gap-4">
                        <span class="text-white font-bold"><?php echo htmlspecialchars($usuario['nome']); ?></span>
                        <a href="logout.php" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition text-sm">
                            Sair
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
            <nav class="space-y-3 text-gray-700 font-medium flex-1">
                <a href="email.php?pagina=inbox" class="flex items-center justify-between gap-3 px-3 py-2 rounded hover:bg-gray-100 <?php echo $pagina == 'inbox' ? 'bg-gray-100 text-eyefind-blue' : ''; ?>">
                    <span class="flex items-center gap-3">
                        <i class="far fa-envelope"></i> Caixa de entrada
                    </span>
                    <?php if ($naoLidos > 0): ?>
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $naoLidos; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="email.php?pagina=estrelas" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100 <?php echo $pagina == 'estrelas' ? 'bg-gray-100 text-eyefind-blue' : ''; ?>">
                    <i class="far fa-star"></i> Com estrela
                </a>
                
                <a href="email.php?pagina=enviados" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100 <?php echo $pagina == 'enviados' ? 'bg-gray-100 text-eyefind-blue' : ''; ?>">
                    <i class="far fa-paper-plane"></i> Enviados
                </a>
                
                <a href="email.php?pagina=lixeira" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100 <?php echo $pagina == 'lixeira' ? 'bg-gray-100 text-eyefind-blue' : ''; ?>">
                    <i class="far fa-trash-alt"></i> Lixeira
                </a>
                
                <div class="border-t border-gray-200 my-4 pt-4">
                    <h3 class="text-xs uppercase text-gray-400 font-bold mb-2">Pastas</h3>
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM email_pastas WHERE usuario_id = ? ORDER BY ordem");
                    $stmt->execute([$usuario['id']]);
                    $pastas = $stmt->fetchAll();
                    
                    foreach ($pastas as $pasta):
                    ?>
                    <a href="email.php?pasta=<?php echo $pasta['id']; ?>" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100 text-sm">
                        <i class="fas fa-folder" style="color: <?php echo $pasta['cor']; ?>"></i>
                        <?php echo htmlspecialchars($pasta['nome']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </nav>
        </aside>

        <!-- ===== LISTA DE EMAILS ===== -->
        <main class="flex-1 bg-white overflow-y-auto">
            
            <!-- Barra superior -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-bold text-gray-700"><?php echo $titulo; ?></h2>
                <span class="text-sm text-gray-500"><?php echo count($emails); ?> mensagens</span>
            </div>

            <!-- Lista de emails -->
            <div class="divide-y divide-gray-200">
                <?php if (empty($emails)): ?>
                    <div class="text-center py-12">
                        <i class="far fa-envelope-open text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Nenhum email encontrado</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($emails as $email): ?>
                        <div class="email-item px-6 py-4 hover:bg-gray-50 cursor-pointer flex items-center gap-4 <?php echo empty($email['data_leitura']) && $pagina == 'inbox' ? 'unread' : ''; ?>" 
                             onclick="window.location='ver_email.php?id=<?php echo $email['id']; ?>'">
                            
                            <!-- Estrela -->
                            <button onclick="event.stopPropagation(); toggleStar(<?php echo $email['id']; ?>, this)">
                                <i class="fa<?php echo isset($email['tem_estrela']) && $email['tem_estrela'] ? 's' : 'r'; ?> fa-star <?php echo isset($email['tem_estrela']) && $email['tem_estrela'] ? 'star-active' : 'text-gray-400'; ?> hover:text-yellow-400"></i>
                            </button>

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
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleStar(emailId, element) {
            fetch('email_acao.php?acao=estrela&id=' + emailId)
                .then(response => response.json())
                .then(data => {
                    if (data.estrela) {
                        element.classList.add('star-active');
                        element.classList.remove('text-gray-400');
                        element.classList.remove('far');
                        element.classList.add('fas');
                    } else {
                        element.classList.remove('star-active');
                        element.classList.add('text-gray-400');
                        element.classList.remove('fas');
                        element.classList.add('far');
                    }
                });
        }
    </script>
</body>
</html>
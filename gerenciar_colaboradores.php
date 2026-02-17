<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$website_id = intval($_GET['website_id'] ?? 0);
if (!$website_id) {
    header('Location: manage_blogs.php');
    exit;
}

$usuario = getUsuarioAtual($pdo);

// Verificar se é admin do site (dono)
$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$website_id, $usuario['id']]);
$website = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$website) {
    // Verificar se é colaborador com nível admin
    $stmt = $pdo->prepare("
        SELECT sc.*, w.nome 
        FROM site_colaboradores sc 
        JOIN websites w ON sc.website_id = w.id 
        WHERE sc.website_id = ? AND sc.usuario_id = ? AND sc.nivel IN ('admin', 'editor')
    ");
    $stmt->execute([$website_id, $usuario['id']]);
    $colaborador = $stmt->fetch();
    
    if (!$colaborador) {
        header('Location: manage_blogs.php?msg=sem_permissao');
        exit;
    }
    $website = ['nome' => $colaborador['nome']];
}

// Processar adição de colaborador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar'])) {
    $email = $_POST['email'];
    $nivel = $_POST['nivel'];
    
    // Buscar usuário pelo email
    $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $colaborador = $stmt->fetch();
    
    if ($colaborador) {
        // Verificar se já é colaborador
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_colaboradores WHERE website_id = ? AND usuario_id = ?");
        $stmt->execute([$website_id, $colaborador['id']]);
        
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO site_colaboradores (website_id, usuario_id, nivel) VALUES (?, ?, ?)");
            $stmt->execute([$website_id, $colaborador['id'], $nivel]);
            $msg = "Colaborador adicionado com sucesso!";
        } else {
            $erro = "Este usuário já é colaborador deste site.";
        }
    } else {
        $erro = "Usuário não encontrado com este email.";
    }
}

// Processar remoção de colaborador
if (isset($_GET['remover'])) {
    $colaborador_id = intval($_GET['remover']);
    $stmt = $pdo->prepare("DELETE FROM site_colaboradores WHERE id = ? AND website_id = ?");
    $stmt->execute([$colaborador_id, $website_id]);
    header('Location: gerenciar_colaboradores.php?website_id=' . $website_id . '&msg=removido');
    exit;
}

// Listar colaboradores
$stmt = $pdo->prepare("
    SELECT sc.*, u.nome, u.email 
    FROM site_colaboradores sc
    JOIN usuarios u ON sc.usuario_id = u.id
    WHERE sc.website_id = ?
");
$stmt->execute([$website_id]);
$colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Colaboradores - <?php echo htmlspecialchars($website['nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-blue-600">Gerenciar Colaboradores</h1>
                    <p class="text-gray-600">Site: <?php echo htmlspecialchars($website['nome']); ?></p>
                </div>
                <a href="manage_blogs.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
            </div>

            <?php if (isset($msg)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($erro)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $erro; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'removido'): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    Colaborador removido com sucesso!
                </div>
            <?php endif; ?>

            <!-- Formulário para adicionar colaborador -->
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <h2 class="text-lg font-bold mb-4">Adicionar Novo Colaborador</h2>
                <form method="POST" class="flex gap-4">
                    <div class="flex-1">
                        <input type="email" name="email" placeholder="Email do usuário" required 
                               class="w-full px-3 py-2 border rounded">
                    </div>
                    <div class="w-48">
                        <select name="nivel" class="w-full px-3 py-2 border rounded">
                            <option value="autor">Autor (criar/editar próprios)</option>
                            <option value="revisor">Revisor (revisar e publicar)</option>
                            <option value="editor">Editor (gerenciar todos)</option>
                            <option value="admin">Admin (gerenciar colaboradores)</option>
                        </select>
                    </div>
                    <button type="submit" name="adicionar" 
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Adicionar
                    </button>
                </form>
            </div>

            <!-- Lista de colaboradores -->
            <h2 class="text-lg font-bold mb-4">Colaboradores Atuais</h2>
            <div class="space-y-3">
                <!-- Dono do site -->
                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-crown text-yellow-500 text-xl"></i>
                        <div>
                            <p class="font-bold"><?php echo htmlspecialchars($usuario['nome']); ?> (Dono)</p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($usuario['email']); ?></p>
                        </div>
                    </div>
                    <span class="bg-blue-600 text-white px-3 py-1 rounded-full text-xs">Admin</span>
                </div>

                <!-- Outros colaboradores -->
                <?php foreach ($colaboradores as $colab): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-user text-gray-400 text-xl"></i>
                        <div>
                            <p class="font-bold"><?php echo htmlspecialchars($colab['nome']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($colab['email']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="bg-<?php 
                            echo $colab['nivel'] == 'admin' ? 'red' : 
                                ($colab['nivel'] == 'editor' ? 'purple' : 
                                ($colab['nivel'] == 'revisor' ? 'orange' : 'green')); 
                        ?>-100 text-<?php 
                            echo $colab['nivel'] == 'admin' ? 'red' : 
                                ($colab['nivel'] == 'editor' ? 'purple' : 
                                ($colab['nivel'] == 'revisor' ? 'orange' : 'green')); 
                        ?>-700 px-3 py-1 rounded-full text-xs">
                            <?php 
                            switch($colab['nivel']) {
                                case 'admin': echo 'Admin'; break;
                                case 'editor': echo 'Editor'; break;
                                case 'revisor': echo 'Revisor'; break;
                                default: echo 'Autor';
                            }
                            ?>
                        </span>
                        <a href="?website_id=<?php echo $website_id; ?>&remover=<?php echo $colab['id']; ?>" 
                           class="text-red-500 hover:text-red-700 ml-2"
                           onclick="return confirm('Remover este colaborador?');">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
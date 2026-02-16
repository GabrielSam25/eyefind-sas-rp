<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$usuario = getUsuarioAtual($pdo);

if (!$usuario || !isset($usuario['is_admin']) || $usuario['is_admin'] != 1) {
    $_SESSION['erro'] = "Acesso restrito a administradores";
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['adicionar_publicidade'])) {
        if (!isset($_POST['nome']) || empty($_POST['nome'])) {
            $_SESSION['erro'] = "O campo Nome é obrigatório";
            header('Location: admin.php');
            exit;
        }

        if (!isset($_POST['website_id']) || empty($_POST['website_id'])) {
            $_SESSION['erro'] = "É necessário vincular um website";
            header('Location: admin.php');
            exit;
        }

        $nome = $_POST['nome'];
        $website_id = $_POST['website_id'];
        $url = "website.php?id=" . $website_id; // Gera a URL com base no ID do website
        $imagem = $_POST['imagem'] ?? '';
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("INSERT INTO publicidade (nome, url, imagem, ativo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nome, $url, $imagem, $ativo]);
            $_SESSION['sucesso'] = "Publicidade adicionada com sucesso!";
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao adicionar publicidade: " . $e->getMessage();
        }
        header('Location: admin.php');
        exit;
    }

        // Sites pendentes
    $sites_pendentes = $pdo->query("
        SELECT w.*, u.nome as usuario_nome 
        FROM websites w 
        JOIN usuarios u ON w.usuario_id = u.id 
        WHERE w.status = 'pending'
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Todos os sites (para tabela geral)
    $sites = $pdo->query("
        SELECT w.*, u.nome as usuario_nome 
        FROM websites w 
        JOIN usuarios u ON w.usuario_id = u.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Excluir site
    if (isset($_POST['excluir_site'])) {
        $site_id = intval($_POST['site_id']);
        $stmt = $pdo->prepare("DELETE FROM websites WHERE id = ?");
        $stmt->execute([$site_id]);
        $mensagem = "Site excluído com sucesso!";
    }

    // Excluir conta
    if (isset($_POST['excluir_conta'])) {
        $usuario_id = intval($_POST['usuario_id']);
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $stmt = $pdo->prepare("DELETE FROM websites WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $mensagem = "Conta excluída com sucesso!";
    }
}

// Obter dados para exibição
$publicidades = $pdo->query("SELECT * FROM publicidade")->fetchAll(PDO::FETCH_ASSOC);
$sites = $pdo->query("SELECT w.*, u.nome as usuario_nome FROM websites w JOIN usuarios u ON w.usuario_id = u.id")->fetchAll(PDO::FETCH_ASSOC);
$usuarios = $pdo->query("SELECT * FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Eyefind.info</title>
    <link rel="icon" type="image/png" sizes="192x192" href="icon/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icon/android-chrome-512x512.png">
    <link rel="apple-touch-icon" sizes="180x180" href="icon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="icon/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icon/favicon-32x32.png">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-eyefind-light">
    <section class="bg-[#488BC2] shadow-md">
        <div class="p-4 flex flex-col md:flex-row justify-between items-center max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row items-center gap-6">
                <div class="w-64">
                    <img src="imagens/eyefind-logo.png" alt="Eyefind.info Logo" class="w-full">
                </div>
                <div class="w-full md:w-96">
                    <form action="busca.php" method="GET">
                        <div class="relative">
                            <input type="text" name="q"
                                class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue"
                                placeholder="Procurar no Eyefind">
                            <button type="submit" class="absolute right-3 top-3 text-eyefind-blue">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="flex items-center gap-4 mt-4 md:mt-0">
                <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded font-bold hover:bg-blue-700 transition">
                    Voltar
                </a>
                <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded font-bold hover:bg-red-700 transition">
                    Logout
                </a>
            </div>
        </div>
    </section>

    <div class="w-full h-2 bg-yellow-400"></div>

    <div class="max-w-7xl mx-auto mt-1">
        <?php if (isset($mensagem)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $mensagem; ?></span>
            </div>
        <?php endif; ?>

        <section class="bg-white p-6 shadow-md mb-6">
            <h2 class="text-2xl font-bold text-eyefind-blue mb-6">Painel de Administração</h2>

            <div class="grid md:grid-cols-3 gap-6">
                <!-- Estatísticas -->
                <div class="bg-gray-100 p-4 rounded-lg">
                    <h3 class="text-lg font-bold mb-4">Estatísticas</h3>
                    <div class="space-y-2">
                        <p>Total de Usuários: <?php echo count($usuarios); ?></p>
                        <p>Total de Sites: <?php echo count($sites); ?></p>
                        <p>Publicidades Ativas: <?php echo count(array_filter($publicidades, function ($p) {
                                                    return $p['ativo'] == 1;
                                                })); ?></p>
                    </div>
                </div>

                <!-- Adicionar Publicidade -->
                <div class="bg-gray-100 p-4 rounded-lg">
                    <h3 class="text-lg font-bold mb-4">Adicionar Publicidade</h3>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2">Nome</label>
                            <input type="text" name="nome" class="w-full px-3 py-2 border rounded" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2">Vincular Website</label>
                            <select name="website_id" class="w-full px-3 py-2 border rounded" required>
                                <option value="">Selecione um website</option>
                                <?php foreach ($sites as $site): ?>
                                    <option value="<?php echo $site['id']; ?>">
                                        <?php echo htmlspecialchars($site['nome']); ?> (ID: <?php echo $site['id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2">Imagem (URL)</label>
                            <input type="url" name="imagem" class="w-full px-3 py-2 border rounded" required>
                        </div>
                        <div class="mb-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="ativo" class="rounded border-gray-300" checked>
                                <span class="ml-2">Ativo</span>
                            </label>
                        </div>
                        <button type="submit" name="adicionar_publicidade" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Adicionar
                        </button>
                    </form>
                </div>

                <!-- Ações Rápidas -->
                <div class="bg-gray-100 p-4 rounded-lg">
                    <h3 class="text-lg font-bold mb-4">Ações Rápidas</h3>
                    <div class="space-y-2">
                        <a href="#gerenciar-sites" class="block bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">
                            Gerenciar Sites
                        </a>
                        <a href="#gerenciar-usuarios" class="block bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                            Gerenciar Usuários
                        </a>
                        <a href="#gerenciar-publicidades" class="block bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                            Gerenciar Publicidades
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Gerenciar Sites -->
        <section id="gerenciar-sites" class="bg-white p-6 shadow-md mb-6">
            <h2 class="text-2xl font-bold text-eyefind-blue mb-6">Gerenciar Sites</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b">ID</th>
                            <th class="py-2 px-4 border-b">Nome</th>
                            <th class="py-2 px-4 border-b">URL</th>
                            <th class="py-2 px-4 border-b">Usuário</th>
                            <th class="py-2 px-4 border-b">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sites as $site): ?>
                            <tr>
                                <td class="py-2 px-4 border-b"><?php echo $site['id']; ?></td>
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($site['nome']); ?></td>
                                <td class="py-2 px-4 border-b">
                                    <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank" class="text-blue-500 hover:underline">
                                        <?php echo htmlspecialchars($site['url']); ?>
                                    </a>
                                </td>
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($site['usuario_nome']); ?></td>
                                <td class="py-2 px-4 border-b">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
                                        <button type="submit" name="excluir_site" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600" onclick="return confirm('Tem certeza que deseja excluir este site?');">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Gerenciar Usuários -->
        <section id="gerenciar-usuarios" class="bg-white p-6 shadow-md mb-6">
            <h2 class="text-2xl font-bold text-eyefind-blue mb-6">Gerenciar Usuários</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b">ID</th>
                            <th class="py-2 px-4 border-b">Nome</th>
                            <th class="py-2 px-4 border-b">Email</th>
                            <th class="py-2 px-4 border-b">Admin</th>
                            <th class="py-2 px-4 border-b">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $user): ?>
                            <tr>
                                <td class="py-2 px-4 border-b"><?php echo $user['id']; ?></td>
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($user['nome']); ?></td>
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="py-2 px-4 border-b"><?php echo $user['is_admin'] ? 'Sim' : 'Não'; ?></td>
                                <td class="py-2 px-4 border-b">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="usuario_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="excluir_conta" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600" onclick="return confirm('Tem certeza que deseja excluir esta conta? TODOS os sites deste usuário também serão excluídos.');">
                                            Excluir
                                        </button>
                                    </form>
                                    <?php if (!$user['is_admin']): ?>
                                        <form method="POST" action="tornar_admin.php" class="inline ml-2">
                                            <input type="hidden" name="usuario_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">
                                                Tornar Admin
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="gerenciar-publicidades" class="bg-white p-6 shadow-md">
            <h2 class="text-2xl font-bold text-eyefind-blue mb-6">Gerenciar Publicidades</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b">ID</th>
                            <th class="py-2 px-4 border-b">Nome</th>
                            <th class="py-2 px-4 border-b">Imagem</th>
                            <th class="py-2 px-4 border-b">URL</th>
                            <th class="py-2 px-4 border-b">Status</th>
                            <th class="py-2 px-4 border-b">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($publicidades as $pub): ?>
                            <tr>
                                <td class="py-2 px-4 border-b"><?php echo $pub['id']; ?></td>
                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($pub['nome']); ?></td>
                                <td class="py-2 px-4 border-b">
                                    <img src="<?php echo htmlspecialchars($pub['imagem']); ?>" alt="Publicidade" class="h-16">
                                </td>
                                <td class="py-2 px-4 border-b">
                                    <a href="<?php echo htmlspecialchars($pub['url']); ?>" target="_blank" class="text-blue-500 hover:underline">
                                        <?php echo htmlspecialchars($pub['url']); ?>
                                    </a>
                                </td>
                                <td class="py-2 px-4 border-b">
                                    <?php echo $pub['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </td>
                                <td class="py-2 px-4 border-b">
                                    <a href="editar_publicidade.php?id=<?php echo $pub['id']; ?>" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">
                                        Editar
                                    </a>
                                    <form method="POST" action="excluir_publicidade.php" class="inline ml-2">
                                        <input type="hidden" name="id" value="<?php echo $pub['id']; ?>">
                                        <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600" onclick="return confirm('Tem certeza que deseja excluir esta publicidade?');">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>

</html>

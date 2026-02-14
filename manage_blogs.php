<?php
// Incluir arquivo de configuração e conexão
require_once 'config.php';

// Verificar se o usuário está logado
if (!isLogado()) {
    header('Location: login.php');
    exit;
}

// Obter o usuário atual
$usuario = getUsuarioAtual($pdo);
$usuario_id = $usuario['id'];

// Obter todos os blogs do usuário
$stmt = $pdo->prepare("SELECT * FROM websites WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Redirecionar para a página de criação de blog se o usuário não tiver blogs
if (empty($blogs)) {
    header('Location: new_blog.php');
    exit;
}

// Processar a exclusão de um blog
if (isset($_GET['excluir'])) {
    $blog_id = intval($_GET['excluir']);
    $stmt = $pdo->prepare("DELETE FROM websites WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$blog_id, $usuario_id]);
    header('Location: manage_blogs.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Blogs - Eyefind.info</title>
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
        <section class="bg-white p-6 shadow-md">
            <h2 class="text-2xl font-bold text-eyefind-blue mb-6">Meus Blogs</h2>
            <div class="space-y-4">
                <?php foreach ($blogs as $blog): ?>
                    <div class="flex items-center justify-between p-4 border border-eyefind-blue rounded-lg">
                        <div class="flex items-center gap-4">
                            <?php if ($blog['imagem']): ?>
                                <img src="<?php echo htmlspecialchars($blog['imagem']); ?>" alt="Imagem do Blog" class="w-64 h-auto object-cover rounded">
                            <?php endif; ?>
                            <div>
                                <h3 class="text-lg font-bold text-eyefind-dark"><?php echo htmlspecialchars($blog['nome']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($blog['descricao']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="edit_blog.php?id=<?php echo $blog['id']; ?>" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <a href="manage_blogs.php?excluir=<?php echo $blog['id']; ?>" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition" onclick="return confirm('Tem certeza que deseja excluir este blog?');">
                                <i class="fas fa-trash"></i> Excluir
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-6">
                <a href="new_blog.php" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700 transition">
                    <i class="fas fa-plus"></i> Criar Novo Blog
                </a>
            </div>
        </section>
    </div>
</body>

</html>

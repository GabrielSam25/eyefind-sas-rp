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

// Obter mensagem da URL
$mensagem = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';

// Obter todos os blogs do usuário
$stmt = $pdo->prepare("SELECT * FROM websites WHERE usuario_id = ? ORDER BY id DESC");
$stmt->execute([$usuario_id]);
$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar a exclusão de um blog
if (isset($_GET['excluir'])) {
    $blog_id = intval($_GET['excluir']);
    
    // Primeiro excluir áreas dinâmicas
    $stmt = $pdo->prepare("DELETE FROM dynamic_areas WHERE website_id = ?");
    $stmt->execute([$blog_id]);
    
    // Depois excluir o blog
    $stmt = $pdo->prepare("DELETE FROM websites WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$blog_id, $usuario_id]);
    
    header('Location: manage_blogs.php?msg=' . urlencode('Blog excluído com sucesso!'));
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
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
                <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded font-bold hover:bg-red-700 transition">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
    </section>

    <div class="w-full h-2 bg-yellow-400"></div>

    <div class="max-w-7xl mx-auto mt-1">
        <?php if ($mensagem): ?>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4">
                <i class="fas fa-info-circle mr-2"></i>
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <section class="bg-white p-6 shadow-md">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-eyefind-blue">
                    <i class="fas fa-blog mr-2"></i>
                    Meus Blogs
                </h2>
                <a href="new_blog.php" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700 transition">
                    <i class="fas fa-plus mr-2"></i> Criar Novo Blog
                </a>
            </div>
            
            <?php if (empty($blogs)): ?>
                <div class="text-center py-12 bg-gray-50 rounded-lg">
                    <i class="fas fa-blog text-6xl text-gray-400 mb-4"></i>
                    <p class="text-xl text-gray-600 mb-4">Você ainda não tem nenhum blog.</p>
                    <a href="new_blog.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition inline-block">
                        <i class="fas fa-plus mr-2"></i> Criar seu primeiro blog
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-6">
                    <?php foreach ($blogs as $blog): ?>
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition">
                            <div class="p-6">
                                <div class="flex flex-col md:flex-row gap-6">
                                    <?php if ($blog['imagem']): ?>
                                        <div class="md:w-48 flex-shrink-0">
                                            <img src="<?php echo htmlspecialchars($blog['imagem']); ?>" 
                                                 alt="Imagem do Blog" 
                                                 class="w-full h-32 object-cover rounded-lg">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-eyefind-dark mb-2">
                                            <?php echo htmlspecialchars($blog['nome']); ?>
                                        </h3>
                                        <p class="text-gray-600 mb-4">
                                            <?php echo htmlspecialchars($blog['descricao']); ?>
                                        </p>
                                        
                                        <div class="flex flex-wrap gap-2">
                                            <a href="website.php?id=<?php echo $blog['id']; ?>" 
                                               target="_blank"
                                               class="bg-green-500 text-white px-3 py-1.5 rounded hover:bg-green-600 transition text-sm">
                                                <i class="fas fa-eye mr-1"></i> Visualizar
                                            </a>
                                            
                                            <a href="edit_blog.php?id=<?php echo $blog['id']; ?>" 
                                               class="bg-blue-500 text-white px-3 py-1.5 rounded hover:bg-blue-600 transition text-sm">
                                                <i class="fas fa-edit mr-1"></i> Editar
                                            </a>
                                            
                                            <a href="manage_blogs.php?excluir=<?php echo $blog['id']; ?>" 
                                               class="bg-red-500 text-white px-3 py-1.5 rounded hover:bg-red-600 transition text-sm"
                                               onclick="return confirm('Tem certeza que deseja excluir este blog? Todas as áreas dinâmicas também serão excluídas.');">
                                                <i class="fas fa-trash mr-1"></i> Excluir
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
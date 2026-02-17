<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$usuario = getUsuarioAtual($pdo);
$usuario_id = $usuario['id'];

$stmt = $pdo->prepare("SELECT * FROM websites WHERE usuario_id = ? ORDER BY id DESC");
$stmt->execute([$usuario_id]);
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['excluir'])) {
    $site_id = intval($_GET['excluir']);
    $stmt = $pdo->prepare("DELETE FROM websites WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$site_id, $usuario_id]);
    header('Location: manage_blogs.php?msg=site_excluido');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Meus Sites - Eyefind.info</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap');
        body { font-family: 'Roboto Condensed', sans-serif; }
        .tipo-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .tipo-empresa { background-color: #e6f2ff; color: #0066cc; }
        .tipo-blog { background-color: #e6ffe6; color: #00802b; }
        .tipo-noticias { background-color: #ffe6e6; color: #cc0000; }
        .tipo-classificados { background-color: #f2e6ff; color: #6600cc; }
    </style>
</head>
<body class="bg-eyefind-light">
    <section class="bg-[#488BC2] shadow-md">
        <div class="p-4 flex flex-col md:flex-row justify-between items-center max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row items-center gap-6">
                <div class="w-64">
                    <img src="imagens/eyefind-logo.png" alt="Eyefind.info Logo" class="w-full">
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

    <div class="max-w-7xl mx-auto mt-8 px-4">
        <?php if (isset($_GET['msg'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php 
                if ($_GET['msg'] == 'site_excluido') echo "Site excluído com sucesso!";
                ?>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 shadow-md rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-eyefind-blue">Meus Sites</h1>
                <a href="new_blog.php" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700 transition">
                    <i class="fas fa-plus mr-2"></i>Criar Novo Site
                </a>
            </div>

            <?php if (empty($sites)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-globe text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Você ainda não tem nenhum site.</p>
                    <a href="new_blog.php" class="inline-block mt-4 bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                        Criar meu primeiro site
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($sites as $site): 
                        $tipoClass = 'tipo-' . $site['tipo'];
                        $statusClass = $site['status'] == 'approved' ? 'text-green-600' : ($site['status'] == 'pending' ? 'text-yellow-600' : 'text-red-600');
                        $statusIcon = $site['status'] == 'approved' ? 'fa-check-circle' : ($site['status'] == 'pending' ? 'fa-clock' : 'fa-ban');
                    ?>
                        <div class="border rounded-lg p-4 hover:shadow-md transition">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="flex items-start gap-4 flex-1">
                                    <?php if ($site['imagem']): ?>
                                        <img src="<?php echo htmlspecialchars($site['imagem']); ?>" alt="" class="w-20 h-20 object-cover rounded hidden md:block">
                                    <?php endif; ?>
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="font-bold text-lg"><?php echo htmlspecialchars($site['nome']); ?></h3>
                                            <span class="tipo-badge <?php echo $tipoClass; ?>">
                                                <?php 
                                                switch($site['tipo']) {
                                                    case 'empresa': echo 'Empresa'; break;
                                                    case 'blog': echo 'Blog'; break;
                                                    case 'noticias': echo 'Notícias'; break;
                                                    case 'classificados': echo 'Classificados'; break;
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($site['descricao']); ?></p>
                                        <div class="flex items-center gap-4 text-sm">
                                            <span class="<?php echo $statusClass; ?>">
                                                <i class="fas <?php echo $statusIcon; ?> mr-1"></i>
                                                <?php 
                                                if ($site['status'] == 'approved') echo 'Aprovado';
                                                elseif ($site['status'] == 'pending') echo 'Pendente';
                                                else echo 'Rejeitado';
                                                ?>
                                            </span>
                                            <a href="website.php?id=<?php echo $site['id']; ?>" target="_blank" class="text-blue-600 hover:underline">
                                                <i class="fas fa-external-link-alt mr-1"></i>Ver site
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <?php if ($site['tipo'] == 'blog'): ?>
                                        <a href="gerenciar_posts.php?website_id=<?php echo $site['id']; ?>" 
                                           class="bg-green-100 text-green-700 px-3 py-1 rounded text-sm hover:bg-green-200 transition">
                                            <i class="fas fa-pen mr-1"></i>Posts
                                        </a>
                                            <?php if ($site['tipo'] == 'noticias'): ?>
                                                <a href="gerenciar_noticias.php?website_id=<?php echo $site['id']; ?>" 
                                                class="bg-red-100 text-red-700 px-3 py-1 rounded text-sm hover:bg-red-200 transition">
                                                    <i class="fas fa-newspaper mr-1"></i>Notícias
                                            </a>
                                    <?php elseif ($site['tipo'] == 'classificados'): ?>
                                        <a href="gerenciar_anuncios.php?website_id=<?php echo $site['id']; ?>" 
                                           class="bg-purple-100 text-purple-700 px-3 py-1 rounded text-sm hover:bg-purple-200 transition">
                                            <i class="fas fa-tag mr-1"></i>Anúncios
                                        </a>
                                    <?php endif; ?>

                                    <a href="edit_blog.php?id=<?php echo $site['id']; ?>" 
                                       class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-sm hover:bg-blue-200 transition">
                                        <i class="fas fa-edit mr-1"></i>Editar
                                    </a>
                                    <a href="manage_blogs.php?excluir=<?php echo $site['id']; ?>" 
                                       class="bg-red-100 text-red-700 px-3 py-1 rounded text-sm hover:bg-red-200 transition"
                                       onclick="return confirm('Tem certeza que deseja excluir este site? Esta ação não pode ser desfeita.');">
                                        <i class="fas fa-trash mr-1"></i>Excluir
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
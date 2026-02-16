<?php
require_once 'config.php';

$website_id = intval($_GET['website_id'] ?? 0);
$post_id = intval($_GET['post_id'] ?? 0);

if (!$website_id || !$post_id) {
    header('Location: index.php');
    exit;
}

// Obter post
$stmt = $pdo->prepare("SELECT p.*, w.nome as site_nome, w.tipo FROM blog_posts p JOIN websites w ON p.website_id = w.id WHERE p.id = ? AND p.website_id = ? AND p.status = 'publicado'");
$stmt->execute([$post_id, $website_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header('Location: index.php');
    exit;
}

// Incrementar views
$stmt = $pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?");
$stmt->execute([$post_id]);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['titulo']); ?> - <?php echo htmlspecialchars($post['site_nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .post-content {
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }
        .post-content img {
            max-width: 100%;
            height: auto;
            margin: 20px 0;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <a href="website.php?id=<?php echo $website_id; ?>" class="inline-block mb-4 text-blue-600 hover:underline">
            <i class="fas fa-arrow-left mr-2"></i>Voltar para o site
        </a>
        
        <article class="bg-white p-8 rounded-lg shadow-md">
            <?php if ($post['imagem']): ?>
                <img src="<?php echo htmlspecialchars($post['imagem']); ?>" alt="" class="w-full h-64 object-cover rounded-lg mb-6">
            <?php endif; ?>
            
            <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($post['titulo']); ?></h1>
            
            <div class="text-gray-600 mb-6">
                Publicado em <?php echo date('d/m/Y H:i', strtotime($post['data_publicacao'])); ?> • 
                <?php echo $post['views']; ?> visualizações
            </div>
            
            <?php if ($post['resumo']): ?>
                <div class="text-lg text-gray-700 italic mb-6 border-l-4 border-blue-500 pl-4">
                    <?php echo nl2br(htmlspecialchars($post['resumo'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="post-content">
                <?php echo $post['conteudo']; ?>
            </div>
        </article>
    </div>
</body>
</html>
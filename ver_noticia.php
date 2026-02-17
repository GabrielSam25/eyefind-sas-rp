<?php
require_once 'config.php';

$website_id = intval($_GET['website_id'] ?? 0);
$noticia_id = intval($_GET['noticia_id'] ?? 0);

if (!$website_id || !$noticia_id) {
    header('Location: index.php');
    exit;
}

// Obter notícia
$stmt = $pdo->prepare("SELECT n.*, w.nome as site_nome, w.tipo FROM noticias_artigos n JOIN websites w ON n.website_id = w.id WHERE n.id = ? AND n.website_id = ? AND n.status = 'publicado'");
$stmt->execute([$noticia_id, $website_id]);
$noticia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$noticia) {
    header('Location: index.php');
    exit;
}

// Incrementar views
$stmt = $pdo->prepare("UPDATE noticias_artigos SET views = views + 1 WHERE id = ?");
$stmt->execute([$noticia_id]);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($noticia['titulo']); ?> - <?php echo htmlspecialchars($noticia['site_nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .noticia-content {
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.8;
        }
        .noticia-content img {
            max-width: 100%;
            height: auto;
            margin: 20px 0;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <a href="website.php?id=<?php echo $website_id; ?>" class="inline-block mb-4 text-red-600 hover:underline">
            <i class="fas fa-arrow-left mr-2"></i>Voltar para o portal
        </a>
        
        <article class="bg-white p-8 rounded-lg shadow-md">
            <?php if ($noticia['imagem']): ?>
                <img src="<?php echo htmlspecialchars($noticia['imagem']); ?>" alt="" class="w-full h-80 object-cover rounded-lg mb-6">
            <?php endif; ?>
            
            <div class="flex items-center gap-2 mb-4">
                <?php if ($noticia['destaque']): ?>
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded">DESTAQUE</span>
                <?php endif; ?>
                <?php if ($noticia['categoria']): ?>
                    <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded"><?php echo htmlspecialchars($noticia['categoria']); ?></span>
                <?php endif; ?>
            </div>
            
            <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($noticia['titulo']); ?></h1>
            
            <div class="text-gray-600 mb-6">
                Publicado em <?php echo date('d/m/Y H:i', strtotime($noticia['data_publicacao'])); ?> • 
                <?php echo $noticia['views']; ?> visualizações
            </div>
            
            <?php if ($noticia['resumo']): ?>
                <div class="text-lg text-gray-700 italic mb-6 border-l-4 border-red-500 pl-4">
                    <?php echo nl2br(htmlspecialchars($noticia['resumo'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="noticia-content">
                <?php echo $noticia['conteudo']; ?>
            </div>
        </article>
    </div>
</body>
</html>
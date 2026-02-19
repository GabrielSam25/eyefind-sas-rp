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

// Buscar template personalizado
$stmt = $pdo->prepare("SELECT html, css FROM templates_visualizacao WHERE website_id = ? AND tipo = 'post'");
$stmt->execute([$website_id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

// Template padrão caso não exista personalizado
if (!$template) {
    $template['html'] = '<article class="max-w-3xl mx-auto p-6">
    <h1 class="text-4xl font-bold mb-4 dynamic-titulo"></h1>
    <div class="flex items-center text-gray-500 mb-6">
        <span class="dynamic-autor-nome"></span> • 
        <span class="dynamic-data ml-2"></span> • 
        <span class="dynamic-views ml-2"></span> visualizações
    </div>
    <img class="w-full h-96 object-cover rounded-lg mb-8 dynamic-imagem" src="">
    <div class="prose max-w-none dynamic-conteudo"></div>
</article>';
    $template['css'] = '.prose { font-size: 1.125rem; line-height: 1.8; }';
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['titulo']); ?> - <?php echo htmlspecialchars($post['site_nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if (!empty($template['css'])): ?>
        <style><?php echo $template['css']; ?></style>
    <?php endif; ?>
    <style>
        .dynamic-conteudo img { max-width: 100%; height: auto; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <a href="website.php?id=<?php echo $website_id; ?>" class="inline-block mb-4 text-blue-600 hover:underline">
            <i class="fas fa-arrow-left mr-2"></i>Voltar para o site
        </a>
        
        <?php
        // Usar DOMDocument para processar o template
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $template['html'], LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        // Preparar dados para o template
        $dados = [
            'titulo' => $post['titulo'],
            'conteudo' => $post['conteudo'],
            'resumo' => $post['resumo'] ?? '',
            'imagem' => $post['imagem'] ?? '',
            'data_publicacao' => $post['data_publicacao'],
            'autor_id' => $post['autor_id'] ?? null,
            'views' => $post['views'],
            'id' => $post['id']
        ];
        
        // Preencher com os dados do post
        $xpath = new DOMXPath($dom);
        $elementos = $xpath->query("//*[contains(@class, 'dynamic-')]");
        
        foreach ($elementos as $el) {
            preencherElementoComDados($el, $dados, $pdo, $website_id);
        }
        
        echo $dom->saveHTML();
        ?>
    </div>
</body>
</html>
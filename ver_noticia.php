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

// Buscar template personalizado
$stmt = $pdo->prepare("SELECT html, css FROM templates_visualizacao WHERE website_id = ? AND tipo = 'noticia'");
$stmt->execute([$website_id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

// Template padrão caso não exista personalizado
if (!$template) {
    $template['html'] = '<article class="max-w-4xl mx-auto p-6">
    <h1 class="text-4xl font-bold mb-4 dynamic-titulo"></h1>
    <div class="flex items-center text-gray-500 mb-6">
        <span class="dynamic-autor-nome"></span> • 
        <span class="dynamic-data ml-2"></span> • 
        <span class="dynamic-views ml-2"></span> visualizações
    </div>
    <img class="w-full h-96 object-cover rounded-lg mb-8 dynamic-imagem" src="">
    <div class="dynamic-conteudo"></div>
</article>';
    $template['css'] = '';
}

// Aplicar CSS do template
$css = $template['css'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($noticia['titulo']); ?> - <?php echo htmlspecialchars($noticia['site_nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if ($css): ?>
        <style><?php echo $css; ?></style>
    <?php endif; ?>
</head>
<body>
    <?php
    // Usar DOMDocument para processar o template
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $template['html'], LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    // Preencher com os dados da notícia
    $xpath = new DOMXPath($dom);
    $elementos = $xpath->query("//*[contains(@class, 'dynamic-')]");
    
    foreach ($elementos as $el) {
        preencherElementoComDados($el, $noticia, $pdo, $website_id);
    }
    
    echo $dom->saveHTML();
    ?>
</body>
</html>
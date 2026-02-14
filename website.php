<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$website_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = :id");
$stmt->execute([':id' => $website_id]);
$website = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$website) {
    header('Location: index.php');
    exit;
}

function sanitize_css($css)
{
    // Remove any potentially harmful CSS
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    $css = preg_replace('/@import\s+[^;]*;/', '', $css);
    return $css;
}

// Processar blocos dinâmicos no conteúdo
$processedContent = processDynamicBlocks($pdo, $website['conteudo']);

$sanitized_css = sanitize_css($website['css_personalizado'] ?? '');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($website['nome']); ?></title>
    
    <!-- Tailwind CSS (opcional, para estilização básica) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 100%; min-height: 100vh; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .website-container { width: 100%; min-height: 100vh; }
        
        /* Estilos padrão para blocos dinâmicos */
        .dynamic-news { display: flex; flex-wrap: wrap; gap: 20px; padding: 20px; }
        .news-item { flex: 1 1 300px; border: 1px solid #eaeaea; border-radius: 8px; overflow: hidden; }
        .news-image { width: 100%; height: 200px; object-fit: cover; }
        .news-title { font-size: 1.2rem; padding: 10px; margin: 0; }
        .news-meta { font-size: 0.9rem; color: #666; padding: 0 10px; }
        .news-excerpt { padding: 10px; color: #333; }
        
        .dynamic-bleets { padding: 20px; }
        .bleet-item { border-bottom: 1px solid #eee; padding: 10px 0; }
        .bleet-meta { font-size: 0.85rem; color: #777; }
        
        .dynamic-quote { text-align: center; padding: 30px; background: #f9f9f9; border-radius: 10px; }
        .quote-text { font-size: 1.5rem; font-style: italic; margin-bottom: 10px; }
        .quote-author { font-size: 1rem; color: #555; }
        
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; padding: 20px; }
        .product-item { border: 1px solid #ddd; border-radius: 8px; padding: 15px; text-align: center; }
        .product-image { width: 100%; height: 150px; object-fit: cover; border-radius: 5px; }
        .product-name { font-size: 1.1rem; margin: 10px 0; }
        .product-price { font-size: 1.2rem; font-weight: bold; color: #067191; margin: 5px 0; }
        .product-buy { background: #067191; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; }
        
        .dynamic-counter { text-align: center; padding: 20px; }
        .dynamic-counter span { font-size: 2rem; display: inline-block; margin: 0 15px; }
        .dynamic-counter button { background: #067191; color: white; border: none; width: 40px; height: 40px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; margin: 0 5px; }
    </style>
    
    <?php if (!empty($website['css'])): ?>
        <style><?php echo $website['css']; ?></style>
    <?php endif; ?>
    
    <?php if (!empty($sanitized_css)): ?>
        <style><?php echo $sanitized_css; ?></style>
    <?php endif; ?>
</head>
<body>
    <div class="website-container">
        <?php echo $processedContent; ?>
    </div>

    <script>
        // Re-executar scripts inline que possam ter sido inseridos
        document.querySelectorAll('script:not([src])').forEach(el => {
            if (!el.src) {
                const newScript = document.createElement('script');
                newScript.text = el.innerHTML;
                document.body.appendChild(newScript);
            }
        });
    </script>
</body>
</html>
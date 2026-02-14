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

// Processar conteúdo dinâmico substituindo {{marcadores}}
$processedContent = processDynamicContent($pdo, $website['conteudo']);

function sanitize_css($css)
{
    // Remove comments and potentially harmful CSS
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    $css = preg_replace('/@import\s+[^;]*;/', '', $css);
    return $css;
}

$sanitized_css = sanitize_css($website['css_personalizado'] ?? '');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($website['nome']); ?></title>
    
    <!-- Tailwind CSS (opcional) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        
        /* Estilos para conteúdo dinâmico */
        .dynamic-noticias {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding: 15px;
        }
        .noticia-item {
            flex: 1 1 300px;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            overflow: hidden;
        }
        .noticia-imagem {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .noticia-titulo {
            font-size: 1.2rem;
            padding: 10px;
            margin: 0;
        }
        .noticia-meta {
            font-size: 0.85rem;
            color: #666;
            padding: 0 10px 10px;
        }
        .noticia-resumo {
            padding: 0 10px 15px;
            color: #333;
        }
        
        .dynamic-bleets {
            padding: 15px;
        }
        .bleet-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .bleet-conteudo {
            font-size: 1rem;
            margin-bottom: 5px;
        }
        .bleet-meta {
            font-size: 0.8rem;
            color: #777;
        }
        
        .dynamic-citacao {
            text-align: center;
            padding: 30px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .citacao-texto {
            font-size: 1.3rem;
            font-style: italic;
            margin-bottom: 10px;
        }
        .citacao-autor {
            font-size: 1rem;
            color: #555;
        }
        
        .grade-produtos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .produto-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .produto-imagem {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
        }
        .produto-nome {
            font-size: 1rem;
            margin: 10px 0 5px;
        }
        .produto-preco {
            font-size: 1.2rem;
            font-weight: bold;
            color: #067191;
            margin: 5px 0;
        }
        .produto-botao {
            background: #067191;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .dynamic-contador {
            text-align: center;
            padding: 20px;
        }
        .contador-valor {
            font-size: 2.5rem;
            display: block;
            margin: 10px 0;
        }
        .contador-botoes button {
            background: #067191;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.5rem;
            margin: 0 5px;
            cursor: pointer;
        }
        
        .dynamic-usuario {
            padding: 15px;
            background: #f0f9ff;
            border-left: 4px solid #067191;
            border-radius: 4px;
        }
    </style>
    
    <?php if (!empty($website['css'])): ?>
        <style><?php echo $website['css']; ?></style>
    <?php endif; ?>
    
    <?php if (!empty($sanitized_css)): ?>
        <style><?php echo $sanitized_css; ?></style>
    <?php endif; ?>
</head>
<body>
    <div style="width: 100%; min-height: 100vh;">
        <?php echo $processedContent; ?>
    </div>

    <script>
        // Re-executar scripts inline
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
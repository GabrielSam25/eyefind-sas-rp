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

// Carregar áreas dinâmicas
$stmt = $pdo->prepare("SELECT element_id, content_data FROM dynamic_areas WHERE website_id = ?");
$stmt->execute([$website_id]);
$dynamicAreas = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dynamicAreas[$row['element_id']] = json_decode($row['content_data'], true);
}

// Função para renderizar uma área dinâmica (layout padrão)
function renderDynamicArea($items) {
    if (empty($items)) return '';
    
    $html = '<div class="dynamic-area grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4">';
    foreach ($items as $item) {
        $html .= '<div class="dynamic-item bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">';
        
        if (!empty($item['image'])) {
            $html .= '<img src="' . htmlspecialchars($item['image']) . '" alt="' . htmlspecialchars($item['title'] ?? '') . '" class="w-full h-48 object-cover">';
        }
        
        $html .= '<div class="p-4">';
        
        if (!empty($item['title'])) {
            $html .= '<h3 class="text-xl font-bold text-gray-800 mb-2">' . htmlspecialchars($item['title']) . '</h3>';
        }
        
        if (!empty($item['text'])) {
            $html .= '<p class="text-gray-600 mb-3">' . nl2br(htmlspecialchars($item['text'])) . '</p>';
        }
        
        if (!empty($item['price'])) {
            $html .= '<p class="text-2xl font-bold text-green-600 mt-2">R$ ' . number_format(floatval($item['price']), 2, ',', '.') . '</p>';
        }
        
        // Se tiver data
        if (!empty($item['date'])) {
            $html .= '<p class="text-sm text-gray-500 mt-2">' . date('d/m/Y', strtotime($item['date'])) . '</p>';
        }
        
        // Se tiver botão
        if (!empty($item['button_text']) && !empty($item['button_link'])) {
            $html .= '<a href="' . htmlspecialchars($item['button_link']) . '" class="inline-block mt-3 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">' . htmlspecialchars($item['button_text']) . '</a>';
        }
        
        $html .= '</div></div>';
    }
    $html .= '</div>';
    
    // Se não houver itens, não mostra nada
    if (empty($items)) {
        $html = '<div class="dynamic-area-empty p-8 text-center text-gray-500 bg-gray-50 rounded-lg">Nenhum item cadastrado nesta área.</div>';
    }
    
    return $html;
}

// Substituir placeholders no HTML
$html = $website['conteudo'];

// Verificar se há áreas dinâmicas para processar
if (!empty($dynamicAreas)) {
    // Usar DOMDocument para manipular com segurança
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // Ignorar warnings de HTML malformado
    
    // Carregar o HTML preservando a estrutura
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//*[@data-dynamic-area]");

    foreach ($nodes as $node) {
        $elementId = $node->getAttribute('data-dynamic-area');
        
        // Verificar se existe conteúdo para este elemento
        if (isset($dynamicAreas[$elementId])) {
            $items = $dynamicAreas[$elementId];
            
            // Verificar se o elemento tem classes específicas para diferentes layouts
            $class = $node->getAttribute('class');
            
            // Renderizar o conteúdo dinâmico baseado na classe ou tipo
            $rendered = renderDynamicArea($items);
            
            // Criar um fragmento com o novo HTML
            $fragment = $dom->createDocumentFragment();
            
            // Adicionar o HTML renderizado ao fragmento
            $fragment->appendXML($rendered);
            
            // Substituir o nó original pelo fragmento
            $node->parentNode->replaceChild($fragment, $node);
        }
    }

    // Salvar o HTML modificado
    $html = $dom->saveHTML();
}

function sanitize_css($css)
{
    // Remove any header-related CSS
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    $css = preg_replace('/#eyefind-header[^{]*{[^}]*}/', '', $css);
    $css = preg_replace('/header[^{]*{[^}]*}/', '', $css);
    $css = preg_replace('/\.eyefind-header[^{]*{[^}]*}/', '', $css);
    $css = preg_replace('/@import\s+[^;]*;/', '', $css);
    $css = preg_replace('/url\s*\([^)]*\)/', '', $css);
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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow-x: hidden;
        }

        /* Reset some common elements that might affect full-width display */
        body, div, section, article, header, footer, nav, aside {
            box-sizing: border-box;
        }

        /* Make sure the website content takes full width and height */
        .website-container {
            width: 100%;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        /* Estilos para áreas dinâmicas */
        .dynamic-area {
            width: 100%;
            max-width: 100%;
        }
        
        .dynamic-item {
            transition: transform 0.2s ease-in-out;
        }
        
        .dynamic-item:hover {
            transform: translateY(-2px);
        }
        
        /* Layouts alternativos (podem ser ativados por classes) */
        .dynamic-area.list-layout {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .dynamic-area.list-layout .dynamic-item {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 1rem;
        }
        
        .dynamic-area.list-layout .dynamic-item img {
            width: 120px;
            height: 120px;
            object-fit: cover;
        }
        
        @media (max-width: 768px) {
            .dynamic-area.list-layout .dynamic-item {
                flex-direction: column;
            }
            
            .dynamic-area.list-layout .dynamic-item img {
                width: 100%;
                height: 200px;
            }
        }
    </style>
    
    <?php if (!empty($website['css_personalizado'])): ?>
        <style>
            <?php echo $sanitized_css; ?>
        </style>
    <?php endif; ?>
    
    <?php if (!empty($website['css'])): ?>
        <style>
            <?php echo $website['css']; ?>
        </style>
    <?php endif; ?>
</head>
<body>
    <div class="website-container">
        <?php echo $html; ?>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Execute any scripts in the content
            document.querySelectorAll('script').forEach((el) => {
                if (!el.src) { // Only execute inline scripts
                    try {
                        const newScript = document.createElement("script");
                        newScript.text = el.innerHTML;
                        document.body.appendChild(newScript);
                    } catch (e) {
                        console.error('Erro ao executar script:', e);
                    }
                }
            });

            // Remove any residual Eyefind elements if they exist
            const eyefindElements = document.querySelectorAll('#eyefind-header, .eyefind-header');
            eyefindElements.forEach(el => el.remove());

            // Make sure the body takes full width
            document.body.style.width = '100%';
            document.body.style.margin = '0';
            document.body.style.padding = '0';

            // Ensure the website container fills the viewport
            const container = document.querySelector('.website-container');
            if (container) {
                container.style.minHeight = '100vh';
                container.style.width = '100%';
            }

            // Adicionar funcionalidade de clique nos itens dinâmicos se tiver link
            document.querySelectorAll('.dynamic-item').forEach(item => {
                const button = item.querySelector('a');
                if (button && button.getAttribute('href')) {
                    item.style.cursor = 'pointer';
                    item.addEventListener('click', (e) => {
                        if (!e.target.closest('a')) {
                            window.location.href = button.getAttribute('href');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
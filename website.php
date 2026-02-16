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

// Verifica permissão: se não estiver aprovado, só admin pode ver
if ($website['status'] !== 'approved') {
    $usuario = isLogado() ? getUsuarioAtual($pdo) : null;
    if (!$usuario || $usuario['is_admin'] != 1) {
        header('Location: index.php');
        exit;
    }
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

$sanitized_css = sanitize_css($website['css_personalizado']);

$htmlFinal = renderDynamicBlocks($website['conteudo'], $website['id'], $pdo);
echo $htmlFinal;
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
        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow-x: hidden;
        }

        /* Reset some common elements that might affect full-width display */
        body,
        div,
        section,
        article,
        header,
        footer,
        nav,
        aside {
            box-sizing: border-box;
        }

        /* Make sure the website content takes full width and height */
        .website-container {
            width: 100%;
            min-height: 100vh;
            margin: 0;
            padding: 0;
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
        <?php echo renderDynamicBlocks($website['conteudo'], $website['id'], $pdo); ?>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Execute any scripts in the content
            document.querySelectorAll('script').forEach((el) => {
                if (!el.src) { // Only execute inline scripts
                    const newScript = document.createElement("script");
                    newScript.text = el.innerHTML;
                    document.body.appendChild(newScript);
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
        });
    </script>
</body>

</html>

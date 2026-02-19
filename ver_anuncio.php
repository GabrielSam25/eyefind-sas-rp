<?php
require_once 'config.php';

$website_id = intval($_GET['website_id'] ?? 0);
$anuncio_id = intval($_GET['anuncio_id'] ?? 0);

if (!$website_id || !$anuncio_id) {
    header('Location: index.php');
    exit;
}

// Obter anúncio
$stmt = $pdo->prepare("SELECT a.*, w.nome as site_nome, w.tipo FROM classificados_anuncios a JOIN websites w ON a.website_id = w.id WHERE a.id = ? AND a.website_id = ? AND a.status = 'ativo'");
$stmt->execute([$anuncio_id, $website_id]);
$anuncio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$anuncio) {
    header('Location: index.php');
    exit;
}

// Incrementar views
$stmt = $pdo->prepare("UPDATE classificados_anuncios SET views = views + 1 WHERE id = ?");
$stmt->execute([$anuncio_id]);

// Buscar template personalizado
$stmt = $pdo->prepare("SELECT html, css FROM templates_visualizacao WHERE website_id = ? AND tipo = 'anuncio'");
$stmt->execute([$website_id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

// Template padrão caso não exista personalizado
if (!$template) {
    $template['html'] = '<div class="max-w-4xl mx-auto p-6">
    <div class="grid md:grid-cols-2 gap-8">
        <div>
            <img class="w-full rounded-lg shadow-lg dynamic-imagem" src="">
        </div>
        <div>
            <h1 class="text-4xl font-bold mb-4 dynamic-titulo"></h1>
            <p class="text-3xl text-green-600 font-bold mb-4 dynamic-preco"></p>
            <div class="space-y-3 mb-6">
                <p><span class="font-bold">Categoria:</span> <span class="dynamic-categoria"></span></p>
                <p><span class="font-bold">Contato:</span> <span class="dynamic-contato"></span></p>
                <p><span class="font-bold">Telefone:</span> <span class="dynamic-telefone"></span></p>
                <p><span class="font-bold">Email:</span> <a class="dynamic-email text-blue-600" href=""></a></p>
                <p><span class="font-bold">Visualizações:</span> <span class="dynamic-views"></span></p>
                <p><span class="font-bold">Anunciado em:</span> <span class="dynamic-data"></span></p>
            </div>
            <div class="border-t pt-6">
                <h2 class="text-xl font-bold mb-4">Descrição</h2>
                <p class="text-gray-700 dynamic-conteudo"></p>
            </div>
        </div>
    </div>
</div>';
    $template['css'] = '';
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($anuncio['titulo']); ?> - <?php echo htmlspecialchars($anuncio['site_nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if (!empty($template['css'])): ?>
        <style><?php echo $template['css']; ?></style>
    <?php endif; ?>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <a href="website.php?id=<?php echo $website_id; ?>" class="inline-block mb-4 text-purple-600 hover:underline">
            <i class="fas fa-arrow-left mr-2"></i>Voltar para os classificados
        </a>
        
        <?php
        // Usar DOMDocument para processar o template
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $template['html'], LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        // Preparar dados para o template
        $dados = [
            'titulo' => $anuncio['titulo'],
            'conteudo' => $anuncio['descricao'],
            'imagem' => $anuncio['imagem'] ?? '',
            'preco' => $anuncio['preco'] ?? null,
            'categoria' => $anuncio['categoria'] ?? '',
            'contato' => $anuncio['contato'] ?? '',
            'telefone' => $anuncio['telefone'] ?? '',
            'email' => $anuncio['email'] ?? '',
            'data_criacao' => $anuncio['data_criacao'],
            'views' => $anuncio['views'],
            'id' => $anuncio['id']
        ];
        
        // Preencher com os dados do anúncio
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
<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: manage_blogs.php');
    exit;
}

$block_id = intval($_GET['id']);

// Buscar bloco e verificar permissão
$usuario = getUsuarioAtual($pdo);
$stmt = $pdo->prepare("
    SELECT db.* FROM dynamic_blocks db
    JOIN websites w ON db.website_id = w.id
    WHERE db.id = ? AND w.usuario_id = ?
");
$stmt->execute([$block_id, $usuario['id']]);
$block = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$block) {
    header('Location: manage_blogs.php');
    exit;
}

$block_data = json_decode($block['block_data'], true);

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $block_type = $block['block_type'];
    $new_data = [];
    
    // Estrutura dinâmica baseada no tipo
    switch ($block_type) {
        case 'news_list':
            $items = [];
            $titles = $_POST['title'] ?? [];
            $images = $_POST['image'] ?? [];
            $contents = $_POST['content'] ?? [];
            
            for ($i = 0; $i < count($titles); $i++) {
                if (!empty($titles[$i])) {
                    $items[] = [
                        'title' => $titles[$i],
                        'image' => $images[$i] ?? '',
                        'content' => $contents[$i] ?? '',
                        'date' => date('Y-m-d H:i:s')
                    ];
                }
            }
            $new_data['items'] = $items;
            break;
            
        case 'product_grid':
            $products = [];
            $names = $_POST['name'] ?? [];
            $prices = $_POST['price'] ?? [];
            $images = $_POST['image'] ?? [];
            $descriptions = $_POST['description'] ?? [];
            
            for ($i = 0; $i < count($names); $i++) {
                if (!empty($names[$i])) {
                    $products[] = [
                        'name' => $names[$i],
                        'price' => floatval($prices[$i] ?? 0),
                        'image' => $images[$i] ?? '',
                        'description' => $descriptions[$i] ?? ''
                    ];
                }
            }
            $new_data['products'] = $products;
            break;
            
        default: // custom block
            $new_data = $_POST;
    }
    
    $stmt = $pdo->prepare("UPDATE dynamic_blocks SET block_data = ? WHERE id = ?");
    $stmt->execute([json_encode($new_data), $block_id]);
    
    header('Location: edit_blog.php?id=' . $block['website_id']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Bloco Dinâmico</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto py-8">
        <div class="bg-white p-6 rounded shadow">
            <h1 class="text-2xl font-bold mb-6">Editar Bloco: <?php echo ucfirst(str_replace('_', ' ', $block['block_type'])); ?></h1>
            
            <form method="POST">
                <?php if ($block['block_type'] == 'news_list'): ?>
                    <!-- Formulário para lista de notícias -->
                    <div id="items-container">
                        <?php if (!empty($block_data['items'])): ?>
                            <?php foreach ($block_data['items'] as $index => $item): ?>
                                <div class="item border p-4 mb-4 rounded">
                                    <h3 class="font-bold mb-2">Notícia <?php echo $index + 1; ?></h3>
                                    <div class="mb-2">
                                        <label class="block text-sm">Título</label>
                                        <input type="text" name="title[]" value="<?php echo htmlspecialchars($item['title']); ?>" class="w-full border p-2 rounded">
                                    </div>
                                    <div class="mb-2">
                                        <label class="block text-sm">URL da Imagem</label>
                                        <input type="url" name="image[]" value="<?php echo htmlspecialchars($item['image']); ?>" class="w-full border p-2 rounded">
                                    </div>
                                    <div class="mb-2">
                                        <label class="block text-sm">Conteúdo</label>
                                        <textarea name="content[]" class="w-full border p-2 rounded"><?php echo htmlspecialchars($item['content']); ?></textarea>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="item border p-4 mb-4 rounded">
                                <h3 class="font-bold mb-2">Notícia 1</h3>
                                <div class="mb-2">
                                    <label class="block text-sm">Título</label>
                                    <input type="text" name="title[]" class="w-full border p-2 rounded">
                                </div>
                                <div class="mb-2">
                                    <label class="block text-sm">URL da Imagem</label>
                                    <input type="url" name="image[]" class="w-full border p-2 rounded">
                                </div>
                                <div class="mb-2">
                                    <label class="block text-sm">Conteúdo</label>
                                    <textarea name="content[]" class="w-full border p-2 rounded"></textarea>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" onclick="addNewsItem()" class="bg-green-500 text-white px-4 py-2 rounded mb-4">
                        + Adicionar Notícia
                    </button>
                    
                <?php elseif ($block['block_type'] == 'product_grid'): ?>
                    <!-- Formulário para grade de produtos -->
                    <div id="products-container">
                        <?php if (!empty($block_data['products'])): ?>
                            <?php foreach ($block_data['products'] as $index => $product): ?>
                                <div class="product border p-4 mb-4 rounded">
                                    <h3 class="font-bold mb-2">Produto <?php echo $index + 1; ?></h3>
                                    <div class="mb-2">
                                        <label class="block text-sm">Nome</label>
                                        <input type="text" name="name[]" value="<?php echo htmlspecialchars($product['name']); ?>" class="w-full border p-2 rounded">
                                    </div>
                                    <div class="mb-2">
                                        <label class="block text-sm">Preço</label>
                                        <input type="number" step="0.01" name="price[]" value="<?php echo $product['price']; ?>" class="w-full border p-2 rounded">
                                    </div>
                                    <div class="mb-2">
                                        <label class="block text-sm">URL da Imagem</label>
                                        <input type="url" name="image[]" value="<?php echo htmlspecialchars($product['image']); ?>" class="w-full border p-2 rounded">
                                    </div>
                                    <div class="mb-2">
                                        <label class="block text-sm">Descrição</label>
                                        <textarea name="description[]" class="w-full border p-2 rounded"><?php echo htmlspecialchars($product['description']); ?></textarea>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="product border p-4 mb-4 rounded">
                                <h3 class="font-bold mb-2">Produto 1</h3>
                                <div class="mb-2">
                                    <label class="block text-sm">Nome</label>
                                    <input type="text" name="name[]" class="w-full border p-2 rounded">
                                </div>
                                <div class="mb-2">
                                    <label class="block text-sm">Preço</label>
                                    <input type="number" step="0.01" name="price[]" class="w-full border p-2 rounded">
                                </div>
                                <div class="mb-2">
                                    <label class="block text-sm">URL da Imagem</label>
                                    <input type="url" name="image[]" class="w-full border p-2 rounded">
                                </div>
                                <div class="mb-2">
                                    <label class="block text-sm">Descrição</label>
                                    <textarea name="description[]" class="w-full border p-2 rounded"></textarea>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" onclick="addProductItem()" class="bg-green-500 text-white px-4 py-2 rounded mb-4">
                        + Adicionar Produto
                    </button>
                <?php endif; ?>
                
                <div class="flex justify-between mt-6">
                    <a href="edit_blog.php?id=<?php echo $block['website_id']; ?>" class="bg-gray-500 text-white px-6 py-2 rounded">
                        Voltar
                    </a>
                    <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded">
                        Salvar Bloco
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function addNewsItem() {
            const container = document.getElementById('items-container');
            const index = container.children.length + 1;
            const html = `
                <div class="item border p-4 mb-4 rounded">
                    <h3 class="font-bold mb-2">Notícia ${index}</h3>
                    <div class="mb-2">
                        <label class="block text-sm">Título</label>
                        <input type="text" name="title[]" class="w-full border p-2 rounded">
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm">URL da Imagem</label>
                        <input type="url" name="image[]" class="w-full border p-2 rounded">
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm">Conteúdo</label>
                        <textarea name="content[]" class="w-full border p-2 rounded"></textarea>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
        
        function addProductItem() {
            const container = document.getElementById('products-container');
            const index = container.children.length + 1;
            const html = `
                <div class="product border p-4 mb-4 rounded">
                    <h3 class="font-bold mb-2">Produto ${index}</h3>
                    <div class="mb-2">
                        <label class="block text-sm">Nome</label>
                        <input type="text" name="name[]" class="w-full border p-2 rounded">
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm">Preço</label>
                        <input type="number" step="0.01" name="price[]" class="w-full border p-2 rounded">
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm">URL da Imagem</label>
                        <input type="url" name="image[]" class="w-full border p-2 rounded">
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm">Descrição</label>
                        <textarea name="description[]" class="w-full border p-2 rounded"></textarea>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
    </script>
</body>
</html>
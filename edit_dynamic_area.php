<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$website_id = intval($_GET['website_id'] ?? 0);
$element_id = $_GET['element_id'] ?? '';

// Se não tiver website_id ou element_id, redirecionar com mensagem
if (!$website_id || !$element_id) {
    header('Location: manage_blogs.php?msg=' . urlencode('Selecione um blog primeiro e depois crie áreas dinâmicas.'));
    exit;
}

// Verificar permissão
$usuario = getUsuarioAtual($pdo);
$stmt = $pdo->prepare("SELECT id, nome FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$website_id, $usuario['id']]);
$website = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$website) {
    header('Location: manage_blogs.php?msg=' . urlencode('Blog não encontrado.'));
    exit;
}

// Buscar área dinâmica
$stmt = $pdo->prepare("SELECT * FROM dynamic_areas WHERE website_id = ? AND element_id = ?");
$stmt->execute([$website_id, $element_id]);
$area = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$area) {
    // Se não existir, criar
    $stmt = $pdo->prepare("INSERT INTO dynamic_areas (website_id, element_id, content_data) VALUES (?, ?, '[]')");
    $stmt->execute([$website_id, $element_id]);
    $area = [
        'id' => $pdo->lastInsertId(),
        'content_data' => '[]'
    ];
}

$content_data = json_decode($area['content_data'], true) ?: [];

// Processar formulário de salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Se clicou em "Salvar e Voltar"
    if (isset($_POST['save_and_return'])) {
        // Processa os dados primeiro
        $items = [];
        $titles = $_POST['title'] ?? [];
        $images = $_POST['image'] ?? [];
        $texts = $_POST['text'] ?? [];
        $prices = $_POST['price'] ?? [];
        $button_texts = $_POST['button_text'] ?? [];
        $button_links = $_POST['button_link'] ?? [];

        for ($i = 0; $i < count($titles); $i++) {
            if (!empty($titles[$i])) {
                $item = [
                    'title' => $titles[$i],
                    'image' => $images[$i] ?? '',
                    'text' => $texts[$i] ?? '',
                    'price' => $prices[$i] ?? '',
                    'button_text' => $button_texts[$i] ?? '',
                    'button_link' => $button_links[$i] ?? ''
                ];
                // Remove campos vazios
                $item = array_filter($item);
                $items[] = $item;
            }
        }

        $stmt = $pdo->prepare("UPDATE dynamic_areas SET content_data = ? WHERE id = ?");
        $stmt->execute([json_encode($items), $area['id']]);
        
        header("Location: edit_blog.php?id=$website_id");
        exit;
    }
    
    // Processamento normal
    $items = [];
    $titles = $_POST['title'] ?? [];
    $images = $_POST['image'] ?? [];
    $texts = $_POST['text'] ?? [];
    $prices = $_POST['price'] ?? [];
    $button_texts = $_POST['button_text'] ?? [];
    $button_links = $_POST['button_link'] ?? [];

    for ($i = 0; $i < count($titles); $i++) {
        if (!empty($titles[$i])) {
            $item = [
                'title' => $titles[$i],
                'image' => $images[$i] ?? '',
                'text' => $texts[$i] ?? '',
                'price' => $prices[$i] ?? '',
                'button_text' => $button_texts[$i] ?? '',
                'button_link' => $button_links[$i] ?? ''
            ];
            // Remove campos vazios
            $item = array_filter($item);
            $items[] = $item;
        }
    }

    $stmt = $pdo->prepare("UPDATE dynamic_areas SET content_data = ? WHERE id = ?");
    $stmt->execute([json_encode($items), $area['id']]);

    header("Location: edit_dynamic_area.php?website_id=$website_id&element_id=$element_id&success=1");
    exit;
}

// Mensagem de sucesso
$success_message = '';
if (isset($_GET['success'])) {
    $success_message = 'Itens salvos com sucesso!';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Área Dinâmica - <?php echo htmlspecialchars($website['nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-6xl mx-auto py-8 px-4">
        <div class="bg-white p-6 rounded shadow">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-blue-600">
                    <i class="fas fa-cog mr-2"></i>
                    Gerenciar Itens da Área Dinâmica
                </h1>
                <div class="text-sm text-gray-500">
                    Blog: <span class="font-bold"><?php echo htmlspecialchars($website['nome']); ?></span>
                </div>
            </div>
            
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            Adicione itens a esta área dinâmica. Cada item pode ter título, imagem, texto, preço e botão.
                        </p>
                    </div>
                </div>
            </div>
            
            <form method="POST">
                <div id="items-container" class="space-y-4">
                    <?php if (empty($content_data)): ?>
                        <!-- Item vazio inicial -->
                        <div class="item border border-gray-300 p-4 mb-4 rounded-lg bg-gray-50">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="font-bold text-gray-700">Item #1</h3>
                                <button type="button" onclick="this.closest('.item').remove()" class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i> Remover
                                </button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold mb-1">Título <span class="text-red-500">*</span></label>
                                    <input type="text" name="title[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" placeholder="Título do item">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold mb-1">Imagem (URL)</label>
                                    <input type="url" name="image[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" placeholder="https://exemplo.com/imagem.jpg">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-bold mb-1">Texto/Descrição</label>
                                    <textarea name="text[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" rows="3" placeholder="Descrição do item..."></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold mb-1">Preço (opcional)</label>
                                    <input type="text" name="price[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" placeholder="R$ 0,00">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold mb-1">Texto do Botão (opcional)</label>
                                    <input type="text" name="button_text[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" placeholder="Saiba mais">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-bold mb-1">Link do Botão (opcional)</label>
                                    <input type="url" name="button_link[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" placeholder="https://...">
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($content_data as $index => $item): ?>
                        <div class="item border border-gray-300 p-4 mb-4 rounded-lg bg-gray-50">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="font-bold text-gray-700">Item #<?php echo $index + 1; ?></h3>
                                <button type="button" onclick="this.closest('.item').remove()" class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i> Remover
                                </button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold mb-1">Título</label>
                                    <input type="text" name="title[]" value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold mb-1">Imagem (URL)</label>
                                    <input type="url" name="image[]" value="<?php echo htmlspecialchars($item['image'] ?? ''); ?>" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-bold mb-1">Texto/Descrição</label>
                                    <textarea name="text[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" rows="3"><?php echo htmlspecialchars($item['text'] ?? ''); ?></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold mb-1">Preço</label>
                                    <input type="text" name="price[]" value="<?php echo htmlspecialchars($item['price'] ?? ''); ?>" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold mb-1">Texto do Botão</label>
                                    <input type="text" name="button_text[]" value="<?php echo htmlspecialchars($item['button_text'] ?? ''); ?>" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-bold mb-1">Link do Botão</label>
                                    <input type="url" name="button_link[]" value="<?php echo htmlspecialchars($item['button_link'] ?? ''); ?>" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="flex gap-2 mt-4">
                    <button type="button" onclick="addItem()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition">
                        <i class="fas fa-plus mr-2"></i>Adicionar Novo Item
                    </button>
                    
                    <button type="button" onclick="addItemPreco()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600 transition">
                        <i class="fas fa-tag mr-2"></i>Adicionar Item com Preço
                    </button>
                    
                    <button type="button" onclick="addItemNoticia()" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                        <i class="fas fa-newspaper mr-2"></i>Adicionar Notícia
                    </button>
                </div>

                <div class="flex justify-between mt-8 pt-4 border-t border-gray-300">
                    <a href="edit_blog.php?id=<?php echo $website_id; ?>" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600 transition">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar para o Blog
                    </a>
                    <div>
                        <button type="submit" name="save_and_return" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600 transition mr-2">
                            <i class="fas fa-save mr-2"></i>Salvar e Voltar
                        </button>
                        <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600 transition">
                            <i class="fas fa-check mr-2"></i>Salvar Itens
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        let itemCounter = <?php echo count($content_data) + 1; ?>;
        
        function addItem() {
            const container = document.getElementById('items-container');
            const html = `
                <div class="item border border-gray-300 p-4 mb-4 rounded-lg bg-gray-50">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-bold text-gray-700">Item #${itemCounter}</h3>
                        <button type="button" onclick="this.closest('.item').remove()" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i> Remover
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold mb-1">Título</label>
                            <input type="text" name="title[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Imagem (URL)</label>
                            <input type="url" name="image[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold mb-1">Texto/Descrição</label>
                            <textarea name="text[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" rows="3"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Preço (opcional)</label>
                            <input type="text" name="price[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Texto do Botão (opcional)</label>
                            <input type="text" name="button_text[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold mb-1">Link do Botão (opcional)</label>
                            <input type="url" name="button_link[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            itemCounter++;
        }
        
        function addItemPreco() {
            const container = document.getElementById('items-container');
            const html = `
                <div class="item border border-gray-300 p-4 mb-4 rounded-lg bg-gray-50">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-bold text-gray-700">Produto #${itemCounter}</h3>
                        <button type="button" onclick="this.closest('.item').remove()" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i> Remover
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold mb-1">Nome do Produto</label>
                            <input type="text" name="title[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Imagem (URL)</label>
                            <input type="url" name="image[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold mb-1">Descrição</label>
                            <textarea name="text[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" rows="3"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Preço</label>
                            <input type="text" name="price[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" placeholder="R$ 0,00">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Texto do Botão</label>
                            <input type="text" name="button_text[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" value="Comprar">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold mb-1">Link do Botão</label>
                            <input type="url" name="button_link[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            itemCounter++;
        }
        
        function addItemNoticia() {
            const container = document.getElementById('items-container');
            const html = `
                <div class="item border border-gray-300 p-4 mb-4 rounded-lg bg-gray-50">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-bold text-gray-700">Notícia #${itemCounter}</h3>
                        <button type="button" onclick="this.closest('.item').remove()" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i> Remover
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold mb-1">Título da Notícia</label>
                            <input type="text" name="title[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Imagem (URL)</label>
                            <input type="url" name="image[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold mb-1">Conteúdo</label>
                            <textarea name="text[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" rows="4"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Texto do Botão</label>
                            <input type="text" name="button_text[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none" value="Leia mais">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Link da Notícia</label>
                            <input type="url" name="button_link[]" class="w-full border border-gray-300 p-2 rounded focus:border-blue-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            itemCounter++;
        }
    </script>
</body>
</html>
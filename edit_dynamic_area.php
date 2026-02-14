<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$website_id = intval($_GET['website_id'] ?? 0);
$element_id = $_GET['element_id'] ?? '';

if (!$website_id || !$element_id) {
    header('Location: manage_blogs.php');
    exit;
}

// Verificar permissão
$usuario = getUsuarioAtual($pdo);
$stmt = $pdo->prepare("SELECT id FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$website_id, $usuario['id']]);
if (!$stmt->fetch()) {
    header('Location: manage_blogs.php');
    exit;
}

// Buscar ou criar área dinâmica
$stmt = $pdo->prepare("SELECT * FROM dynamic_areas WHERE website_id = ? AND element_id = ?");
$stmt->execute([$website_id, $element_id]);
$area = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$area) {
    // Se não existir, criar (mas já deveria ter sido criado no AJAX)
    $stmt = $pdo->prepare("INSERT INTO dynamic_areas (website_id, element_id, content_data) VALUES (?, ?, '[]')");
    $stmt->execute([$website_id, $element_id]);
    $area = ['id' => $pdo->lastInsertId(), 'content_data' => '[]'];
}

$content_data = json_decode($area['content_data'], true) ?: [];

// Processar formulário de salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recebe os dados dos itens
    $items = [];
    $titles = $_POST['title'] ?? [];
    $images = $_POST['image'] ?? [];
    $texts = $_POST['text'] ?? [];
    $prices = $_POST['price'] ?? [];

    for ($i = 0; $i < count($titles); $i++) {
        $item = [
            'title' => $titles[$i],
            'image' => $images[$i] ?? '',
            'text' => $texts[$i] ?? '',
            'price' => $prices[$i] ?? ''
        ];
        // Remove campos vazios
        $item = array_filter($item);
        if (!empty($item)) {
            $items[] = $item;
        }
    }

    $stmt = $pdo->prepare("UPDATE dynamic_areas SET content_data = ? WHERE id = ?");
    $stmt->execute([json_encode($items), $area['id']]);

    header("Location: edit_blog.php?id=$website_id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Área Dinâmica</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto py-8">
        <div class="bg-white p-6 rounded shadow">
            <h1 class="text-2xl font-bold mb-6">Gerenciar Itens da Área Dinâmica</h1>
            <form method="POST">
                <div id="items-container">
                    <?php if (empty($content_data)): ?>
                        <!-- Item vazio inicial -->
                        <div class="item border p-4 mb-4 rounded bg-gray-50">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold mb-1">Título</label>
                                    <input type="text" name="title[]" class="w-full border p-2 rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold mb-1">Imagem (URL)</label>
                                    <input type="url" name="image[]" class="w-full border p-2 rounded">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-bold mb-1">Texto/Descrição</label>
                                    <textarea name="text[]" class="w-full border p-2 rounded"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold mb-1">Preço (opcional)</label>
                                    <input type="text" name="price[]" class="w-full border p-2 rounded">
                                </div>
                            </div>
                            <button type="button" onclick="this.closest('.item').remove()" class="mt-2 text-red-500 text-sm">
                                <i class="fas fa-trash"></i> Remover item
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($content_data as $index => $item): ?>
                        <div class="item border p-4 mb-4 rounded bg-gray-50">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold mb-1">Título</label>
                                    <input type="text" name="title[]" value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" class="w-full border p-2 rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold mb-1">Imagem (URL)</label>
                                    <input type="url" name="image[]" value="<?php echo htmlspecialchars($item['image'] ?? ''); ?>" class="w-full border p-2 rounded">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-bold mb-1">Texto/Descrição</label>
                                    <textarea name="text[]" class="w-full border p-2 rounded"><?php echo htmlspecialchars($item['text'] ?? ''); ?></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold mb-1">Preço (opcional)</label>
                                    <input type="text" name="price[]" value="<?php echo htmlspecialchars($item['price'] ?? ''); ?>" class="w-full border p-2 rounded">
                                </div>
                            </div>
                            <button type="button" onclick="this.closest('.item').remove()" class="mt-2 text-red-500 text-sm">
                                <i class="fas fa-trash"></i> Remover item
                            </button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button type="button" onclick="addItem()" class="bg-green-500 text-white px-4 py-2 rounded mb-4">
                    <i class="fas fa-plus"></i> Adicionar Item
                </button>

                <div class="flex justify-between mt-6">
                    <a href="edit_blog.php?id=<?php echo $website_id; ?>" class="bg-gray-500 text-white px-6 py-2 rounded">
                        Voltar
                    </a>
                    <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded">
                        Salvar Itens
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addItem() {
            const container = document.getElementById('items-container');
            const html = `
                <div class="item border p-4 mb-4 rounded bg-gray-50">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold mb-1">Título</label>
                            <input type="text" name="title[]" class="w-full border p-2 rounded">
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Imagem (URL)</label>
                            <input type="url" name="image[]" class="w-full border p-2 rounded">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold mb-1">Texto/Descrição</label>
                            <textarea name="text[]" class="w-full border p-2 rounded"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Preço (opcional)</label>
                            <input type="text" name="price[]" class="w-full border p-2 rounded">
                        </div>
                    </div>
                    <button type="button" onclick="this.closest('.item').remove()" class="mt-2 text-red-500 text-sm">
                        <i class="fas fa-trash"></i> Remover item
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
    </script>
</body>
</html>
<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$website_id = intval($_GET['website_id'] ?? 0);
if (!$website_id) {
    header('Location: manage_blogs.php');
    exit;
}

$usuario = getUsuarioAtual($pdo);

// Verificar permissão (dono ou admin do site)
$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$website_id, $usuario['id']]);
$website = $stmt->fetch(PDO::FETCH_ASSOC);

$is_dono = !empty($website);
$nivel_colaborador = '';

if (!$is_dono) {
    // Verificar se é colaborador com nível admin/editor
    $stmt = $pdo->prepare("
        SELECT nivel FROM site_colaboradores 
        WHERE website_id = ? AND usuario_id = ? AND nivel IN ('admin', 'editor')
    ");
    $stmt->execute([$website_id, $usuario['id']]);
    $colaborador = $stmt->fetch();
    
    if ($colaborador) {
        $nivel_colaborador = $colaborador['nivel'];
        // Buscar dados do site
        $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
        $stmt->execute([$website_id]);
        $website = $stmt->fetch();
    } else {
        header('Location: manage_blogs.php?msg=sem_permissao');
        exit;
    }
}

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Criar nova categoria
    if (isset($_POST['criar'])) {
        $nome = $_POST['nome'];
        $descricao = $_POST['descricao'] ?? '';
        $cor = $_POST['cor'] ?? '#3B82F6';
        $icone = $_POST['icone'] ?? 'fa-folder';
        $tipo = $_POST['tipo'] ?? 'geral';
        
        // Criar slug
        $slug = criarSlug($nome);
        
        // Verificar se já existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_categorias WHERE website_id = ? AND slug = ?");
        $stmt->execute([$website_id, $slug]);
        if ($stmt->fetchColumn() > 0) {
            $slug .= '-' . time();
        }
        
        // Pegar última ordem
        $stmt = $pdo->prepare("SELECT MAX(ordem) FROM site_categorias WHERE website_id = ?");
        $stmt->execute([$website_id]);
        $ordem = $stmt->fetchColumn() + 1;
        
        $stmt = $pdo->prepare("
            INSERT INTO site_categorias (website_id, nome, slug, descricao, cor, icone, tipo, ordem) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$website_id, $nome, $slug, $descricao, $cor, $icone, $tipo, $ordem]);
        
        header('Location: gerenciar_categorias.php?website_id=' . $website_id . '&msg=criada');
        exit;
    }
    
    // Editar categoria
    if (isset($_POST['editar'])) {
        $categoria_id = $_POST['categoria_id'];
        $nome = $_POST['nome'];
        $descricao = $_POST['descricao'] ?? '';
        $cor = $_POST['cor'] ?? '#3B82F6';
        $icone = $_POST['icone'] ?? 'fa-folder';
        $tipo = $_POST['tipo'] ?? 'geral';
        
        $slug = criarSlug($nome);
        
        $stmt = $pdo->prepare("
            UPDATE site_categorias 
            SET nome = ?, slug = ?, descricao = ?, cor = ?, icone = ?, tipo = ? 
            WHERE id = ? AND website_id = ?
        ");
        $stmt->execute([$nome, $slug, $descricao, $cor, $icone, $tipo, $categoria_id, $website_id]);
        
        header('Location: gerenciar_categorias.php?website_id=' . $website_id . '&msg=editada');
        exit;
    }
    
    // Reordenar categorias
    if (isset($_POST['reordenar'])) {
        $ordens = $_POST['ordem'] ?? [];
        foreach ($ordens as $categoria_id => $ordem) {
            $stmt = $pdo->prepare("UPDATE site_categorias SET ordem = ? WHERE id = ? AND website_id = ?");
            $stmt->execute([$ordem, $categoria_id, $website_id]);
        }
        header('Location: gerenciar_categorias.php?website_id=' . $website_id . '&msg=reordenada');
        exit;
    }
}

// Excluir categoria
if (isset($_GET['excluir'])) {
    $categoria_id = intval($_GET['excluir']);
    
    // Verificar se há notícias usando esta categoria
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM noticias_artigos WHERE website_id = ? AND categoria = ?");
    $stmt->execute([$website_id, $categoria_id]);
    $total_noticias = $stmt->fetchColumn();
    
    if ($total_noticias > 0) {
        $erro = "Não é possível excluir esta categoria pois existem $total_noticias notícias vinculadas a ela.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM site_categorias WHERE id = ? AND website_id = ?");
        $stmt->execute([$categoria_id, $website_id]);
        header('Location: gerenciar_categorias.php?website_id=' . $website_id . '&msg=excluida');
        exit;
    }
}

// Buscar categorias do site
$stmt = $pdo->prepare("
    SELECT * FROM site_categorias 
    WHERE website_id = ? 
    ORDER BY tipo, ordem, nome
");
$stmt->execute([$website_id]);
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por tipo
$categorias_agrupadas = [
    'noticias' => [],
    'blog' => [],
    'classificados' => [],
    'geral' => []
];

foreach ($categorias as $cat) {
    $categorias_agrupadas[$cat['tipo']][] = $cat;
}

// Ícones disponíveis do Font Awesome
$icones_disponiveis = [
    'fa-folder' => 'Pasta',
    'fa-newspaper' => 'Jornal',
    'fa-blog' => 'Blog',
    'fa-tag' => 'Etiqueta',
    'fa-star' => 'Estrela',
    'fa-heart' => 'Coração',
    'fa-fire' => 'Fogo',
    'fa-bolt' => 'Raio',
    'fa-camera' => 'Câmera',
    'fa-music' => 'Música',
    'fa-film' => 'Filme',
    'fa-gamepad' => 'Games',
    'fa-book' => 'Livro',
    'fa-graduation-cap' => 'Educação',
    'fa-flask' => 'Ciência',
    'fa-heartbeat' => 'Saúde',
    'fa-briefcase' => 'Negócios',
    'fa-money-bill' => 'Economia',
    'fa-futbol' => 'Esportes',
    'fa-plane' => 'Viagem'
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Categorias - <?php echo htmlspecialchars($website['nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        .categoria-item {
            cursor: move;
            transition: all 0.2s;
        }
        .categoria-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .categoria-item.dragging {
            opacity: 0.5;
            background: #f3f4f6;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-6xl mx-auto py-8 px-4">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-blue-600">Gerenciar Categorias</h1>
                    <p class="text-gray-600">Site: <?php echo htmlspecialchars($website['nome']); ?></p>
                </div>
                <div class="flex gap-2">
                    <a href="manage_blogs.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </a>
                    <button onclick="abrirModalCriar()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                        <i class="fas fa-plus mr-2"></i>Nova Categoria
                    </button>
                </div>
            </div>

            <!-- Mensagens -->
            <?php if (isset($_GET['msg'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php 
                    if ($_GET['msg'] == 'criada') echo "Categoria criada com sucesso!";
                    if ($_GET['msg'] == 'editada') echo "Categoria editada com sucesso!";
                    if ($_GET['msg'] == 'excluida') echo "Categoria excluída com sucesso!";
                    if ($_GET['msg'] == 'reordenada') echo "Ordem das categorias atualizada!";
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($erro)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $erro; ?>
                </div>
            <?php endif; ?>

            <!-- Lista de categorias por tipo -->
            <form method="POST" id="formReordenar">
                <input type="hidden" name="reordenar" value="1">
                
                <?php foreach (['noticias' => 'Notícias', 'blog' => 'Blog', 'classificados' => 'Classificados', 'geral' => 'Geral'] as $tipo => $titulo): ?>
                    <?php if (!empty($categorias_agrupadas[$tipo])): ?>
                        <div class="mb-8">
                            <h2 class="text-lg font-bold mb-4 border-b pb-2"><?php echo $titulo; ?></h2>
                            <div class="space-y-3 sortable-container" data-tipo="<?php echo $tipo; ?>">
                                <?php foreach ($categorias_agrupadas[$tipo] as $categoria): ?>
                                    <div class="categoria-item bg-gray-50 p-4 rounded-lg border-l-4" 
                                         style="border-left-color: <?php echo $categoria['cor']; ?>"
                                         data-id="<?php echo $categoria['id']; ?>">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-3 flex-1">
                                                <i class="fas fa-grip-vertical text-gray-400 cursor-move"></i>
                                                <i class="fas <?php echo $categoria['icone']; ?>" style="color: <?php echo $categoria['cor']; ?>"></i>
                                                <div>
                                                    <h3 class="font-bold"><?php echo htmlspecialchars($categoria['nome']); ?></h3>
                                                    <?php if ($categoria['descricao']): ?>
                                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($categoria['descricao']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <input type="number" name="ordem[<?php echo $categoria['id']; ?>]" 
                                                       value="<?php echo $categoria['ordem']; ?>" 
                                                       class="w-16 px-2 py-1 border rounded text-center" min="0">
                                                <button type="button" onclick="editarCategoria(<?php echo htmlspecialchars(json_encode($categoria)); ?>)" 
                                                        class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?website_id=<?php echo $website_id; ?>&excluir=<?php echo $categoria['id']; ?>" 
                                                   class="text-red-600 hover:text-red-800"
                                                   onclick="return confirm('Excluir esta categoria? As notícias vinculadas serão movidas para "Sem Categoria".');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (!empty($categorias)): ?>
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                            <i class="fas fa-save mr-2"></i>Salvar Ordem
                        </button>
                    </div>
                <?php endif; ?>
            </form>

            <?php if (empty($categorias)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Nenhuma categoria criada ainda.</p>
                    <button onclick="abrirModalCriar()" class="mt-4 bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                        Criar primeira categoria
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Criar/Editar Categoria -->
    <div id="modalCategoria" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg w-full max-w-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold" id="modalTitulo">Nova Categoria</h2>
                <button onclick="fecharModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <form method="POST" id="formCategoria">
                <input type="hidden" name="categoria_id" id="categoria_id">
                <input type="hidden" name="criar" id="acao_criar" value="1">
                <input type="hidden" name="editar" id="acao_editar" value="">

                <div class="mb-4">
                    <label class="block font-bold mb-2">Nome da Categoria *</label>
                    <input type="text" name="nome" id="categoria_nome" required 
                           class="w-full px-3 py-2 border rounded">
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Descrição (opcional)</label>
                    <textarea name="descricao" id="categoria_descricao" rows="2" 
                              class="w-full px-3 py-2 border rounded"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Tipo de Conteúdo</label>
                    <select name="tipo" id="categoria_tipo" class="w-full px-3 py-2 border rounded">
                        <option value="noticias">Notícias</option>
                        <option value="blog">Blog</option>
                        <option value="classificados">Classificados</option>
                        <option value="geral">Geral</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Cor</label>
                    <input type="color" name="cor" id="categoria_cor" value="#3B82F6" 
                           class="w-full h-10 p-1 border rounded">
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Ícone</label>
                    <select name="icone" id="categoria_icone" class="w-full px-3 py-2 border rounded">
                        <?php foreach ($icones_disponiveis as $valor => $nome): ?>
                            <option value="<?php echo $valor; ?>"><?php echo $nome; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mt-2 text-center">
                        <i class="fas <?php echo $icones_disponiveis ? array_key_first($icones_disponiveis) : 'fa-folder'; ?>" 
                           id="preview_icone" style="font-size: 2rem; color: #3B82F6;"></i>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="fecharModal()" 
                            class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(function() {
            // Torna as listas ordenáveis
            $(".sortable-container").sortable({
                items: '.categoria-item',
                placeholder: "categoria-item dragging",
                opacity: 0.6,
                cursor: 'move',
                update: function(event, ui) {
                    // Atualiza os números de ordem
                    $(this).find('.categoria-item').each(function(index) {
                        $(this).find('input[type="number"]').val(index);
                    });
                }
            });
        });

        function abrirModalCriar() {
            document.getElementById('modalTitulo').textContent = 'Nova Categoria';
            document.getElementById('formCategoria').reset();
            document.getElementById('categoria_id').value = '';
            document.getElementById('acao_criar').value = '1';
            document.getElementById('acao_editar').value = '';
            document.getElementById('modalCategoria').classList.remove('hidden');
            document.getElementById('modalCategoria').classList.add('flex');
        }

        function editarCategoria(categoria) {
            document.getElementById('modalTitulo').textContent = 'Editar Categoria';
            document.getElementById('categoria_id').value = categoria.id;
            document.getElementById('categoria_nome').value = categoria.nome;
            document.getElementById('categoria_descricao').value = categoria.descricao || '';
            document.getElementById('categoria_tipo').value = categoria.tipo;
            document.getElementById('categoria_cor').value = categoria.cor;
            document.getElementById('categoria_icone').value = categoria.icone;
            
            document.getElementById('acao_criar').value = '';
            document.getElementById('acao_editar').value = '1';
            
            document.getElementById('modalCategoria').classList.remove('hidden');
            document.getElementById('modalCategoria').classList.add('flex');
            
            previewIcone();
        }

        function fecharModal() {
            document.getElementById('modalCategoria').classList.add('hidden');
            document.getElementById('modalCategoria').classList.remove('flex');
        }

        function previewIcone() {
            const icone = document.getElementById('categoria_icone').value;
            const cor = document.getElementById('categoria_cor').value;
            const preview = document.getElementById('preview_icone');
            preview.className = 'fas ' + icone;
            preview.style.color = cor;
        }

        document.getElementById('categoria_icone').addEventListener('change', previewIcone);
        document.getElementById('categoria_cor').addEventListener('input', previewIcone);
    </script>
</body>
</html>
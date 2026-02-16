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

// Verificar permissão
$usuario = getUsuarioAtual($pdo);
$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$website_id, $usuario['id']]);
$website = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$website) {
    header('Location: manage_blogs.php');
    exit;
}

// Verificar se é do tipo blog
if ($website['tipo'] != 'blog') {
    header('Location: manage_blogs.php');
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao'])) {
        // Criar/editar post
        if ($_POST['acao'] == 'salvar') {
            $titulo = $_POST['titulo'];
            $conteudo = $_POST['conteudo'];
            $resumo = $_POST['resumo'] ?? substr($conteudo, 0, 150);
            $imagem = $_POST['imagem'] ?? '';
            $status = $_POST['status'] ?? 'rascunho';
            
            $slug = criarSlug($titulo);
            
            if (!empty($_POST['id'])) {
                // Atualizar
                $stmt = $pdo->prepare("UPDATE blog_posts SET titulo = ?, slug = ?, conteudo = ?, resumo = ?, imagem = ?, status = ? WHERE id = ? AND website_id = ?");
                $stmt->execute([$titulo, $slug, $conteudo, $resumo, $imagem, $status, $_POST['id'], $website_id]);
            } else {
                // Inserir
                $stmt = $pdo->prepare("INSERT INTO blog_posts (website_id, titulo, slug, conteudo, resumo, imagem, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$website_id, $titulo, $slug, $conteudo, $resumo, $imagem, $status]);
            }
            
            header('Location: gerenciar_posts.php?website_id=' . $website_id . '&msg=salvo');
            exit;
        }
        
        // Excluir post
        if ($_POST['acao'] == 'excluir' && isset($_POST['id'])) {
            $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ? AND website_id = ?");
            $stmt->execute([$_POST['id'], $website_id]);
            header('Location: gerenciar_posts.php?website_id=' . $website_id . '&msg=excluido');
            exit;
        }
    }
}

// Obter posts
$posts = getBlogPosts($pdo, $website_id, 100);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Posts - <?php echo htmlspecialchars($website['nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-6xl mx-auto py-8 px-4">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-blue-600">Gerenciar Posts</h1>
                    <p class="text-gray-600">Blog: <?php echo htmlspecialchars($website['nome']); ?></p>
                </div>
                <a href="manage_blogs.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
            </div>

            <!-- Mensagens -->
            <?php if (isset($_GET['msg'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php 
                    if ($_GET['msg'] == 'salvo') echo "Post salvo com sucesso!";
                    if ($_GET['msg'] == 'excluido') echo "Post excluído com sucesso!";
                    ?>
                </div>
            <?php endif; ?>

            <!-- Botão novo post -->
            <button onclick="abrirModal()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition mb-6">
                <i class="fas fa-plus mr-2"></i>Novo Post
            </button>

            <!-- Lista de posts -->
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-4 border text-left">ID</th>
                            <th class="py-2 px-4 border text-left">Título</th>
                            <th class="py-2 px-4 border text-left">Status</th>
                            <th class="py-2 px-4 border text-left">Data</th>
                            <th class="py-2 px-4 border text-left">Visualizações</th>
                            <th class="py-2 px-4 border text-left">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                        <tr>
                            <td class="py-2 px-4 border"><?php echo $post['id']; ?></td>
                            <td class="py-2 px-4 border"><?php echo htmlspecialchars($post['titulo']); ?></td>
                            <td class="py-2 px-4 border">
                                <?php if ($post['status'] == 'publicado'): ?>
                                    <span class="text-green-600"><i class="fas fa-check-circle"></i> Publicado</span>
                                <?php else: ?>
                                    <span class="text-yellow-600"><i class="fas fa-clock"></i> Rascunho</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4 border"><?php echo date('d/m/Y H:i', strtotime($post['data_publicacao'])); ?></td>
                            <td class="py-2 px-4 border"><?php echo $post['views']; ?></td>
                            <td class="py-2 px-4 border">
                                <button onclick="editarPost(<?php echo htmlspecialchars(json_encode($post)); ?>)" class="text-blue-600 hover:text-blue-800 mr-2">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Excluir este post?');">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para criar/editar post -->
    <div id="modalPost" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg w-11/12 max-w-4xl max-h-[90vh] overflow-y-auto p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold" id="modalTitulo">Novo Post</h2>
                <button onclick="fecharModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <form method="POST" id="formPost">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" id="post_id">

                <div class="mb-4">
                    <label class="block font-bold mb-2">Título *</label>
                    <input type="text" name="titulo" id="post_titulo" required class="w-full px-3 py-2 border rounded">
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Imagem (URL)</label>
                    <input type="url" name="imagem" id="post_imagem" class="w-full px-3 py-2 border rounded">
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Resumo</label>
                    <textarea name="resumo" id="post_resumo" rows="3" class="w-full px-3 py-2 border rounded"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Conteúdo *</label>
                    <textarea name="conteudo" id="post_conteudo" required class="w-full px-3 py-2 border rounded" rows="10"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Status</label>
                    <select name="status" id="post_status" class="w-full px-3 py-2 border rounded">
                        <option value="rascunho">Rascunho</option>
                        <option value="publicado">Publicado</option>
                    </select>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="fecharModal()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
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
        CKEDITOR.replace('post_conteudo');

        function abrirModal() {
            document.getElementById('modalPost').classList.remove('hidden');
            document.getElementById('modalPost').classList.add('flex');
            document.getElementById('modalTitulo').textContent = 'Novo Post';
            document.getElementById('formPost').reset();
            document.getElementById('post_id').value = '';
        }

        function editarPost(post) {
            document.getElementById('modalPost').classList.remove('hidden');
            document.getElementById('modalPost').classList.add('flex');
            document.getElementById('modalTitulo').textContent = 'Editar Post';
            
            document.getElementById('post_id').value = post.id;
            document.getElementById('post_titulo').value = post.titulo;
            document.getElementById('post_imagem').value = post.imagem || '';
            document.getElementById('post_resumo').value = post.resumo || '';
            CKEDITOR.instances.post_conteudo.setData(post.conteudo);
            document.getElementById('post_status').value = post.status;
        }

        function fecharModal() {
            document.getElementById('modalPost').classList.add('hidden');
            document.getElementById('modalPost').classList.remove('flex');
        }
    </script>
</body>
</html>
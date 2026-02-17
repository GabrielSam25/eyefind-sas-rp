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

// Verificar permissão (dono ou colaborador)
$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$website_id, $usuario['id']]);
$website = $stmt->fetch(PDO::FETCH_ASSOC);

$is_dono = !empty($website);
$nivel_colaborador = '';
$pode_editar_todos = false;
$pode_publicar = false;

if (!$is_dono) {
    // Verificar se é colaborador
    $stmt = $pdo->prepare("
        SELECT nivel FROM site_colaboradores 
        WHERE website_id = ? AND usuario_id = ?
    ");
    $stmt->execute([$website_id, $usuario['id']]);
    $colaborador = $stmt->fetch();
    
    if ($colaborador) {
        $nivel_colaborador = $colaborador['nivel'];
        // Buscar dados do site
        $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
        $stmt->execute([$website_id]);
        $website = $stmt->fetch();
        
        // Definir permissões baseadas no nível
        $pode_editar_todos = in_array($nivel_colaborador, ['admin', 'editor', 'revisor']);
        $pode_publicar = in_array($nivel_colaborador, ['admin', 'editor', 'revisor']);
    } else {
        header('Location: manage_blogs.php?msg=sem_permissao');
        exit;
    }
} else {
    $pode_editar_todos = true;
    $pode_publicar = true;
}

// Verificar se é do tipo blog
if ($website['tipo'] != 'blog') {
    header('Location: manage_blogs.php');
    exit;
}

// Buscar categorias do site
$stmt = $pdo->prepare("
    SELECT * FROM site_categorias 
    WHERE website_id = ? AND (tipo = 'blog' OR tipo = 'geral')
    ORDER BY ordem, nome
");
$stmt->execute([$website_id]);
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao'])) {
        // Criar/editar post
        if ($_POST['acao'] == 'salvar') {
            $titulo = $_POST['titulo'];
            $conteudo = $_POST['conteudo'];
            $resumo = $_POST['resumo'] ?? substr(strip_tags($conteudo), 0, 150);
            $imagem = $_POST['imagem'] ?? '';
            $categoria_id = $_POST['categoria_id'] ?? null;
            $tags = $_POST['tags'] ?? '';
            $status = $_POST['status'] ?? 'rascunho';
            $autor_id = $_POST['autor_id'] ?? $usuario['id'];
            
            // Verificar permissão de publicação
            if ($status == 'publicado' && !$pode_publicar) {
                $status = 'rascunho';
                $erro_publicacao = "Você não tem permissão para publicar posts.";
            }
            
            $slug = criarSlug($titulo);
            
            if (!empty($_POST['id'])) {
                // Verificar se pode editar este post
                $stmt = $pdo->prepare("SELECT autor_id FROM blog_posts WHERE id = ? AND website_id = ?");
                $stmt->execute([$_POST['id'], $website_id]);
                $post_existente = $stmt->fetch();
                
                if ($post_existente && ($post_existente['autor_id'] == $usuario['id'] || $pode_editar_todos)) {
                    // Atualizar
                    $stmt = $pdo->prepare("
                        UPDATE blog_posts 
                        SET titulo = ?, slug = ?, conteudo = ?, resumo = ?, imagem = ?, 
                            categoria_id = ?, tags = ?, status = ?, autor_id = ? 
                        WHERE id = ? AND website_id = ?
                    ");
                    $stmt->execute([$titulo, $slug, $conteudo, $resumo, $imagem, 
                                   $categoria_id, $tags, $status, $autor_id, $_POST['id'], $website_id]);
                } else {
                    $erro = "Você não tem permissão para editar este post.";
                }
            } else {
                // Inserir
                $stmt = $pdo->prepare("
                    INSERT INTO blog_posts 
                    (website_id, titulo, slug, conteudo, resumo, imagem, categoria_id, tags, status, autor_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$website_id, $titulo, $slug, $conteudo, $resumo, 
                               $imagem, $categoria_id, $tags, $status, $autor_id]);
            }
            
            if (!isset($erro)) {
                header('Location: gerenciar_posts.php?website_id=' . $website_id . '&msg=salvo');
                exit;
            }
        }
        
        // Excluir post
        if ($_POST['acao'] == 'excluir' && isset($_POST['id'])) {
            // Verificar permissão
            $stmt = $pdo->prepare("SELECT autor_id FROM blog_posts WHERE id = ? AND website_id = ?");
            $stmt->execute([$_POST['id'], $website_id]);
            $post_existente = $stmt->fetch();
            
            if ($post_existente && ($post_existente['autor_id'] == $usuario['id'] || $pode_editar_todos)) {
                $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ? AND website_id = ?");
                $stmt->execute([$_POST['id'], $website_id]);
                header('Location: gerenciar_posts.php?website_id=' . $website_id . '&msg=excluido');
                exit;
            } else {
                $erro = "Você não tem permissão para excluir este post.";
            }
        }
    }
}

// Obter posts com informações do autor
$posts = getBlogPostsComAutor($pdo, $website_id, 100);

// Função para buscar posts com autor
function getBlogPostsComAutor($pdo, $website_id, $limit = 100) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nome as autor_nome 
        FROM blog_posts p
        LEFT JOIN usuarios u ON p.autor_id = u.id
        WHERE p.website_id = ? 
        ORDER BY p.data_publicacao DESC 
        LIMIT ?
    ");
    $stmt->bindValue(1, $website_id, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar colaboradores para selecionar autor
$stmt = $pdo->prepare("
    SELECT u.id, u.nome 
    FROM site_colaboradores sc
    JOIN usuarios u ON sc.usuario_id = u.id
    WHERE sc.website_id = ? AND sc.nivel IN ('admin', 'editor', 'autor')
    UNION
    SELECT id, nome FROM usuarios WHERE id = ?  // Dono do site
");
$stmt->execute([$website_id, $website['usuario_id']]);
$autores = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <style>
        .tag {
            display: inline-block;
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin: 2px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-7xl mx-auto py-8 px-4">
        <?php if (isset($erro)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($erro_publicacao)): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                <?php echo $erro_publicacao; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-blue-600">Gerenciar Posts</h1>
                    <p class="text-gray-600">Blog: <?php echo htmlspecialchars($website['nome']); ?></p>
                    <?php if (!$is_dono && $nivel_colaborador): ?>
                        <p class="text-sm text-gray-500 mt-1">
                            <i class="fas fa-user-tag mr-1"></i>Você é: 
                            <span class="font-bold"><?php echo ucfirst($nivel_colaborador); ?></span>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="flex gap-2">
                    <a href="gerenciar_categorias.php?website_id=<?php echo $website_id; ?>" 
                       class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700 transition">
                        <i class="fas fa-tags mr-2"></i>Categorias
                    </a>
                    <a href="manage_blogs.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </a>
                </div>
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
                            <th class="py-2 px-4 border text-left">Categoria</th>
                            <th class="py-2 px-4 border text-left">Autor</th>
                            <th class="py-2 px-4 border text-left">Tags</th>
                            <th class="py-2 px-4 border text-left">Status</th>
                            <th class="py-2 px-4 border text-left">Data</th>
                            <th class="py-2 px-4 border text-left">Views</th>
                            <th class="py-2 px-4 border text-left">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): 
                            $pode_editar = ($post['autor_id'] == $usuario['id'] || $pode_editar_todos);
                        ?>
                        <tr>
                            <td class="py-2 px-4 border"><?php echo $post['id']; ?></td>
                            <td class="py-2 px-4 border">
                                <?php echo htmlspecialchars($post['titulo']); ?>
                                <?php if (!$pode_editar): ?>
                                    <i class="fas fa-lock text-gray-400 ml-1" title="Apenas o autor pode editar"></i>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4 border">
                                <?php 
                                if ($post['categoria_id']) {
                                    $stmt = $pdo->prepare("SELECT nome, cor FROM site_categorias WHERE id = ?");
                                    $stmt->execute([$post['categoria_id']]);
                                    $cat = $stmt->fetch();
                                    if ($cat) {
                                        echo '<span class="px-2 py-1 rounded-full text-xs" style="background-color: ' . $cat['cor'] . '20; color: ' . $cat['cor'] . '">' 
                                             . htmlspecialchars($cat['nome']) . '</span>';
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td class="py-2 px-4 border"><?php echo htmlspecialchars($post['autor_nome'] ?? 'Desconhecido'); ?></td>
                            <td class="py-2 px-4 border">
                                <?php 
                                if ($post['tags']) {
                                    $tags = explode(',', $post['tags']);
                                    foreach ($tags as $tag) {
                                        echo '<span class="tag">' . htmlspecialchars(trim($tag)) . '</span>';
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
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
                                <?php if ($pode_editar): ?>
                                    <button onclick="editarPost(<?php echo htmlspecialchars(json_encode($post)); ?>)" 
                                            class="text-blue-600 hover:text-blue-800 mr-2">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($pode_editar || $pode_editar_todos): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Excluir este post?');">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="ver_post.php?website_id=<?php echo $website_id; ?>&post_id=<?php echo $post['id']; ?>" 
                                   target="_blank" class="text-gray-600 hover:text-gray-800 ml-2">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
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
                <input type="hidden" name="autor_id" id="autor_id" value="<?php echo $usuario['id']; ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block font-bold mb-2">Título *</label>
                        <input type="text" name="titulo" id="post_titulo" required 
                               class="w-full px-3 py-2 border rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block font-bold mb-2">Imagem (URL)</label>
                        <input type="url" name="imagem" id="post_imagem" 
                               class="w-full px-3 py-2 border rounded"
                               placeholder="https://exemplo.com/imagem.jpg">
                    </div>

                    <div class="mb-4">
                        <label class="block font-bold mb-2">Categoria</label>
                        <select name="categoria_id" id="post_categoria" class="w-full px-3 py-2 border rounded">
                            <option value="">Sem categoria</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                        style="color: <?php echo $cat['cor']; ?>">
                                    <?php echo htmlspecialchars($cat['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block font-bold mb-2">Tags (separadas por vírgula)</label>
                        <input type="text" name="tags" id="post_tags" 
                               class="w-full px-3 py-2 border rounded"
                               placeholder="tecnologia, programação, web">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Resumo</label>
                    <textarea name="resumo" id="post_resumo" rows="3" 
                              class="w-full px-3 py-2 border rounded"
                              placeholder="Breve resumo do post..."></textarea>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Conteúdo *</label>
                    <textarea name="conteudo" id="post_conteudo" required 
                              class="w-full px-3 py-2 border rounded" rows="10"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Status</label>
                    <select name="status" id="post_status" class="w-full px-3 py-2 border rounded">
                        <option value="rascunho">Rascunho (apenas você vê)</option>
                        <?php if ($pode_publicar): ?>
                            <option value="publicado">Publicado (visível para todos)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <?php if ($pode_editar_todos && count($autores) > 1): ?>
                <div class="mb-4">
                    <label class="block font-bold mb-2">Autor</label>
                    <select name="autor_id" id="post_autor" class="w-full px-3 py-2 border rounded">
                        <?php foreach ($autores as $autor): ?>
                            <option value="<?php echo $autor['id']; ?>">
                                <?php echo htmlspecialchars($autor['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="flex justify-end gap-2">
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
        CKEDITOR.replace('post_conteudo', {
            height: 400,
            toolbar: [
                { name: 'document', items: ['Source', '-', 'Save', 'NewPage', 'Preview', 'Print', '-', 'Templates'] },
                { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo'] },
                { name: 'editing', items: ['Find', 'Replace', '-', 'SelectAll', '-', 'Scayt'] },
                { name: 'forms', items: ['Form', 'Checkbox', 'Radio', 'TextField', 'Textarea', 'Select', 'Button', 'ImageButton', 'HiddenField'] },
                '/',
                { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat'] },
                { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl', 'Language'] },
                { name: 'links', items: ['Link', 'Unlink', 'Anchor'] },
                { name: 'insert', items: ['Image', 'Flash', 'Table', 'HorizontalRule', 'Smiley', 'SpecialChar', 'PageBreak', 'Iframe'] },
                '/',
                { name: 'styles', items: ['Styles', 'Format', 'Font', 'FontSize'] },
                { name: 'colors', items: ['TextColor', 'BGColor'] },
                { name: 'tools', items: ['Maximize', 'ShowBlocks'] },
                { name: 'about', items: ['About'] }
            ]
        });

        function abrirModal() {
            document.getElementById('modalPost').classList.remove('hidden');
            document.getElementById('modalPost').classList.add('flex');
            document.getElementById('modalTitulo').textContent = 'Novo Post';
            document.getElementById('formPost').reset();
            document.getElementById('post_id').value = '';
            CKEDITOR.instances.post_conteudo.setData('');
        }

        function editarPost(post) {
            document.getElementById('modalPost').classList.remove('hidden');
            document.getElementById('modalPost').classList.add('flex');
            document.getElementById('modalTitulo').textContent = 'Editar Post';
            
            document.getElementById('post_id').value = post.id;
            document.getElementById('post_titulo').value = post.titulo;
            document.getElementById('post_imagem').value = post.imagem || '';
            document.getElementById('post_categoria').value = post.categoria_id || '';
            document.getElementById('post_tags').value = post.tags || '';
            document.getElementById('post_resumo').value = post.resumo || '';
            document.getElementById('post_autor').value = post.autor_id || '';
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
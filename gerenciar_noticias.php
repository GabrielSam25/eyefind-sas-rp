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

// Verificar se é do tipo noticias
if ($website['tipo'] != 'noticias') {
    header('Location: manage_blogs.php');
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao'])) {
        if ($_POST['acao'] == 'salvar') {
            $titulo = $_POST['titulo'];
            $conteudo = $_POST['conteudo'];
            $resumo = $_POST['resumo'] ?? substr($conteudo, 0, 150);
            $imagem = $_POST['imagem'] ?? '';
            $categoria = $_POST['categoria'] ?? '';
            $destaque = isset($_POST['destaque']) ? 1 : 0;
            $status = $_POST['status'] ?? 'rascunho';
            
            $slug = criarSlug($titulo);
            
            if (!empty($_POST['id'])) {
                $stmt = $pdo->prepare("UPDATE noticias_artigos SET titulo = ?, slug = ?, conteudo = ?, resumo = ?, imagem = ?, categoria = ?, destaque = ?, status = ? WHERE id = ? AND website_id = ?");
                $stmt->execute([$titulo, $slug, $conteudo, $resumo, $imagem, $categoria, $destaque, $status, $_POST['id'], $website_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO noticias_artigos (website_id, titulo, slug, conteudo, resumo, imagem, categoria, destaque, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$website_id, $titulo, $slug, $conteudo, $resumo, $imagem, $categoria, $destaque, $status]);
            }
            
            header('Location: gerenciar_noticias.php?website_id=' . $website_id . '&msg=salvo');
            exit;
        }
        
        if ($_POST['acao'] == 'excluir' && isset($_POST['id'])) {
            $stmt = $pdo->prepare("DELETE FROM noticias_artigos WHERE id = ? AND website_id = ?");
            $stmt->execute([$_POST['id'], $website_id]);
            header('Location: gerenciar_noticias.php?website_id=' . $website_id . '&msg=excluido');
            exit;
        }
    }
}

// Obter artigos
$stmt = $pdo->prepare("SELECT * FROM noticias_artigos WHERE website_id = ? ORDER BY data_publicacao DESC");
$stmt->execute([$website_id]);
$artigos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter categorias únicas
$stmt = $pdo->prepare("SELECT DISTINCT categoria FROM noticias_artigos WHERE website_id = ? AND categoria IS NOT NULL");
$stmt->execute([$website_id]);
$categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Notícias - <?php echo htmlspecialchars($website['nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-6xl mx-auto py-8 px-4">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-red-600">Gerenciar Notícias</h1>
                    <p class="text-gray-600">Portal: <?php echo htmlspecialchars($website['nome']); ?></p>
                </div>
                <a href="manage_blogs.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php 
                    if ($_GET['msg'] == 'salvo') echo "Notícia salva com sucesso!";
                    if ($_GET['msg'] == 'excluido') echo "Notícia excluída com sucesso!";
                    ?>
                </div>
            <?php endif; ?>

            <button onclick="abrirModal()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition mb-6">
                <i class="fas fa-plus mr-2"></i>Nova Notícia
            </button>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-4 border">ID</th>
                            <th class="py-2 px-4 border">Título</th>
                            <th class="py-2 px-4 border">Categoria</th>
                            <th class="py-2 px-4 border">Destaque</th>
                            <th class="py-2 px-4 border">Status</th>
                            <th class="py-2 px-4 border">Data</th>
                            <th class="py-2 px-4 border">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($artigos as $artigo): ?>
                        <tr>
                            <td class="py-2 px-4 border"><?php echo $artigo['id']; ?></td>
                            <td class="py-2 px-4 border"><?php echo htmlspecialchars($artigo['titulo']); ?></td>
                            <td class="py-2 px-4 border"><?php echo $artigo['categoria'] ?? '-'; ?></td>
                            <td class="py-2 px-4 border">
                                <?php if ($artigo['destaque']): ?>
                                    <span class="text-yellow-600"><i class="fas fa-star"></i> Destaque</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4 border">
                                <?php if ($artigo['status'] == 'publicado'): ?>
                                    <span class="text-green-600">Publicado</span>
                                <?php else: ?>
                                    <span class="text-yellow-600">Rascunho</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4 border"><?php echo date('d/m/Y', strtotime($artigo['data_publicacao'])); ?></td>
                            <td class="py-2 px-4 border">
                                <button onclick="editarArtigo(<?php echo htmlspecialchars(json_encode($artigo)); ?>)" class="text-blue-600 hover:text-blue-800 mr-2">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Excluir esta notícia?');">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?php echo $artigo['id']; ?>">
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

    <!-- Modal -->
    <div id="modalNoticia" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg w-11/12 max-w-4xl max-h-[90vh] overflow-y-auto p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold" id="modalTitulo">Nova Notícia</h2>
                <button onclick="fecharModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <form method="POST" id="formNoticia">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" id="noticia_id">

                <div class="mb-4">
                    <label class="block font-bold mb-2">Título *</label>
                    <input type="text" name="titulo" id="noticia_titulo" required class="w-full px-3 py-2 border rounded">
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Imagem (URL)</label>
                    <input type="url" name="imagem" id="noticia_imagem" class="w-full px-3 py-2 border rounded">
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Categoria</label>
                    <input type="text" name="categoria" id="noticia_categoria" class="w-full px-3 py-2 border rounded" list="categorias">
                    <datalist id="categorias">
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Resumo</label>
                    <textarea name="resumo" id="noticia_resumo" rows="3" class="w-full px-3 py-2 border rounded"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Conteúdo *</label>
                    <textarea name="conteudo" id="noticia_conteudo" required class="w-full px-3 py-2 border rounded" rows="10"></textarea>
                </div>

                <div class="mb-4 flex items-center">
                    <input type="checkbox" name="destaque" id="noticia_destaque" class="mr-2">
                    <label class="font-bold">Colocar em destaque</label>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Status</label>
                    <select name="status" id="noticia_status" class="w-full px-3 py-2 border rounded">
                        <option value="rascunho">Rascunho</option>
                        <option value="publicado">Publicado</option>
                    </select>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="fecharModal()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        CKEDITOR.replace('noticia_conteudo');

        function abrirModal() {
            document.getElementById('modalNoticia').classList.remove('hidden');
            document.getElementById('modalNoticia').classList.add('flex');
            document.getElementById('modalTitulo').textContent = 'Nova Notícia';
            document.getElementById('formNoticia').reset();
            document.getElementById('noticia_id').value = '';
        }

        function editarArtigo(artigo) {
            document.getElementById('modalNoticia').classList.remove('hidden');
            document.getElementById('modalNoticia').classList.add('flex');
            document.getElementById('modalTitulo').textContent = 'Editar Notícia';
            
            document.getElementById('noticia_id').value = artigo.id;
            document.getElementById('noticia_titulo').value = artigo.titulo;
            document.getElementById('noticia_imagem').value = artigo.imagem || '';
            document.getElementById('noticia_categoria').value = artigo.categoria || '';
            document.getElementById('noticia_resumo').value = artigo.resumo || '';
            CKEDITOR.instances.noticia_conteudo.setData(artigo.conteudo);
            document.getElementById('noticia_destaque').checked = artigo.destaque == 1;
            document.getElementById('noticia_status').value = artigo.status;
        }

        function fecharModal() {
            document.getElementById('modalNoticia').classList.add('hidden');
            document.getElementById('modalNoticia').classList.remove('flex');
        }
    </script>
</body>
</html>
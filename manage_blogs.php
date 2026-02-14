<?php
require_once 'config.php';
if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$usuario = getUsuarioAtual($pdo);
$usuario_id = $usuario['id'];

// Obter o blog do usuário (assumindo um por usuário)
$blog = getBlogDoUsuario($pdo, $usuario_id);
if (!$blog) {
    header('Location: new_blog.php');
    exit;
}

// Processar formulário de novo post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] === 'criar') {
        $titulo = $_POST['titulo'];
        $conteudo = $_POST['conteudo'];
        $imagem = $_POST['imagem'];
        $status = $_POST['status'];
        $stmt = $pdo->prepare("INSERT INTO website_posts (website_id, titulo, conteudo, imagem, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$blog['id'], $titulo, $conteudo, $imagem, $status]);
    } elseif ($_POST['acao'] === 'editar') {
        $id = $_POST['id'];
        $titulo = $_POST['titulo'];
        $conteudo = $_POST['conteudo'];
        $imagem = $_POST['imagem'];
        $status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE website_posts SET titulo=?, conteudo=?, imagem=?, status=? WHERE id=? AND website_id=?");
        $stmt->execute([$titulo, $conteudo, $imagem, $status, $id, $blog['id']]);
    } elseif ($_POST['acao'] === 'excluir') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM website_posts WHERE id=? AND website_id=?");
        $stmt->execute([$id, $blog['id']]);
    }
    header('Location: manage_posts.php');
    exit;
}

// Listar posts
$stmt = $pdo->prepare("SELECT * FROM website_posts WHERE website_id = ? ORDER BY data_publicacao DESC");
$stmt->execute([$blog['id']]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gerenciar Posts</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-6xl mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Gerenciar Posts do Blog</h1>
        <a href="index.php" class="bg-blue-500 text-white px-4 py-2 rounded">Voltar</a>
        <hr class="my-4">
        <h2 class="text-xl font-bold">Criar Novo Post</h2>
        <form method="POST" class="bg-white p-4 rounded shadow">
            <input type="hidden" name="acao" value="criar">
            <div class="mb-4">
                <label class="block">Título</label>
                <input type="text" name="titulo" required class="w-full border p-2">
            </div>
            <div class="mb-4">
                <label class="block">Conteúdo</label>
                <textarea name="conteudo" rows="5" class="w-full border p-2"></textarea>
            </div>
            <div class="mb-4">
                <label class="block">URL da Imagem</label>
                <input type="url" name="imagem" class="w-full border p-2">
            </div>
            <div class="mb-4">
                <label class="block">Status</label>
                <select name="status" class="border p-2">
                    <option value="rascunho">Rascunho</option>
                    <option value="publicado">Publicado</option>
                </select>
            </div>
            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Salvar Post</button>
        </form>

        <h2 class="text-xl font-bold mt-8">Posts Existentes</h2>
        <div class="space-y-4">
            <?php foreach ($posts as $post): ?>
                <div class="bg-white p-4 rounded shadow flex justify-between items-center">
                    <div>
                        <h3 class="font-bold"><?= htmlspecialchars($post['titulo']) ?></h3>
                        <p class="text-sm"><?= $post['status'] ?> - <?= $post['data_publicacao'] ?></p>
                    </div>
                    <div>
                        <button onclick="editarPost(<?= $post['id'] ?>)" class="bg-yellow-500 text-white px-3 py-1 rounded">Editar</button>
                        <form method="POST" class="inline">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= $post['id'] ?>">
                            <button type="submit" onclick="return confirm('Excluir?')" class="bg-red-500 text-white px-3 py-1 rounded">Excluir</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- Modal de edição (simples, via JS) -->
    <script>
        function editarPost(id) {
            // Abrir formulário preenchido via fetch ou redirect
            window.location = 'editar_post.php?id=' + id;
        }
    </script>
</body>
</html>
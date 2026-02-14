<?php
require_once 'config.php';
if (!isLogado()) { header('Location: login.php'); exit; }
$usuario = getUsuarioAtual($pdo);
$usuario_id = $usuario['id'];
$blog = getBlogDoUsuario($pdo, $usuario_id);
if (!$blog) { header('Location: new_blog.php'); exit; }

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM website_posts WHERE id=? AND website_id=?");
$stmt->execute([$id, $blog['id']]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$post) { header('Location: manage_posts.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $conteudo = $_POST['conteudo'];
    $imagem = $_POST['imagem'];
    $status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE website_posts SET titulo=?, conteudo=?, imagem=?, status=? WHERE id=?");
    $stmt->execute([$titulo, $conteudo, $imagem, $status, $id]);
    header('Location: manage_posts.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Editar Post</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <div class="max-w-4xl mx-auto p-4">
        <h1 class="text-2xl font-bold">Editar Post</h1>
        <form method="POST" class="bg-white p-4 rounded shadow">
            <div class="mb-4">
                <label class="block">Título</label>
                <input type="text" name="titulo" value="<?= htmlspecialchars($post['titulo']) ?>" required class="w-full border p-2">
            </div>
            <div class="mb-4">
                <label class="block">Conteúdo</label>
                <textarea name="conteudo" rows="5" class="w-full border p-2"><?= htmlspecialchars($post['conteudo']) ?></textarea>
            </div>
            <div class="mb-4">
                <label class="block">URL da Imagem</label>
                <input type="url" name="imagem" value="<?= htmlspecialchars($post['imagem']) ?>" class="w-full border p-2">
            </div>
            <div class="mb-4">
                <label class="block">Status</label>
                <select name="status" class="border p-2">
                    <option value="rascunho" <?= $post['status']=='rascunho'?'selected':'' ?>>Rascunho</option>
                    <option value="publicado" <?= $post['status']=='publicado'?'selected':'' ?>>Publicado</option>
                </select>
            </div>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Atualizar</button>
            <a href="manage_posts.php" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded">Cancelar</a>
        </form>
    </div>
</body>
</html>
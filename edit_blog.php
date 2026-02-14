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

$blog_id = intval($_GET['id']);
$usuario = getUsuarioAtual($pdo);
$usuario_id = $usuario['id'];

$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$blog_id, $usuario_id]);
$blog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$blog) {
    header('Location: manage_blogs.php');
    exit;
}

$categorias = getCategorias($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $imagem_url = $_POST['imagem_url'];
    $categoria_id = $_POST['categoria_id'];
    $conteudo = minifyHtml($_POST['conteudo']);
    $css = minifyCss($_POST['css']);

    $stmt = $pdo->prepare("UPDATE websites SET nome = :nome, descricao = :descricao, imagem = :imagem, categoria_id = :categoria_id, conteudo = :conteudo, css = :css WHERE id = :id");
    $stmt->execute([
        ':nome' => $nome,
        ':descricao' => $descricao,
        ':imagem' => $imagem_url,
        ':categoria_id' => $categoria_id,
        ':conteudo' => $conteudo,
        ':css' => $css,
        ':id' => $blog['id']
    ]);

    header('Location: manage_blogs.php?updated=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Blog - Eyefind.info</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://unpkg.com/grapesjs@0.22.6/dist/css/grapes.min.css" rel="stylesheet">
    <script src="https://unpkg.com/grapesjs@0.22.6/dist/grapes.min.js"></script>
    <script src="https://unpkg.com/grapesjs-plugin-forms"></script>
    <script src="https://unpkg.com/grapesjs-plugin-export"></script>
    <script src="https://unpkg.com/grapesjs-custom-code"></script>
    <script src="https://unpkg.com/grapesjs-blocks-flexbox"></script>
    <script src="https://unpkg.com/grapesjs-blocks-basic"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'eyefind-blue': '#067191',
                        'eyefind-light': '#E8F4F8',
                        'eyefind-dark': '#02404F'
                    }
                }
            }
        }

        function previewImage() {
            const url = document.getElementById('imagem_url').value;
            const preview = document.getElementById('image-preview');
            if (url) {
                preview.innerHTML = `<img src="${url}" alt="Pré-visualização" class="w-full h-48 object-cover rounded">`;
            } else {
                preview.innerHTML = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const editor = grapesjs.init({
                container: '#grapesjs-editor',
                fromElement: true,
                storageManager: false,
                height: '500px',
                plugins: [
                    'grapesjs-plugin-forms',
                    'grapesjs-blocks-basic',
                    'grapesjs-plugin-export',
                    'grapesjs-custom-code',
                    'grapesjs-blocks-flexbox'
                ],
                pluginsOpts: {
                    'grapesjs-plugin-forms': {},
                    'grapesjs-blocks-basic': { flexGrid: true },
                    'grapesjs-plugin-export': {},
                    'grapesjs-custom-code': {},
                    'grapesjs-blocks-flexbox': {}
                }
            });

            // Adicionar bloco de ajuda
            editor.BlockManager.add('dynamic-helper', {
                label: '📌 Ajuda: Marcadores',
                category: 'Dinâmico',
                content: '<div style="padding: 20px; background: #f0f9ff; border: 2px dashed #067191; border-radius: 8px; text-align: center;">' +
                         '<p style="font-weight: bold; margin-bottom: 10px;">✨ Marcadores Dinâmicos</p>' +
                         '<code style="display: block; background: #fff; padding: 5px; margin: 5px 0;">{{noticias}}</code>' +
                         '<code style="display: block; background: #fff; padding: 5px; margin: 5px 0;">{{bleets}}</code>' +
                         '<code style="display: block; background: #fff; padding: 5px; margin: 5px 0;">{{citacao}}</code>' +
                         '<code style="display: block; background: #fff; padding: 5px; margin: 5px 0;">{{produtos}}</code>' +
                         '<code style="display: block; background: #fff; padding: 5px; margin: 5px 0;">{{data}}</code>' +
                         '<code style="display: block; background: #fff; padding: 5px; margin: 5px 0;">{{hora}}</code>' +
                         '<p style="font-size: 12px; margin-top: 10px;">Use :N para limitar (ex: {{noticias:5}})</p>' +
                         '</div>'
            });

            // Carregar conteúdo existente
            editor.setComponents(`<?php echo addslashes($blog['conteudo']); ?>`);
            editor.setStyle(`<?php echo addslashes($blog['css']); ?>`);

            const form = document.querySelector('form[action="edit_blog.php?id=<?php echo $blog['id']; ?>"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    try {
                        const html = editor.getHtml();
                        const css = editor.getCss();

                        document.getElementById('conteudo').value = html || '';
                        document.getElementById('css').value = css || '';

                        form.removeEventListener('submit', arguments.callee);
                        form.submit();
                    } catch (error) {
                        console.error('Erro ao salvar:', error);
                    }
                });
            }
        });
    </script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap');
        body { font-family: 'Roboto Condensed', sans-serif; }
        #grapesjs-editor { height: 500px; border: 1px solid #e2e8f0; border-radius: 0.5rem; margin-bottom: 1rem; }
    </style>
</head>
<body class="bg-eyefind-light">
    <div class="max-w-7xl mx-auto">
        <section class="bg-[#488BC2] shadow-md">
            <div class="p-4 flex flex-col md:flex-row justify-between items-center">
                <div class="flex flex-col md:flex-row items-center gap-6">
                    <div class="w-64">
                        <img src="imagens/eyefind-logo.png" alt="Eyefind.info Logo" class="w-full">
                    </div>
                    <div class="w-full md:w-96">
                        <form action="search.php" method="GET">
                            <div class="relative">
                                <input type="text" name="q"
                                    class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue"
                                    placeholder="Procurar no Eyefind">
                                <button type="submit" class="absolute right-3 top-3 text-eyefind-blue">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="flex items-center gap-4 mt-4 md:mt-0">
                    <a href="manage_blogs.php" class="bg-blue-600 text-white px-4 py-2 rounded font-bold hover:bg-blue-700 transition">
                        Voltar
                    </a>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded font-bold hover:bg-red-700 transition">
                        Logout
                    </a>
                </div>
            </div>
        </section>

        <div class="w-full h-2 bg-yellow-400"></div>

        <section class="mt-1 bg-white p-6 shadow-md">
            <h2 class="text-2xl font-bold text-eyefind-blue mb-6">Editar Blog</h2>
            
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                <p class="text-green-700 font-bold">✨ CONTEÚDO DINÂMICO!</p>
                <p class="text-green-600">Use marcadores como <strong>{{noticias}}</strong>, <strong>{{bleets}}</strong>, <strong>{{data}}</strong> no seu HTML.</p>
            </div>
            
            <form action="edit_blog.php?id=<?php echo $blog['id']; ?>" method="POST">
                <div class="mb-4">
                    <label for="nome" class="block text-eyefind-dark font-bold mb-2">Nome do Blog</label>
                    <input type="text" name="nome" id="nome" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" value="<?php echo htmlspecialchars($blog['nome']); ?>" required>
                </div>
                
                <div class="mb-4">
                    <label for="descricao" class="block text-eyefind-dark font-bold mb-2">Descrição</label>
                    <textarea name="descricao" id="descricao" rows="3" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" required><?php echo htmlspecialchars($blog['descricao']); ?></textarea>
                </div>
                
                <div class="mb-4">
                    <label for="imagem_url" class="block text-eyefind-dark font-bold mb-2">URL da Imagem</label>
                    <input type="url" name="imagem_url" id="imagem_url" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" value="<?php echo htmlspecialchars($blog['imagem']); ?>" oninput="previewImage()" required>
                    <div id="image-preview" class="mt-2">
                        <?php if ($blog['imagem']): ?>
                            <img src="<?php echo htmlspecialchars($blog['imagem']); ?>" alt="Pré-visualização" class="w-full h-48 object-cover rounded">
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="categoria_id" class="block text-eyefind-dark font-bold mb-2">Categoria</label>
                    <select name="categoria_id" id="categoria_id" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" required>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id']; ?>" <?php echo $categoria['id'] == $blog['categoria_id'] ? 'selected' : ''; ?>><?php echo $categoria['nome']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="conteudo" class="block text-eyefind-dark font-bold mb-2">Conteúdo</label>
                    <div id="grapesjs-editor"></div>
                    <input type="hidden" name="conteudo" id="conteudo" value="<?php echo htmlspecialchars($blog['conteudo']); ?>">
                    <input type="hidden" name="css" id="css" value="<?php echo htmlspecialchars($blog['css']); ?>">
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700 transition">
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
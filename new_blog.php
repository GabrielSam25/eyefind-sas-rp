<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
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
    $usuario_id = $_SESSION['usuario_id'];

    // Gerar URL amigável
    $url = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $nome), '-'));
    
    // Verificar se URL já existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM websites WHERE url = ?");
    $stmt->execute([$url]);
    if ($stmt->fetchColumn() > 0) {
        $url .= '-' . uniqid();
    }

    $stmt = $pdo->prepare("INSERT INTO websites (nome, url, imagem, descricao, categoria_id, usuario_id, conteudo, css) VALUES (:nome, :url, :imagem, :descricao, :categoria_id, :usuario_id, :conteudo, :css)");
    
    $stmt->execute([
        ':nome' => $nome,
        ':url' => $url,
        ':imagem' => $imagem_url,
        ':descricao' => $descricao,
        ':categoria_id' => $categoria_id,
        ':usuario_id' => $usuario_id,
        ':conteudo' => $conteudo,
        ':css' => $css
    ]);

    header('Location: manage_blogs.php?success=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Novo Blog - Eyefind.info</title>
    <link rel="icon" type="image/png" sizes="192x192" href="icon/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icon/android-chrome-512x512.png">
    <link rel="apple-touch-icon" sizes="180x180" href="icon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="icon/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icon/favicon-32x32.png">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- GRAPESJS -->
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
                preview.innerHTML = `
                    <div class="relative w-full rounded border-2 border-eyefind-blue overflow-hidden">
                        <img src="${url}" alt="Pré-visualização" 
                             class="w-full h-auto max-h-[400px] object-cover"
                             onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'bg-red-50 p-4 text-red-500\'>Imagem não encontrada</div>'">
                    </div>
                `;
            } else {
                preview.innerHTML = `
                    <div class="relative w-full bg-gray-100 rounded border-2 border-dashed border-gray-300 flex items-center justify-center min-h-[200px]">
                        <span class="text-gray-500">Pré-visualização aparecerá aqui</span>
                    </div>
                `;
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

            // Adicionar blocos de ajuda para marcadores dinâmicos
            editor.BlockManager.add('dynamic-helper', {
                label: '📌 Ajuda: Marcadores',
                category: 'Dinâmico',
                content: '<div style="padding: 20px; background: #f0f9ff; border: 2px dashed #067191; border-radius: 8px; text-align: center;">' +
                         '<p style="font-weight: bold; margin-bottom: 10px;">✨ Marcadores Dinâmicos</p>' +
                         '<p style="font-size: 14px;">Use no seu HTML:</p>' +
                         '<code style="display: block; background: #fff; padding: 10px; margin: 10px 0; border-radius: 4px;">{{noticias}}</code>' +
                         '<code style="display: block; background: #fff; padding: 10px; margin: 10px 0; border-radius: 4px;">{{noticias:5}}</code>' +
                         '<code style="display: block; background: #fff; padding: 10px; margin: 10px 0; border-radius: 4px;">{{bleets}}</code>' +
                         '<code style="display: block; background: #fff; padding: 10px; margin: 10px 0; border-radius: 4px;">{{citacao}}</code>' +
                         '<code style="display: block; background: #fff; padding: 10px; margin: 10px 0; border-radius: 4px;">{{produtos}}</code>' +
                         '<code style="display: block; background: #fff; padding: 10px; margin: 10px 0; border-radius: 4px;">{{data}}</code>' +
                         '<code style="display: block; background: #fff; padding: 10px; margin: 10px 0; border-radius: 4px;">{{hora}}</code>' +
                         '<code style="display: block; background: #fff; padding: 10px; margin: 10px 0; border-radius: 4px;">{{usuario}}</code>' +
                         '<code style="display: block; background: #fff; padding: 10px; margin: 10px 0; border-radius: 4px;">{{contador}}</code>' +
                         '<p style="font-size: 12px; color: #666; margin-top: 10px;">Os marcadores serão substituídos automaticamente!</p>' +
                         '</div>'
            });

            const form = document.querySelector('form[action="new_blog.php"]');
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
                        alert('Erro ao salvar. Verifique o console.');
                    }
                });
            }
        });
    </script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap');
        body { font-family: 'Roboto Condensed', sans-serif; }
        #grapesjs-editor { height: 500px; border: 1px solid #e2e8f0; border-radius: 0.5rem; margin-bottom: 1rem; }
        .gjs-block { width: auto !important; height: auto !important; }
    </style>
</head>
<body class="bg-eyefind-light">
    <section class="bg-[#488BC2] shadow-md">
        <div class="p-4 flex flex-col md:flex-row justify-between items-center max-w-7xl mx-auto">
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
                <a href="index.php" class="bg-blue-600 text-white px-4 py-2 rounded font-bold hover:bg-blue-700 transition">
                    Voltar
                </a>
                <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded font-bold hover:bg-red-700 transition">
                    Logout
                </a>
            </div>
        </div>
    </section>

    <div class="w-full h-2 bg-yellow-400"></div>

    <div class="max-w-7xl mx-auto mt-1">
        <section class="bg-white p-6 shadow-md">
            <h2 class="text-2xl font-bold text-eyefind-blue mb-6">Criar Novo Blog</h2>
            
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                <p class="text-green-700 font-bold">✨ CONTEÚDO DINÂMICO SIMPLES!</p>
                <p class="text-green-600">No editor, use marcadores como <strong>{{noticias}}</strong>, <strong>{{bleets}}</strong>, <strong>{{citacao}}</strong>, <strong>{{data}}</strong> e eles serão substituídos automaticamente!</p>
                <p class="text-green-600 mt-2">Exemplo: <code>{{noticias:5}}</code> mostra as 5 últimas notícias.</p>
            </div>
            
            <form action="new_blog.php" method="POST">
                <div class="mb-4">
                    <label for="nome" class="block text-eyefind-dark font-bold mb-2">Nome do Blog</label>
                    <input type="text" name="nome" id="nome" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" required>
                </div>
                
                <div class="mb-4">
                    <label for="descricao" class="block text-eyefind-dark font-bold mb-2">Descrição</label>
                    <textarea name="descricao" id="descricao" rows="3" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" required></textarea>
                </div>
                
                <div class="mb-4">
                    <label for="imagem_url" class="block text-eyefind-dark font-bold mb-2">URL da Imagem do Blog</label>
                    <input type="url" name="imagem_url" id="imagem_url"
                        class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue"
                        oninput="previewImage()"
                        placeholder="https://exemplo.com/imagem.jpg"
                        required>
                    <div id="image-preview" class="mt-3">
                        <div class="relative w-full bg-gray-100 rounded border-2 border-dashed border-gray-300 flex items-center justify-center min-h-[200px]">
                            <span class="text-gray-500">Pré-visualização aparecerá aqui</span>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="categoria_id" class="block text-eyefind-dark font-bold mb-2">Categoria</label>
                    <select name="categoria_id" id="categoria_id" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" required>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id']; ?>"><?php echo $categoria['nome']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="conteudo" class="block text-eyefind-dark font-bold mb-2">Conteúdo do Blog</label>
                    <p class="text-sm text-gray-600 mb-2">💡 Dica: Use {{noticias}}, {{bleets}}, {{citacao}}, {{data}}, {{hora}} para conteúdo dinâmico!</p>
                    <div id="grapesjs-editor"></div>
                    <input type="hidden" name="conteudo" id="conteudo" value="">
                    <input type="hidden" name="css" id="css" value="">
                </div>
                
                <div class="flex justify-end gap-2">
                    <a href="manage_blogs.php" class="bg-gray-500 text-white px-6 py-2 rounded font-bold hover:bg-gray-600 transition">
                        Cancelar
                    </a>
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded font-bold hover:bg-green-700 transition">
                        Criar Blog
                    </button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
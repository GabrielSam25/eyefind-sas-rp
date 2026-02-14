<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$categorias = getCategorias($pdo);

// Processar o formulário de criação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $imagem_url = $_POST['imagem_url'];
    $categoria_id = $_POST['categoria_id'];
    $conteudo = minifyHtml($_POST['conteudo']);
    $css = minifyCss($_POST['css']);
    $usuario_id = $_SESSION['usuario_id'];

    $stmt = $pdo->prepare("INSERT INTO websites (nome, url, imagem, descricao, categoria_id, usuario_id, conteudo, css) VALUES (:nome, :url, :imagem, :descricao, :categoria_id, :usuario_id, :conteudo, :css)");
    $stmt->execute([
        ':nome' => $nome,
        ':url' => strtolower(str_replace(' ', '-', $nome)),
        ':imagem' => $imagem_url,
        ':descricao' => $descricao,
        ':categoria_id' => $categoria_id,
        ':usuario_id' => $usuario_id,
        ':conteudo' => $conteudo,
        ':css' => $css
    ]);

    $websiteId = $pdo->lastInsertId();
    
    header("Location: edit_blog.php?id=$websiteId");
    exit;
}

function minifyHtml($html)
{
    $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html);
    $html = preg_replace('/\s+/', ' ', $html);
    $html = preg_replace('/>\s+</', '><', $html);
    return trim($html);
}

function minifyCss($css)
{
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
    $css = preg_replace('/\s*([{}:;,+>~])\s*/', '$1', $css);
    $css = preg_replace('/;}/', '}', $css);
    return trim($css);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
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
    <link href="https://unpkg.com/grapesjs@0.22.6/dist/css/grapes.min.css" rel="stylesheet">
    <script src="https://unpkg.com/grapesjs@0.22.6/dist/grapes.min.js"></script>
    <script src="https://unpkg.com/grapesjs-plugin-forms@2.0.5"></script>
    <script src="https://unpkg.com/grapesjs-tailwind@latest"></script>
    <script src="https://unpkg.com/grapesjs-preset-webpage@1.0.3"></script>
    <script src="https://unpkg.com/grapesjs-blocks-basic@1.0.1"></script>
    <script src="https://unpkg.com/grapesjs-plugin-export@1.0.7"></script>
    <script src="https://unpkg.com/grapesjs-custom-code@1.0.1"></script>
    <script src="https://unpkg.com/grapesjs-blocks-flexbox@1.0.1"></script>
    <script src="https://unpkg.com/grapesjs-templates-manager@1.0.2"></script>
    <script src="https://unpkg.com/grapesjs-plugin-toolbox@0.1.0"></script>
    <script src="https://unpkg.com/grapesjs-symbols@1.0.0"></script>
    <script src="https://unpkg.com/grapesjs-blocks-bootstrap5@1.0.0"></script>
    <script src="https://unpkg.com/grapesjs-style-filter@1.0.0"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap');
        
        body {
            font-family: 'Roboto Condensed', sans-serif;
        }
        
        #grapesjs-editor {
            height: 600px;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .dynamic-area-highlight {
            outline: 2px dashed #3b82f6 !important;
            outline-offset: 2px;
            position: relative;
            cursor: pointer;
        }
        
        .dynamic-area-highlight::after {
            content: '🔧 Área Dinâmica';
            position: absolute;
            top: -25px;
            left: 0;
            background: #3b82f6;
            color: white;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 4px;
            z-index: 1000;
            white-space: nowrap;
        }
    </style>

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
                preview.innerHTML = `<img src="${url}" alt="Pré-visualização" class="w-full h-48 object-cover rounded border-2 border-blue-500">`;
            } else {
                preview.innerHTML = '<div class="w-full h-48 bg-gray-100 rounded border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-500">Pré-visualização</div>';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const editor = grapesjs.init({
                container: '#grapesjs-editor',
                fromElement: true,
                storageManager: false,
                allowScripts: true,
                plugins: [
                    'grapesjs-plugin-forms',
                    'grapesjs-tailwind',
                    'grapesjs-preset-webpage',
                    'grapesjs-blocks-basic',
                    'grapesjs-plugin-export',
                    'grapesjs-custom-code',
                    'grapesjs-blocks-flexbox',
                    'grapesjs-templates-manager',
                    'grapesjs-plugin-toolbox',
                    'grapesjs-symbols',
                    'grapesjs-blocks-bootstrap5',
                    'grapesjs-style-filter',
                ],
                pluginsOpts: {
                    'grapesjs-plugin-forms': {},
                    'grapesjs-tailwind': {},
                    'grapesjs-preset-webpage': {},
                    'grapesjs-blocks-basic': { flexGrid: true },
                    'grapesjs-plugin-export': {},
                    'grapesjs-custom-code': {},
                    'grapesjs-blocks-flexbox': {},
                    'grapesjs-templates-manager': {},
                    'grapesjs-blocks-bootstrap5': {},
                    'grapesjs-plugin-toolbox': {},
                    'grapesjs-symbols': {},
                    'grapesjs-style-filter': {}
                }
            });

            // ===== BOTÃO PARA ÁREAS DINÂMICAS =====
            editor.Panels.addButton('options', {
                id: 'make-dynamic',
                className: 'fa fa-cog',
                command: 'open-dynamic-modal',
                attributes: { 
                    title: 'Tornar área dinâmica (selecione um elemento)',
                    style: 'color: #3b82f6; font-size: 16px;'
                }
            });

            editor.Commands.add('open-dynamic-modal', {
                run(editor, sender) {
                    const selected = editor.getSelected();
                    if (!selected) {
                        alert('Por favor, selecione um elemento (div, section, etc.) primeiro.');
                        return;
                    }

                    // Verifica se o elemento já tem um data-dynamic-area
                    let dynamicId = selected.get('attributes')?.['data-dynamic-area'];
                    
                    // IMPORTANTE: Em new_blog.php, ainda não temos ID
                    // O usuário precisa salvar primeiro
                    alert('Primeiro, crie o blog com as informações básicas e salve. Depois você poderá adicionar áreas dinâmicas na página de edição.');
                    
                    // Redirecionar para a listagem de blogs
                    window.location.href = 'manage_blogs.php';
                }
            });

            // ===== FIM DO BOTÃO =====

            // Manipulador do formulário
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    try {
                        const html = editor.getHtml();
                        const css = editor.getCss();
                        
                        document.getElementById('conteudo').value = html || '';
                        document.getElementById('css').value = css || '';
                        
                        form.submit();
                    } catch (error) {
                        console.error('Erro ao salvar:', error);
                    }
                });
            }
        });
    </script>
</head>

<body class="bg-eyefind-light">
    <section class="bg-[#488BC2] shadow-md">
        <div class="p-4 flex flex-col md:flex-row justify-between items-center max-w-7xl mx-auto">
            <div class="flex items-center gap-6">
                <div class="w-64">
                    <img src="imagens/eyefind-logo.png" alt="Eyefind.info Logo" class="w-full">
                </div>
                <div class="w-96">
                    <form action="busca.php" method="GET">
                        <div class="relative">
                            <input type="text" name="q" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded" placeholder="Procurar no Eyefind">
                            <button type="submit" class="absolute right-3 top-3 text-eyefind-blue">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="flex gap-4">
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
            
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            <strong>Importante:</strong> Primeiro crie o blog com as informações básicas. 
                            Depois de salvar, você poderá adicionar áreas dinâmicas na página de edição.
                        </p>
                    </div>
                </div>
            </div>
            
            <form action="new_blog.php" method="POST">
                <div class="mb-4">
                    <label for="nome" class="block text-eyefind-dark font-bold mb-2">Nome do Blog</label>
                    <input type="text" name="nome" id="nome" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded" required>
                </div>
                
                <div class="mb-4">
                    <label for="descricao" class="block text-eyefind-dark font-bold mb-2">Descrição</label>
                    <textarea name="descricao" id="descricao" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded" required></textarea>
                </div>
                
                <div class="mb-4">
                    <label for="imagem_url" class="block text-eyefind-dark font-bold mb-2">URL da Imagem</label>
                    <input type="url" name="imagem_url" id="imagem_url" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded" oninput="previewImage()" required>
                    <div id="image-preview" class="mt-3"></div>
                </div>
                
                <div class="mb-4">
                    <label for="categoria_id" class="block text-eyefind-dark font-bold mb-2">Categoria</label>
                    <select name="categoria_id" id="categoria_id" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded" required>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id']; ?>"><?php echo $categoria['nome']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-eyefind-dark font-bold mb-2">Conteúdo do Blog (você pode editar depois)</label>
                    <div id="grapesjs-editor"></div>
                    <input type="hidden" name="conteudo" id="conteudo" value="">
                    <input type="hidden" name="css" id="css" value="">
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded font-bold hover:bg-green-700 transition text-lg">
                        <i class="fas fa-save mr-2"></i> Criar Blog
                    </button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
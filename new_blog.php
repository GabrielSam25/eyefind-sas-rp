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
    
    // Pegar o conteúdo do campo hidden
    $conteudo = isset($_POST['conteudo']) ? $_POST['conteudo'] : '';
    $css = isset($_POST['css']) ? $_POST['css'] : '';
    
    // Se estiver vazio, colocar um HTML padrão
    if (empty($conteudo)) {
        $conteudo = '<div class="p-8 text-center text-gray-500">Comece a editar seu blog usando o editor acima.</div>';
    }
    
    $usuario_id = $_SESSION['usuario_id'];

    $stmt = $pdo->prepare("INSERT INTO websites (nome, url, imagem, descricao, categoria_id, usuario_id, conteudo, css) VALUES (:nome, :url, :imagem, :descricao, :categoria_id, :usuario_id, :conteudo, :css)");
    
    $result = $stmt->execute([
        ':nome' => $nome,
        ':url' => strtolower(str_replace(' ', '-', $nome)),
        ':imagem' => $imagem_url,
        ':descricao' => $descricao,
        ':categoria_id' => $categoria_id,
        ':usuario_id' => $usuario_id,
        ':conteudo' => $conteudo,
        ':css' => $css
    ]);

    if ($result) {
        $websiteId = $pdo->lastInsertId();
        header("Location: edit_blog.php?id=$websiteId");
        exit;
    } else {
        $erro = "Erro ao salvar o blog.";
    }
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
            // CONTEÚDO INICIAL DO EDITOR - VAI APARECER NA TELA
            const initialContent = `
                <div class="max-w-6xl mx-auto p-8">
                    <!-- Cabeçalho -->
                    <div class="text-center mb-12">
                        <h1 class="text-4xl font-bold text-blue-600 mb-4">Bem-vindo ao meu novo blog!</h1>
                        <p class="text-xl text-gray-600">Este é um template inicial. Você pode editar tudo aqui.</p>
                    </div>
                    
                    <!-- Seção de cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <div class="text-blue-500 text-3xl mb-4">
                                <i class="fas fa-rocket"></i>
                            </div>
                            <h3 class="text-xl font-bold mb-2">Inovação</h3>
                            <p class="text-gray-600">Crie conteúdos incríveis com nosso editor visual.</p>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <div class="text-green-500 text-3xl mb-4">
                                <i class="fas fa-paint-brush"></i>
                            </div>
                            <h3 class="text-xl font-bold mb-2">Design</h3>
                            <p class="text-gray-600">Personalize cada detalhe do seu site.</p>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <div class="text-purple-500 text-3xl mb-4">
                                <i class="fas fa-magic"></i>
                            </div>
                            <h3 class="text-xl font-bold mb-2">Dinâmico</h3>
                            <p class="text-gray-600">Adicione áreas dinâmicas depois de salvar.</p>
                        </div>
                    </div>
                    
                    <!-- Seção de conteúdo -->
                    <div class="bg-gray-100 rounded-lg p-8 mb-8">
                        <h2 class="text-2xl font-bold mb-4">Sobre este blog</h2>
                        <p class="text-gray-700 mb-4">
                            Este é um template inicial para você começar. Você pode:
                        </p>
                        <ul class="list-disc list-inside text-gray-700 space-y-2">
                            <li>Editar qualquer texto clicando duas vezes</li>
                            <li>Arrastar novos elementos da barra lateral</li>
                            <li>Alterar cores e estilos no painel direito</li>
                            <li>Adicionar imagens, vídeos e muito mais</li>
                        </ul>
                    </div>
                    
                    <!-- Rodapé -->
                    <div class="text-center text-gray-500 text-sm">
                        <p>Comece a editar este conteúdo agora mesmo!</p>
                    </div>
                </div>
            `;

            const editor = grapesjs.init({
                container: '#grapesjs-editor',
                fromElement: true,
                components: initialContent, // Define o conteúdo inicial
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

            // ===== IMPORTANTE: Capturar o conteúdo antes de enviar =====
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    try {
                        // Pegar o HTML e CSS do editor
                        const html = editor.getHtml();
                        const css = editor.getCss();
                        
                        console.log('HTML que será salvo:', html); // Para debug
                        
                        // Colocar nos campos hidden
                        document.getElementById('conteudo').value = html || '';
                        document.getElementById('css').value = css || '';
                        
                        // Verificar se o conteúdo não está vazio
                        if (!html || html.trim() === '') {
                            if (!confirm('O conteúdo está vazio. Deseja continuar mesmo assim?')) {
                                return;
                            }
                        }
                        
                        // Agora enviar o formulário
                        form.submit();
                    } catch (error) {
                        console.error('Erro ao salvar:', error);
                        alert('Erro ao salvar o conteúdo. Tente novamente.');
                    }
                });
            }

            // Botão informativo (já que não podemos criar áreas dinâmicas aqui)
            editor.Panels.addButton('options', {
                id: 'make-dynamic-info',
                className: 'fa fa-info-circle',
                command: 'show-dynamic-info',
                attributes: { 
                    title: 'Áreas dinâmicas disponíveis após salvar',
                    style: 'color: #ff9800; font-size: 16px;'
                }
            });

            editor.Commands.add('show-dynamic-info', {
                run(editor) {
                    alert('📝 Áreas Dinâmicas\n\nPrimeiro, complete a criação do blog clicando em "Criar Blog".\n\nDepois de salvo, você será redirecionado para a página de edição onde poderá:\n\n• Selecionar qualquer elemento\n• Clicar no botão ⚙️ para torná-lo dinâmico\n• Adicionar itens (notícias, produtos, etc.)');
                }
            });
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
        <?php if (isset($erro)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <section class="bg-white p-6 shadow-md">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-eyefind-blue">Criar Novo Blog</h2>
                <div class="flex gap-2">
                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                        <i class="fas fa-check-circle mr-1"></i> Passo 1 de 2
                    </span>
                </div>
            </div>
            
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>🚀 Pronto para começar?</strong><br>
                            1. Preencha as informações básicas do seu blog<br>
                            2. Use o editor visual abaixo para criar seu conteúdo<br>
                            3. Clique em "Criar Blog" para salvar<br>
                            4. Depois de salvo, você poderá adicionar áreas dinâmicas!
                        </p>
                    </div>
                </div>
            </div>
            
            <form action="new_blog.php" method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="nome" class="block text-eyefind-dark font-bold mb-2">Nome do Blog <span class="text-red-500">*</span></label>
                        <input type="text" name="nome" id="nome" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ex: Meu Blog Pessoal" required>
                    </div>
                    
                    <div>
                        <label for="categoria_id" class="block text-eyefind-dark font-bold mb-2">Categoria <span class="text-red-500">*</span></label>
                        <select name="categoria_id" id="categoria_id" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Selecione uma categoria</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>"><?php echo $categoria['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="descricao" class="block text-eyefind-dark font-bold mb-2">Descrição <span class="text-red-500">*</span></label>
                    <textarea name="descricao" id="descricao" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3" placeholder="Uma breve descrição do seu blog..." required></textarea>
                </div>
                
                <div class="mb-6">
                    <label for="imagem_url" class="block text-eyefind-dark font-bold mb-2">URL da Imagem de Capa <span class="text-red-500">*</span></label>
                    <input type="url" name="imagem_url" id="imagem_url" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-blue-500" oninput="previewImage()" placeholder="https://exemplo.com/imagem.jpg" required>
                    <div id="image-preview" class="mt-3">
                        <div class="w-full h-48 bg-gray-100 rounded border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-500">
                            <i class="fas fa-image text-4xl mb-2"></i>
                            <p>Pré-visualização da imagem aparecerá aqui</p>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-eyefind-dark font-bold mb-2">Conteúdo do Blog (editor visual)</label>
                    <p class="text-sm text-gray-500 mb-2">Arraste elementos da barra lateral para construir seu blog</p>
                    <div id="grapesjs-editor"></div>
                    <input type="hidden" name="conteudo" id="conteudo" value="">
                    <input type="hidden" name="css" id="css" value="">
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-green-600 text-white px-8 py-3 rounded font-bold hover:bg-green-700 transition text-lg">
                        <i class="fas fa-save mr-2"></i> Criar Blog
                    </button>
                </div>
            </form>
        </section>
    </div>

    <script>
        // Pré-visualização inicial
        previewImage();
    </script>
</body>
</html>
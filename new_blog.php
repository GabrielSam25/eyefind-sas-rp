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
    $is_dynamic = isset($_POST['is_dynamic']) ? 1 : 0;
    $dynamic_blocks_data = $_POST['dynamic_blocks_data'] ?? '[]';

    // Verificar se há blocos dinâmicos no conteúdo
    $usingDynamicBlocks = strpos($conteudo, 'data-dynamic-type') !== false;
    if ($usingDynamicBlocks) {
        $is_dynamic = 1;
    }

    // Inserir o website
    $stmt = $pdo->prepare("INSERT INTO websites (nome, url, imagem, descricao, categoria_id, destaque, ordem, usuario_id, conteudo, css, is_dynamic, dynamic_config) VALUES (:nome, :url, :imagem, :descricao, :categoria_id, 0, 0, :usuario_id, :conteudo, :css, :is_dynamic, :dynamic_config)");
    
    $url = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $nome), '-'));
    
    $stmt->execute([
        ':nome' => $nome,
        ':url' => $url,
        ':imagem' => $imagem_url,
        ':descricao' => $descricao,
        ':categoria_id' => $categoria_id,
        ':usuario_id' => $usuario_id,
        ':conteudo' => $conteudo,
        ':css' => $css,
        ':is_dynamic' => $is_dynamic,
        ':dynamic_config' => $dynamic_blocks_data
    ]);

    $websiteId = $pdo->lastInsertId();

    // Se estiver usando blocos dinâmicos, processar para a tabela dynamic_blocks
    if ($usingDynamicBlocks) {
        $dynamicBlocks = json_decode($dynamic_blocks_data, true);
        if (is_array($dynamicBlocks)) {
            foreach ($dynamicBlocks as $index => $block) {
                $type = $block['type'] ?? 'unknown';
                $attrs = json_encode($block['attributes'] ?? []);
                
                $stmt = $pdo->prepare("INSERT INTO dynamic_blocks (website_id, block_type, content, block_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$websiteId, $type, $attrs, $index]);
            }
        }
    }

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

    <!-- GRAPESJS Core -->
    <link href="https://unpkg.com/grapesjs@0.22.6/dist/css/grapes.min.css" rel="stylesheet">
    <script src="https://unpkg.com/grapesjs@0.22.6/dist/grapes.min.js"></script>

    <!-- Plugins -->
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
                dragMode: 'translate',
                snapToGrid: true,
                snapGrid: 10,
                storageManager: false,
                allowScripts: true,
                components: {
                    wrapper: {
                        removable: false,
                        scripts: [],
                    }
                },
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
                    'grapesjs-blocks-basic': {
                        flexGrid: true,
                        blocks: ['*']
                    },
                    'grapesjs-plugin-export': {},
                    'grapesjs-custom-code': {},
                    'grapesjs-blocks-flexbox': {},
                    'grapesjs-templates-manager': {},
                    'grapesjs-blocks-bootstrap5': {},
                    'grapesjs-plugin-toolbox': {},
                    'grapesjs-symbols': {},
                    'grapesjs-style-filter': {}
                },
                blockManager: {
                    blocks: [
                        // Blocos básicos
                        {
                            id: 'text',
                            label: 'Texto',
                            category: 'Básicos',
                            content: '<div style="padding: 10px;">Insira seu texto aqui...</div>',
                        },
                        {
                            id: 'heading',
                            label: 'Título',
                            category: 'Básicos',
                            content: '<h1 style="padding: 10px;">Título</h1>',
                        },
                        {
                            id: 'image',
                            label: 'Imagem',
                            category: 'Básicos',
                            content: '<img src="https://via.placeholder.com/400x200" style="max-width:100%; padding: 10px;">',
                        },
                        {
                            id: 'button',
                            label: 'Botão',
                            category: 'Básicos',
                            content: '<button style="background: #067191; color: white; padding: 10px 20px; border-radius: 5px;">Clique aqui</button>',
                        },
                        
                        // BLOCOS DINÂMICOS - NOVOS!
                        {
                            id: 'dynamic-news',
                            label: '📰 Últimas Notícias',
                            category: 'Blocos Dinâmicos',
                            content: '<div data-dynamic-type="latest-news" data-limit="5" data-class="dynamic-news-block" style="padding: 10px; border: 2px dashed #067191; background: #f0f9ff; text-align: center;">🔴 Bloco Dinâmico: Últimas Notícias (será carregado automaticamente)</div>',
                        },
                        {
                            id: 'dynamic-bleets',
                            label: '💬 Bleets Recentes',
                            category: 'Blocos Dinâmicos',
                            content: '<div data-dynamic-type="recent-bleets" data-limit="3" data-class="dynamic-bleets-block" style="padding: 10px; border: 2px dashed #067191; background: #f0f9ff; text-align: center;">🟢 Bloco Dinâmico: Bleets Recentes</div>',
                        },
                        {
                            id: 'dynamic-quote',
                            label: '✨ Citação Aleatória',
                            category: 'Blocos Dinâmicos',
                            content: '<div data-dynamic-type="random-quote" data-class="dynamic-quote-block" style="padding: 10px; border: 2px dashed #067191; background: #f0f9ff; text-align: center;">💭 Bloco Dinâmico: Citação Aleatória</div>',
                        },
                        {
                            id: 'dynamic-products',
                            label: '🛍️ Produtos em Destaque',
                            category: 'Blocos Dinâmicos',
                            content: '<div data-dynamic-type="featured-products" data-limit="4" data-class="dynamic-products-grid" style="padding: 10px; border: 2px dashed #067191; background: #f0f9ff; text-align: center;">🛒 Bloco Dinâmico: Produtos</div>',
                        },
                        {
                            id: 'dynamic-stats',
                            label: '📊 Estatísticas do Usuário',
                            category: 'Blocos Dinâmicos',
                            content: '<div data-dynamic-type="user-stats" data-class="dynamic-stats" style="padding: 10px; border: 2px dashed #067191; background: #f0f9ff; text-align: center;">👤 Bloco Dinâmico: Estatísticas do Usuário</div>',
                        },
                        {
                            id: 'dynamic-counter',
                            label: '🔢 Contador Interativo',
                            category: 'Blocos Dinâmicos',
                            content: '<div data-dynamic-type="counter" data-initial="0" data-class="dynamic-counter" style="padding: 10px; border: 2px dashed #067191; background: #f0f9ff; text-align: center;">🧮 Bloco Dinâmico: Contador</div>',
                        }
                    ]
                },
                styleManager: {
                    sectors: [
                        {
                            name: 'Geral',
                            properties: [
                                { type: 'color', property: 'color', label: 'Cor do Texto' },
                                { type: 'color', property: 'background-color', label: 'Cor de Fundo' },
                                { type: 'select', property: 'text-align', label: 'Alinhamento', options: [
                                    { value: 'left', label: 'Esquerda' },
                                    { value: 'center', label: 'Centro' },
                                    { value: 'right', label: 'Direita' },
                                ]},
                                { type: 'slider', property: 'font-size', label: 'Tamanho da Fonte', defaults: '16px', step: 1, max: 100, min: 10 },
                            ]
                        },
                        {
                            name: 'Dimensões',
                            properties: [
                                { type: 'slider', property: 'width', label: 'Largura', units: ['px', '%', 'vw'], defaults: 'auto', min: 0, max: 1000 },
                                { type: 'slider', property: 'height', label: 'Altura', units: ['px', '%', 'vh'], defaults: 'auto', min: 0, max: 1000 },
                                { type: 'slider', property: 'margin', label: 'Margem', units: ['px', 'em', '%'], defaults: '0', min: 0, max: 100 },
                                { type: 'slider', property: 'padding', label: 'Preenchimento', units: ['px', 'em', '%'], defaults: '0', min: 0, max: 100 },
                            ]
                        }
                    ]
                }
            });

            // Manipulador do formulário - INCLUINDO COLETA DOS BLOCOS DINÂMICOS
            const form = document.querySelector('form[action="new_blog.php"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    try {
                        const html = editor.getHtml();
                        const css = editor.getCss();

                        // COLETAR BLOCOS DINÂMICOS
                        const dynamicBlocks = [];
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        
                        tempDiv.querySelectorAll('[data-dynamic-type]').forEach(el => {
                            const block = {
                                type: el.dataset.dynamicType,
                                attributes: {}
                            };
                            
                            // Copiar todos os atributos data-* exceto dynamic-type
                            for (let attr of el.attributes) {
                                if (attr.name.startsWith('data-') && attr.name !== 'data-dynamic-type') {
                                    block.attributes[attr.name] = attr.value;
                                }
                            }
                            
                            dynamicBlocks.push(block);
                        });

                        // Preencher campos hidden
                        document.getElementById('conteudo').value = html || '';
                        document.getElementById('css').value = css || '';
                        document.getElementById('dynamic_blocks_data').value = JSON.stringify(dynamicBlocks);

                        form.removeEventListener('submit', arguments.callee);
                        form.submit();
                    } catch (error) {
                        console.error('Erro ao salvar conteúdo:', error);
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
        .gjs-block { width: auto !important; height: auto !important; margin: 5px !important; }
        .gjs-block-label { font-size: 12px; }
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
                    <form action="busca.php" method="GET">
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
            
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                <p class="text-blue-700 font-bold">✨ NOVIDADE: Blocos Dinâmicos!</p>
                <p class="text-blue-600">Arraste blocos da categoria "Blocos Dinâmicos" para adicionar conteúdo que se atualiza automaticamente: notícias, bleets, citações, produtos e mais!</p>
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
                    <div id="grapesjs-editor"></div>
                    <input type="hidden" name="conteudo" id="conteudo" value="">
                    <input type="hidden" name="css" id="css" value="">
                    <input type="hidden" name="dynamic_blocks_data" id="dynamic_blocks_data" value="[]">
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
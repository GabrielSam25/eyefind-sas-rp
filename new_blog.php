<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$categorias = getCategorias($pdo);

// Processar o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $imagem_url = $_POST['imagem_url'];
    $categoria_id = $_POST['categoria_id'];
    $tipo = $_POST['tipo']; // NOVO: tipo do site
    $conteudo = minifyHtml($_POST['conteudo']);
    $css = minifyCss($_POST['css']);
    $usuario_id = $_SESSION['usuario_id'];
    $is_dynamic = isset($_POST['is_dynamic']) ? 1 : 0;
    $dynamic_config = isset($_POST['dynamic_config']) ? json_encode($_POST['dynamic_config']) : null;

    // Criar URL amigável
    $url = criarSlug($nome);

    // Verificar se URL já existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM websites WHERE url = ?");
    $stmt->execute([$url]);
    if ($stmt->fetchColumn() > 0) {
        $url .= '-' . time();
    }

    // Verificar se está usando blocos dinâmicos
    $usingDynamicBlocks = strpos($conteudo, 'data-block-type="dynamic"') !== false;

    if ($usingDynamicBlocks) {
        $is_dynamic = 1;
    }

    $stmt = $pdo->prepare("INSERT INTO websites (nome, url, imagem, descricao, categoria_id, tipo, destaque, ordem, usuario_id, conteudo, css, is_dynamic, dynamic_config, status) VALUES (:nome, :url, :imagem, :descricao, :categoria_id, :tipo, 0, 0, :usuario_id, :conteudo, :css, :is_dynamic, :dynamic_config, 'pending')");
    
    $stmt->execute([
        ':nome' => $nome,
        ':url' => $url,
        ':imagem' => $imagem_url,
        ':descricao' => $descricao,
        ':categoria_id' => $categoria_id,
        ':tipo' => $tipo,
        ':usuario_id' => $usuario_id,
        ':conteudo' => $conteudo,
        ':css' => $css,
        ':is_dynamic' => $is_dynamic,
        ':dynamic_config' => $dynamic_config
    ]);

    $website_id = $pdo->lastInsertId();

    // Se estiver usando blocos dinâmicos, processá-los
    if ($usingDynamicBlocks) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $conteudo, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $dynamicBlocks = $dom->getElementsByTagName('div');
        foreach ($dynamicBlocks as $block) {
            if ($block->getAttribute('data-block-type') === 'dynamic') {
                $blockType = $block->getAttribute('data-original-type') ?? 'custom';
                $content = '';

                foreach ($block->childNodes as $child) {
                    $content .= $dom->saveHTML($child);
                }

                $stmt = $pdo->prepare("INSERT INTO dynamic_blocks (website_id, block_type, content) VALUES (?, ?, ?)");
                $stmt->execute([$website_id, $blockType, $content]);

                $blockId = $pdo->lastInsertId();
                $block->setAttribute('data-block-id', $blockId);
            }
        }

        // Atualizar o conteúdo com os IDs dos blocos
        $updatedContent = $dom->saveHTML();
        $stmt = $pdo->prepare("UPDATE websites SET conteudo = ? WHERE id = ?");
        $stmt->execute([$updatedContent, $website_id]);
    }

    // Mensagem de sucesso baseada no tipo
    $mensagens = [
        'empresa' => 'sua página institucional.',
        'blog' => 'adicionar posts ao seu blog.',
        'noticias' => 'adicionar artigos e notícias.',
        'classificados' => 'adicionar anúncios classificados.'
    ];
    
    $_SESSION['sucesso'] = "Site criado com sucesso! Agora você pode " . ($mensagens[$tipo] ?? 'editar o conteúdo.');
    
    header('Location: edit_blog.php?id=' . $website_id);
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
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Novo Site - Eyefind.info</title>
    
    <!-- Ícones -->
    <link rel="icon" type="image/png" sizes="192x192" href="icon/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icon/android-chrome-512x512.png">
    <link rel="apple-touch-icon" sizes="180x180" href="icon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="icon/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icon/favicon-32x32.png">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- GRAPESJS Core -->
    <link href="https://unpkg.com/grapesjs@0.22.6/dist/css/grapes.min.css" rel="stylesheet">
    <script src="https://unpkg.com/grapesjs@0.22.6/dist/grapes.min.js"></script>

    <!-- Plugins GrapesJS -->
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
            background-color: #E8F4F8;
        }
        
        .tipo-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .tipo-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        
        .tipo-card.selected {
            border-color: #067191;
            background-color: #E8F4F8;
        }
        
        #grapesjs-editor {
            height: 500px;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .gjs-one-bg {
            background-color: #f8f9fa;
        }
        
        .gjs-two-color {
            color: #067191;
        }
        
        .gjs-three-bg {
            background-color: #02404F;
        }
        
        .gjs-four-color {
            color: #FEBE00;
        }
    </style>
    
    <script>
        // Configuração do Tailwind
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

        // Função de preview de imagem
        function previewImage() {
            const url = document.getElementById('imagem_url').value;
            const preview = document.getElementById('image-preview');

            if (url) {
                preview.innerHTML = `
                    <div class="relative w-full rounded border-2 border-eyefind-blue overflow-hidden">
                        <img src="${url}" alt="Pré-visualização da imagem" 
                             class="w-full h-auto max-h-[400px] object-cover"
                             onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-full bg-red-50 rounded border-2 border-red-300 flex items-center justify-center min-h-[200px] text-red-500\'>Imagem não encontrada</div>'">
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

        // Função para selecionar tipo
        function selectTipo(tipo) {
            document.querySelectorAll('.tipo-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`[data-tipo="${tipo}"]`).classList.add('selected');
            document.getElementById('tipo_selecionado').value = tipo;
        }

        // Inicialização do GrapesJS
        document.addEventListener('DOMContentLoaded', function() {
            const editor = grapesjs.init({
                container: '#grapesjs-editor',
                fromElement: true,
                height: '500px',
                width: 'auto',
                storageManager: false,
                allowScripts: true,
                
                panels: {
                    defaults: [
                        {
                            id: 'basic-actions',
                            el: '.panel__basic-actions',
                            buttons: [
                                {
                                    id: 'visibility',
                                    active: true,
                                    className: 'btn-toggle-borders',
                                    label: '<i class="fa fa-clone"></i>',
                                    command: 'sw-visibility',
                                },
                                {
                                    id: 'export',
                                    className: 'btn-open-export',
                                    label: '<i class="fa fa-code"></i>',
                                    command: 'export-template',
                                },
                                {
                                    id: 'undo',
                                    className: 'btn-undo',
                                    label: '<i class="fa fa-undo"></i>',
                                    command: 'undo',
                                },
                                {
                                    id: 'redo',
                                    className: 'btn-redo',
                                    label: '<i class="fa fa-repeat"></i>',
                                    command: 'redo',
                                },
                                {
                                    id: 'clear',
                                    className: 'btn-clear',
                                    label: '<i class="fa fa-trash"></i>',
                                    command: 'core:canvas-clear',
                                }
                            ]
                        },
                        {
                            id: 'panel-devices',
                            el: '.panel__devices',
                            buttons: [
                                {
                                    id: 'device-desktop',
                                    label: '<i class="fa fa-desktop"></i> Desktop',
                                    command: 'set-device-desktop',
                                    active: true,
                                },
                                {
                                    id: 'device-tablet',
                                    label: '<i class="fa fa-tablet"></i> Tablet',
                                    command: 'set-device-tablet',
                                },
                                {
                                    id: 'device-mobile',
                                    label: '<i class="fa fa-mobile"></i> Mobile',
                                    command: 'set-device-mobile',
                                }
                            ]
                        }
                    ]
                },
                
                deviceManager: {
                    devices: [
                        { name: 'Desktop', width: '' },
                        { name: 'Tablet', width: '768px', widthMedia: '810px' },
                        { name: 'Mobile', width: '320px', widthMedia: '480px' }
                    ]
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
                    'grapesjs-tailwind': {
                        tailwindConfig: {
                            theme: {
                                extend: {
                                    colors: {
                                        primary: '#067191',
                                        secondary: '#FEBE00'
                                    }
                                }
                            }
                        }
                    },
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
                
                canvas: {
                    styles: [
                        'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
                        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
                    ],
                    scripts: [
                        'https://cdn.tailwindcss.com'
                    ]
                },
                
                styleManager: {
                    sectors: [
                        {
                            name: 'Geral',
                            open: true,
                            properties: [
                                { type: 'color', property: 'color', label: 'Cor do Texto' },
                                { type: 'color', property: 'background-color', label: 'Cor de Fundo' },
                                { type: 'select', property: 'text-align', label: 'Alinhamento', 
                                  options: ['left', 'center', 'right'] },
                                { type: 'slider', property: 'font-size', label: 'Tamanho', 
                                  units: ['px', 'em'], min: 8, max: 100 }
                            ]
                        },
                        {
                            name: 'Dimensões',
                            open: false,
                            properties: [
                                { type: 'slider', property: 'width', label: 'Largura', 
                                  units: ['px', '%'], min: 0, max: 1920 },
                                { type: 'slider', property: 'height', label: 'Altura', 
                                  units: ['px', '%'], min: 0, max: 1080 },
                                { type: 'slider', property: 'margin', label: 'Margem', 
                                  units: ['px', 'em', '%'], min: -100, max: 500 },
                                { type: 'slider', property: 'padding', label: 'Preenchimento', 
                                  units: ['px', 'em', '%'], min: 0, max: 500 }
                            ]
                        },
                        {
                            name: 'Decorações',
                            open: false,
                            properties: [
                                { type: 'slider', property: 'border-radius', label: 'Borda', 
                                  units: ['px', '%'], min: 0, max: 100 },
                                { type: 'slider', property: 'border-width', label: 'Espessura', 
                                  units: ['px'], min: 0, max: 20 },
                                { type: 'color', property: 'border-color', label: 'Cor da Borda' },
                                { type: 'select', property: 'border-style', label: 'Estilo',
                                  options: ['none', 'solid', 'dashed', 'dotted'] }
                            ]
                        }
                    ]
                },
                
                blockManager: {
                    appendTo: '#blocks-container',
                    blocks: [
                        {
                            id: 'section',
                            label: '<i class="fa fa-square-o"></i> Seção',
                            content: '<section style="min-height: 100px; padding: 20px;"><h2 class="text-2xl font-bold">Título da Seção</h2><p>Conteúdo da seção...</p></section>',
                            category: 'Básicos',
                        },
                        {
                            id: 'text',
                            label: '<i class="fa fa-font"></i> Texto',
                            content: '<p class="text-gray-700">Digite seu texto aqui...</p>',
                            category: 'Básicos',
                        },
                        {
                            id: 'heading',
                            label: '<i class="fa fa-header"></i> Título',
                            content: '<h1 class="text-4xl font-bold text-eyefind-blue">Título Principal</h1>',
                            category: 'Básicos',
                        },
                        {
                            id: 'image',
                            label: '<i class="fa fa-image"></i> Imagem',
                            content: '<img src="https://via.placeholder.com/800x400" alt="Imagem" class="w-full h-auto rounded-lg shadow-md"/>',
                            category: 'Mídia',
                        },
                        {
                            id: 'button',
                            label: '<i class="fa fa-hand-pointer-o"></i> Botão',
                            content: '<button class="bg-eyefind-blue hover:bg-eyefind-dark text-white font-bold py-2 px-4 rounded transition">Clique Aqui</button>',
                            category: 'Componentes',
                        },
                        {
                            id: 'card',
                            label: '<i class="fa fa-id-card-o"></i> Card',
                            content: `
                                <div class="max-w-sm rounded overflow-hidden shadow-lg bg-white">
                                    <img class="w-full h-48 object-cover" src="https://via.placeholder.com/400x200" alt="Card">
                                    <div class="px-6 py-4">
                                        <h3 class="font-bold text-xl mb-2">Título do Card</h3>
                                        <p class="text-gray-700 text-base">Descrição do card aqui...</p>
                                    </div>
                                    <div class="px-6 pt-4 pb-2">
                                        <button class="bg-eyefind-blue text-white px-4 py-2 rounded">Saiba Mais</button>
                                    </div>
                                </div>
                            `,
                            category: 'Componentes',
                        },
                        {
                            id: 'grid-2',
                            label: '<i class="fa fa-th-large"></i> 2 Colunas',
                            content: `
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
                                    <div class="bg-gray-100 p-4 rounded">Coluna 1</div>
                                    <div class="bg-gray-100 p-4 rounded">Coluna 2</div>
                                </div>
                            `,
                            category: 'Layout',
                        }
                    ]
                }
            });

            // Comandos para dispositivos
            editor.Commands.add('set-device-desktop', {
                run: editor => editor.setDevice('Desktop')
            });
            editor.Commands.add('set-device-tablet', {
                run: editor => editor.setDevice('Tablet')
            });
            editor.Commands.add('set-device-mobile', {
                run: editor => editor.setDevice('Mobile')
            });

            // Manipulador do formulário
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
                        console.error('Erro ao salvar conteúdo:', error);
                        alert('Erro ao salvar o conteúdo. Tente novamente.');
                    }
                });
            }
        });
    </script>
</head>

<body class="bg-eyefind-light">
    <!-- Header -->
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

    <!-- Conteúdo Principal -->
    <div class="max-w-7xl mx-auto mt-8 px-4">
        <div class="bg-white p-8 shadow-md rounded-lg">
            <h1 class="text-3xl font-bold text-eyefind-blue mb-2">Criar Novo Site</h1>
            <p class="text-gray-600 mb-8">Escolha o tipo de site e personalize o conteúdo</p>

            <form action="new_blog.php" method="POST" id="formCriarSite">
                <!-- SELEÇÃO DE TIPO -->
                <div class="mb-8">
                    <label class="block text-eyefind-dark font-bold mb-4 text-lg">Tipo de Site</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Empresa -->
                        <div class="tipo-card p-4 border rounded-lg" data-tipo="empresa" onclick="selectTipo('empresa')">
                            <div class="flex items-start gap-3">
                                <div class="text-3xl text-blue-600"><i class="fas fa-building"></i></div>
                                <div>
                                    <h3 class="font-bold text-lg">Empresa</h3>
                                    <p class="text-sm text-gray-600">Página institucional com seções fixas</p>
                                    <ul class="text-xs text-gray-500 mt-2 space-y-1">
                                        <li>✓ Página única</li>
                                        <li>✓ Seções personalizáveis</li>
                                        <li>✓ Conteúdo estático</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Blog -->
                        <div class="tipo-card p-4 border rounded-lg" data-tipo="blog" onclick="selectTipo('blog')">
                            <div class="flex items-start gap-3">
                                <div class="text-3xl text-green-600"><i class="fas fa-blog"></i></div>
                                <div>
                                    <h3 class="font-bold text-lg">Blog</h3>
                                    <p class="text-sm text-gray-600">Sistema completo de posts</p>
                                    <ul class="text-xs text-gray-500 mt-2 space-y-1">
                                        <li>✓ Lista automática de posts</li>
                                        <li>✓ Criar/editar posts</li>
                                        <li>✓ Posts em destaque</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Portal de Notícias -->
                        <div class="tipo-card p-4 border rounded-lg" data-tipo="noticias" onclick="selectTipo('noticias')">
                            <div class="flex items-start gap-3">
                                <div class="text-3xl text-red-600"><i class="fas fa-newspaper"></i></div>
                                <div>
                                    <h3 class="font-bold text-lg">Portal de Notícias</h3>
                                    <p class="text-sm text-gray-600">Destaques e últimas notícias</p>
                                    <ul class="text-xs text-gray-500 mt-2 space-y-1">
                                        <li>✓ Artigos com categorias</li>
                                        <li>✓ Notícia em destaque</li>
                                        <li>✓ Listagem automática</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Classificados -->
                        <div class="tipo-card p-4 border rounded-lg" data-tipo="classificados" onclick="selectTipo('classificados')">
                            <div class="flex items-start gap-3">
                                <div class="text-3xl text-purple-600"><i class="fas fa-tags"></i></div>
                                <div>
                                    <h3 class="font-bold text-lg">Classificados</h3>
                                    <p class="text-sm text-gray-600">Anúncios e produtos</p>
                                    <ul class="text-xs text-gray-500 mt-2 space-y-1">
                                        <li>✓ Anúncios individuais</li>
                                        <li>✓ Preço e contato</li>
                                        <li>✓ Lista automática</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="tipo" id="tipo_selecionado" value="empresa" required>
                </div>

                <!-- DADOS BÁSICOS -->
                <div class="space-y-4 mb-8">
                    <div>
                        <label for="nome" class="block text-eyefind-dark font-bold mb-2">Nome do Site *</label>
                        <input type="text" name="nome" id="nome" 
                               class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" 
                               required>
                    </div>

                    <div>
                        <label for="descricao" class="block text-eyefind-dark font-bold mb-2">Descrição Curta *</label>
                        <textarea name="descricao" id="descricao" rows="3" 
                                  class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" 
                                  required></textarea>
                    </div>

                    <div>
                        <label for="imagem_url" class="block text-eyefind-dark font-bold mb-2">URL da Imagem de Capa *</label>
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

                    <div>
                        <label for="categoria_id" class="block text-eyefind-dark font-bold mb-2">Categoria Principal *</label>
                        <select name="categoria_id" id="categoria_id" 
                                class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" 
                                required>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>"><?php echo $categoria['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- EDITOR GRAPESJS -->
                <div class="mb-8">
                    <label class="block text-eyefind-dark font-bold mb-4 text-lg">Conteúdo do Site</label>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                        <!-- Painel Esquerdo - Blocos -->
                        <div class="lg:col-span-1">
                            <div class="bg-gray-100 p-4 rounded-lg">
                                <h3 class="font-bold text-eyefind-blue mb-4">Blocos</h3>
                                <div id="blocks-container" class="space-y-2 max-h-[600px] overflow-y-auto"></div>
                            </div>
                        </div>
                        
                        <!-- Editor Central -->
                        <div class="lg:col-span-3">
                            <div class="bg-white border border-gray-200 rounded-lg">
                                <div class="panel__basic-actions border-b border-gray-200 p-2 flex gap-2"></div>
                                <div class="panel__devices border-b border-gray-200 p-2 flex gap-2"></div>
                                <div id="grapesjs-editor" style="height: 600px;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="conteudo" id="conteudo" value="">
                    <input type="hidden" name="css" id="css" value="">
                    <input type="hidden" name="is_dynamic" id="is_dynamic" value="0">
                    <input type="hidden" name="dynamic_config" id="dynamic_config" value="">
                </div>

                <!-- Botões -->
                <div class="flex justify-between items-center pt-6 border-t">
                    <a href="index.php" class="text-gray-600 hover:text-gray-800">Cancelar</a>
                    <button type="submit" 
                            class="bg-green-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-green-700 transition text-lg">
                        <i class="fas fa-plus mr-2"></i>Criar Site
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Selecionar empresa por padrão
        selectTipo('empresa');
    </script>
</body>
</html>
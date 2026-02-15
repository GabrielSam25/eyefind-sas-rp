<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$categorias = getCategorias($pdo);

// No trecho de inserção, modifique para:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $imagem_url = $_POST['imagem_url'];
    $categoria_id = $_POST['categoria_id'];
    $conteudo = minifyHtml($_POST['conteudo']);
    $css = minifyCss($_POST['css']);
    $usuario_id = $_SESSION['usuario_id'];
    $is_dynamic = isset($_POST['is_dynamic']) ? 1 : 0;
    $dynamic_config = isset($_POST['dynamic_config']) ? json_encode($_POST['dynamic_config']) : null;

    // Verificar se está usando blocos dinâmicos
    $usingDynamicBlocks = strpos($conteudo, 'data-block-type="dynamic"') !== false;

    if ($usingDynamicBlocks) {
        $is_dynamic = 1;
    }

    $stmt = $pdo->prepare("INSERT INTO websites (nome, url, imagem, descricao, categoria_id, destaque, ordem, usuario_id, conteudo, css, is_dynamic, dynamic_config) VALUES (:nome, :url, :imagem, :descricao, :categoria_id, 0, 0, :usuario_id, :conteudo, :css, :is_dynamic, :dynamic_config)");
    $stmt->execute([
        ':nome' => $nome,
        ':url' => strtolower(str_replace(' ', '-', $nome)),
        ':imagem' => $imagem_url,
        ':descricao' => $descricao,
        ':categoria_id' => $categoria_id,
        ':usuario_id' => $usuario_id,
        ':conteudo' => $conteudo,
        ':css' => $css,
        ':is_dynamic' => $is_dynamic,
        ':dynamic_config' => $dynamic_config
    ]);

    // Se estiver usando blocos dinâmicos, processá-los
    if ($usingDynamicBlocks) {
        $websiteId = $pdo->lastInsertId();
        $dom = new DOMDocument();
        @$dom->loadHTML($conteudo);

        $dynamicBlocks = $dom->getElementsByTagName('div');
        foreach ($dynamicBlocks as $block) {
            if ($block->getAttribute('data-block-type') === 'dynamic') {
                $blockType = $block->getAttribute('data-original-type') ?? 'custom';
                $content = '';

                foreach ($block->childNodes as $child) {
                    $content .= $dom->saveHTML($child);
                }

                $stmt = $pdo->prepare("INSERT INTO dynamic_blocks (website_id, block_type, content) VALUES (?, ?, ?)");
                $stmt->execute([$websiteId, $blockType, $content]);

                $blockId = $pdo->lastInsertId();
                $block->setAttribute('data-block-id', $blockId);
            }
        }

        // Atualizar o conteúdo com os IDs dos blocos
        $updatedContent = $dom->saveHTML();
        $stmt = $pdo->prepare("UPDATE websites SET conteudo = ? WHERE id = ?");
        $stmt->execute([$updatedContent, $websiteId]);
    }

    header('Location: index.php');
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
    <title>Criar Novo Blog - Eyefind.info</title>
    <link rel="icon" type="image/png" sizes="192x192" href="icon/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="icon/android-chrome-512x512.png">
    <link rel="apple-touch-icon" sizes="180x180" href="icon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="icon/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icon/favicon-32x32.png">
    <link rel="icon" type="image/x-icon" href="favicon.ico">

    <!-- Tailwind CSS + Font Awesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- GrapesJS Core (versão estável mais recente) -->
    <link href="https://unpkg.com/grapesjs@0.22.6/dist/css/grapes.min.css" rel="stylesheet">
    <script src="https://unpkg.com/grapesjs@0.22.6/dist/grapes.min.js"></script>

    <!-- Plugins essenciais e modernos -->
    <script src="https://unpkg.com/grapesjs-blocks-basic@1.0.1"></script>
    <script src="https://unpkg.com/grapesjs-plugin-forms@2.0.5"></script>
    <script src="https://unpkg.com/grapesjs-plugin-export@1.0.7"></script>
    <script src="https://unpkg.com/grapesjs-custom-code@1.0.1"></script>
    <script src="https://unpkg.com/grapesjs-blocks-flexbox@1.0.1"></script>
    <script src="https://unpkg.com/grapesjs-tabs@1.0.6"></script>
    <script src="https://unpkg.com/grapesjs-navbar@1.0.3"></script>
    <script src="https://unpkg.com/grapesjs-component-countdown@1.0.2"></script>
    <script src="https://unpkg.com/grapesjs-style-bg@2.0.2"></script>
    <script src="https://unpkg.com/grapesjs-tooltip@1.0.2"></script>
    <script src="https://unpkg.com/grapesjs-parser@0.1.0"></script>
    <script src="https://unpkg.com/grapesjs-tailwind@2.1.0"></script>
    <script src="https://unpkg.com/grapesjs-blocks-bootstrap5@1.0.0"></script>
    <script src="https://unpkg.com/grapesjs-style-filter@1.0.0"></script>


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
                     onerror="this.onerror=null;preview.innerHTML='<div class=\'relative w-full bg-red-50 rounded border-2 border-red-300 flex items-center justify-center min-h-[200px] text-red-500\'>Imagem não encontrada</div>'">
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

        // Inicialização do GrapesJS
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
                        scripts: []
                    }
                },
                plugins: [
                    'grapesjs-blocks-basic',
                    'grapesjs-plugin-forms',
                    'grapesjs-plugin-export',
                    'grapesjs-custom-code',
                    'grapesjs-blocks-flexbox',
                    'grapesjs-tabs',
                    'grapesjs-navbar',
                    'grapesjs-component-countdown',
                    'grapesjs-style-bg',
                    'grapesjs-tooltip',
                    'grapesjs-parser',
                    'grapesjs-tailwind',
                    'grapesjs-blocks-bootstrap5',
                    'grapesjs-style-filter'
                ],
                pluginsOpts: {
                    'grapesjs-blocks-basic': {
                        flexGrid: true,
                        blocks: ['*'] // Todos os blocos básicos
                    },
                    'grapesjs-plugin-forms': {},
                    'grapesjs-plugin-export': {},
                    'grapesjs-custom-code': {},
                    'grapesjs-blocks-flexbox': {},
                    'grapesjs-tabs': {},
                    'grapesjs-navbar': {},
                    'grapesjs-component-countdown': {},
                    'grapesjs-style-bg': {},
                    'grapesjs-tooltip': {},
                    'grapesjs-parser': {},
                    'grapesjs-tailwind': {
                        // Configuração para usar classes Tailwind nos estilos
                        tailwindConfig: {
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
                    },
                    'grapesjs-blocks-bootstrap5': {},
                    'grapesjs-style-filter': {}
                },
                // Configuração avançada do gerenciador de estilos
                styleManager: {
                    sectors: [
                        {
                            name: 'Tipografia',
                            open: false,
                            properties: [
                                { type: 'color', property: 'color', label: 'Cor do texto' },
                                { type: 'select', property: 'font-family', label: 'Fonte', 
                                    options: [
                                        { value: 'Arial, sans-serif', label: 'Arial' },
                                        { value: 'Helvetica, sans-serif', label: 'Helvetica' },
                                        { value: 'Georgia, serif', label: 'Georgia' },
                                        { value: 'Times New Roman, serif', label: 'Times New Roman' },
                                        { value: 'Verdana, sans-serif', label: 'Verdana' },
                                        { value: 'Courier New, monospace', label: 'Courier New' }
                                    ]
                                },
                                { type: 'slider', property: 'font-size', label: 'Tamanho', units: ['px', 'em', 'rem'], defaults: '16px', min: 8, max: 100 },
                                { type: 'slider', property: 'line-height', label: 'Altura da linha', units: ['', 'px', 'em'], defaults: '1.5', min: 0.5, max: 3, step: 0.1 },
                                { type: 'slider', property: 'letter-spacing', label: 'Espaçamento', units: ['px', 'em'], defaults: '0', min: -5, max: 20 },
                                { type: 'select', property: 'font-weight', label: 'Peso', 
                                    options: [
                                        { value: 'normal', label: 'Normal' },
                                        { value: 'bold', label: 'Negrito' },
                                        { value: 'lighter', label: 'Leve' },
                                        { value: '100', label: '100' },
                                        { value: '200', label: '200' },
                                        { value: '300', label: '300' },
                                        { value: '400', label: '400' },
                                        { value: '500', label: '500' },
                                        { value: '600', label: '600' },
                                        { value: '700', label: '700' },
                                        { value: '800', label: '800' },
                                        { value: '900', label: '900' }
                                    ]
                                },
                                { type: 'select', property: 'text-align', label: 'Alinhamento', 
                                    options: [
                                        { value: 'left', label: 'Esquerda' },
                                        { value: 'center', label: 'Centro' },
                                        { value: 'right', label: 'Direita' },
                                        { value: 'justify', label: 'Justificado' }
                                    ]
                                },
                                { type: 'select', property: 'text-transform', label: 'Transformar', 
                                    options: [
                                        { value: 'none', label: 'Normal' },
                                        { value: 'uppercase', label: 'MAIÚSCULAS' },
                                        { value: 'lowercase', label: 'minúsculas' },
                                        { value: 'capitalize', label: 'Primeira Maiúscula' }
                                    ]
                                }
                            ]
                        },
                        {
                            name: 'Dimensões',
                            open: false,
                            properties: [
                                { type: 'slider', property: 'width', label: 'Largura', units: ['px', '%', 'vw'], defaults: 'auto', min: 0, max: 2000 },
                                { type: 'slider', property: 'height', label: 'Altura', units: ['px', '%', 'vh'], defaults: 'auto', min: 0, max: 2000 },
                                { type: 'slider', property: 'max-width', label: 'Largura máxima', units: ['px', '%'], min: 0, max: 2000 },
                                { type: 'slider', property: 'min-height', label: 'Altura mínima', units: ['px', '%'], min: 0, max: 2000 },
                                { type: 'composite', property: 'margin', label: 'Margem', properties: [
                                    { type: 'slider', property: 'margin-top', label: 'Topo', units: ['px', 'em', '%'], min: -100, max: 500 },
                                    { type: 'slider', property: 'margin-right', label: 'Direita', units: ['px', 'em', '%'], min: -100, max: 500 },
                                    { type: 'slider', property: 'margin-bottom', label: 'Inferior', units: ['px', 'em', '%'], min: -100, max: 500 },
                                    { type: 'slider', property: 'margin-left', label: 'Esquerda', units: ['px', 'em', '%'], min: -100, max: 500 }
                                ]},
                                { type: 'composite', property: 'padding', label: 'Preenchimento', properties: [
                                    { type: 'slider', property: 'padding-top', label: 'Topo', units: ['px', 'em', '%'], min: 0, max: 500 },
                                    { type: 'slider', property: 'padding-right', label: 'Direita', units: ['px', 'em', '%'], min: 0, max: 500 },
                                    { type: 'slider', property: 'padding-bottom', label: 'Inferior', units: ['px', 'em', '%'], min: 0, max: 500 },
                                    { type: 'slider', property: 'padding-left', label: 'Esquerda', units: ['px', 'em', '%'], min: 0, max: 500 }
                                ]}
                            ]
                        },
                        {
                            name: 'Fundo',
                            open: false,
                            buildProps: ['background-color', 'background-image', 'background-repeat', 'background-attachment', 'background-position', 'background-size'],
                            properties: [
                                { type: 'color', property: 'background-color', label: 'Cor de fundo' },
                                { type: 'background-image', property: 'background-image', label: 'Imagem de fundo' },
                                { type: 'select', property: 'background-repeat', label: 'Repetir', 
                                    options: [
                                        { value: 'repeat', label: 'Repetir' },
                                        { value: 'no-repeat', label: 'Não repetir' },
                                        { value: 'repeat-x', label: 'Repetir horizontal' },
                                        { value: 'repeat-y', label: 'Repetir vertical' }
                                    ]
                                },
                                { type: 'select', property: 'background-size', label: 'Tamanho',
                                    options: [
                                        { value: 'auto', label: 'Automático' },
                                        { value: 'cover', label: 'Cobrir' },
                                        { value: 'contain', label: 'Conter' }
                                    ]
                                },
                                { type: 'select', property: 'background-position', label: 'Posição',
                                    options: [
                                        { value: 'left top', label: 'Esquerda topo' },
                                        { value: 'left center', label: 'Esquerda centro' },
                                        { value: 'left bottom', label: 'Esquerda inferior' },
                                        { value: 'center top', label: 'Centro topo' },
                                        { value: 'center center', label: 'Centro centro' },
                                        { value: 'center bottom', label: 'Centro inferior' },
                                        { value: 'right top', label: 'Direita topo' },
                                        { value: 'right center', label: 'Direita centro' },
                                        { value: 'right bottom', label: 'Direita inferior' }
                                    ]
                                }
                            ]
                        },
                        {
                            name: 'Bordas',
                            open: false,
                            properties: [
                                { type: 'composite', property: 'border-radius', label: 'Raio da borda', properties: [
                                    { type: 'slider', property: 'border-top-left-radius', label: 'Superior esquerdo', units: ['px', '%'], min: 0, max: 200 },
                                    { type: 'slider', property: 'border-top-right-radius', label: 'Superior direito', units: ['px', '%'], min: 0, max: 200 },
                                    { type: 'slider', property: 'border-bottom-right-radius', label: 'Inferior direito', units: ['px', '%'], min: 0, max: 200 },
                                    { type: 'slider', property: 'border-bottom-left-radius', label: 'Inferior esquerdo', units: ['px', '%'], min: 0, max: 200 }
                                ]},
                                { type: 'composite', property: 'border', label: 'Borda', properties: [
                                    { type: 'slider', property: 'border-width', label: 'Espessura', units: ['px'], min: 0, max: 50 },
                                    { type: 'select', property: 'border-style', label: 'Estilo', 
                                        options: [
                                            { value: 'none', label: 'Nenhum' },
                                            { value: 'solid', label: 'Sólido' },
                                            { value: 'dashed', label: 'Tracejado' },
                                            { value: 'dotted', label: 'Pontilhado' },
                                            { value: 'double', label: 'Dupla' },
                                            { value: 'groove', label: 'Sulco' },
                                            { value: 'ridge', label: 'Crista' }
                                        ]
                                    },
                                    { type: 'color', property: 'border-color', label: 'Cor' }
                                ]}
                            ]
                        },
                        {
                            name: 'Sombras e Efeitos',
                            open: false,
                            properties: [
                                { type: 'box-shadow', property: 'box-shadow', label: 'Sombra da caixa' },
                                { type: 'text-shadow', property: 'text-shadow', label: 'Sombra do texto' },
                                { type: 'slider', property: 'opacity', label: 'Opacidade', units: ['', '%'], defaults: 1, step: 0.1, max: 1, min: 0 },
                                { type: 'slider', property: 'transform-rotate', label: 'Rotação', units: ['deg'], min: -180, max: 180 },
                                { type: 'select', property: 'cursor', label: 'Cursor',
                                    options: [
                                        { value: 'auto', label: 'Automático' },
                                        { value: 'pointer', label: 'Mão' },
                                        { value: 'move', label: 'Mover' },
                                        { value: 'text', label: 'Texto' },
                                        { value: 'wait', label: 'Espera' },
                                        { value: 'help', label: 'Ajuda' }
                                    ]
                                }
                            ]
                        }
                    ]
                },
                // Configuração do painel de blocos
                blockManager: {
                    appendTo: '#blocks-container', // Opcional, se quiser customizar
                    blocks: [
                        // Blocos personalizados com Tailwind
                        {
                            id: 'tailwind-card',
                            label: 'Card Moderno',
                            content: '<div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition m-4"><img class="w-full h-48 object-cover" src="https://via.placeholder.com/400x200" alt="Card image"><div class="p-6"><h3 class="text-xl font-bold text-eyefind-blue mb-2">Título do Card</h3><p class="text-gray-700 mb-4">Descrição do card com um texto exemplo para mostrar o conteúdo.</p><button class="bg-eyefind-blue text-white px-4 py-2 rounded hover:bg-eyefind-dark">Saiba mais</button></div></div>',
                            category: 'Tailwind',
                        },
                        {
                            id: 'tailwind-hero',
                            label: 'Hero Section',
                            content: '<section class="bg-gradient-to-r from-eyefind-blue to-eyefind-dark text-white py-20 px-4"><div class="container mx-auto text-center"><h1 class="text-4xl md:text-5xl font-bold mb-4">Título Principal</h1><p class="text-xl mb-8 max-w-2xl mx-auto">Descrição da seção hero com um texto impactante para capturar a atenção.</p><button class="bg-white text-eyefind-blue px-6 py-3 rounded-lg font-semibold hover:bg-gray-100">Call to Action</button></div></section>',
                            category: 'Tailwind',
                        },
                        {
                            id: 'tailwind-grid',
                            label: 'Grid 3 Colunas',
                            content: '<div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6"><div class="bg-gray-100 p-4 rounded"><h3 class="font-bold">Coluna 1</h3><p>Conteúdo da coluna</p></div><div class="bg-gray-100 p-4 rounded"><h3 class="font-bold">Coluna 2</h3><p>Conteúdo da coluna</p></div><div class="bg-gray-100 p-4 rounded"><h3 class="font-bold">Coluna 3</h3><p>Conteúdo da coluna</p></div></div>',
                            category: 'Tailwind',
                        }
                    ]
                }
            });

            // Personaliza a barra de comandos (adiciona botões)
            editor.Panels.addButton('options', {
                id: 'preview',
                className: 'fa fa-eye',
                command: 'preview',
                attributes: { title: 'Pré-visualizar' }
            });

            editor.Panels.addButton('options', {
                id: 'export',
                className: 'fa fa-code',
                command: 'export-template',
                attributes: { title: 'Exportar HTML/CSS' }
            });

            // Comando de preview personalizado (abre nova aba)
            editor.Commands.add('preview', {
                run: function(editor) {
                    const html = editor.getHtml();
                    const css = editor.getCss();
                    const previewWindow = window.open('', '_blank');
                    previewWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <script src="https://cdn.tailwindcss.com"></script>
                            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                            <style>${css}</style>
                        </head>
                        <body>${html}</body>
                        </html>
                    `);
                    previewWindow.document.close();
                }
            });

            // Manipulador do formulário de submissão
            const form = document.querySelector('form[method="POST"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    try {
                        const html = editor.getHtml();
                        const css = editor.getCss();
                        document.getElementById('conteudo').value = html || '';
                        document.getElementById('css').value = css || '';
                        // A imagem já está em imagem_final via preview
                        form.submit();
                    } catch (error) {
                        console.error('Erro ao salvar conteúdo:', error);
                        alert('Erro ao gerar o conteúdo do editor.');
                    }
                });
            }

            // Restrição de drag para componentes específicos (opcional)
            editor.on('component:drag:start', (component) => {
                if (component.get('type') === 'header' || component.get('customNoDrag')) {
                    editor.get('DomComponents').getWrapper().trigger('component:drag:stop');
                }
            });

        editor.on('component:drag', (component) => {
            const wrapper = editor.getWrapper();
            const wrapperEl = wrapper.getEl();

            // pega posição do componente e limita dentro do wrapper
            const compEl = component.view.el;
            const rect = compEl.getBoundingClientRect();
            const wrapRect = wrapperEl.getBoundingClientRect();

            if (rect.left < wrapRect.left) {
                compEl.style.left = '0px';
            }
        });
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap');

        body {
            font-family: 'Roboto Condensed', sans-serif;
        }

        #grapesjs-editor {
            height: 500px;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
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
            <form action="new_blog.php" method="POST">
                <div class="mb-4">
                    <label for="nome" class="block text-eyefind-dark font-bold mb-2">Nome do Blog</label>
                    <input type="text" name="nome" id="nome" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" required>
                </div>
                <div class="mb-4">
                    <label for="descricao" class="block text-eyefind-dark font-bold mb-2">Descrição</label>
                    <textarea name="descricao" id="descricao" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" required></textarea>
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
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700 transition">
                        Criar Blog
                    </button>
                </div>
            </form>
        </section>
    </div>
</body>
<html>

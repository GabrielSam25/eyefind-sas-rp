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

        /* Estilo para destacar áreas dinâmicas */
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

        /* Estilo para placeholders de áreas dinâmicas */
        .dynamic-area-placeholder {
            background-color: #f3f4f6;
            border: 2px dashed #9ca3af;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            color: #6b7280;
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 10px;
        }

        .dynamic-area-placeholder i {
            font-size: 32px;
            color: #9ca3af;
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
                styleManager: {
                    sectors: [{
                            name: 'Geral',
                            properties: [{
                                    type: 'color',
                                    property: 'color',
                                    label: 'Cor do Texto',
                                },
                                {
                                    type: 'color',
                                    property: 'background-color',
                                    label: 'Cor de Fundo',
                                },
                                {
                                    type: 'select',
                                    property: 'text-align',
                                    label: 'Alinhamento',
                                    options: [{
                                            value: 'left',
                                            label: 'Esquerda'
                                        },
                                        {
                                            value: 'center',
                                            label: 'Centro'
                                        },
                                        {
                                            value: 'right',
                                            label: 'Direita'
                                        },
                                    ]
                                },
                                {
                                    type: 'slider',
                                    property: 'font-size',
                                    label: 'Tamanho da Fonte',
                                    defaults: '16px',
                                    step: 1,
                                    max: 100,
                                    min: 10,
                                }
                            ]
                        },
                        {
                            name: 'Dimensões',
                            properties: [{
                                    type: 'slider',
                                    property: 'width',
                                    label: 'Largura',
                                    units: ['px', '%', 'vw'],
                                    defaults: 'auto',
                                    min: 0,
                                    max: 1000,
                                },
                                {
                                    type: 'slider',
                                    property: 'height',
                                    label: 'Altura',
                                    units: ['px', '%', 'vh'],
                                    defaults: 'auto',
                                    min: 0,
                                    max: 1000,
                                },
                                {
                                    type: 'slider',
                                    property: 'margin',
                                    label: 'Margem',
                                    units: ['px', 'em', '%'],
                                    defaults: '0',
                                    min: 0,
                                    max: 100,
                                },
                                {
                                    type: 'slider',
                                    property: 'padding',
                                    label: 'Preenchimento',
                                    units: ['px', 'em', '%'],
                                    defaults: '0',
                                    min: 0,
                                    max: 100,
                                }
                            ]
                        },
                        {
                            name: 'Decorações',
                            properties: [{
                                    type: 'slider',
                                    property: 'border-radius',
                                    label: 'Borda Arredondada',
                                    units: ['px', '%'],
                                    defaults: '0',
                                    min: 0,
                                    max: 100,
                                },
                                {
                                    type: 'slider',
                                    property: 'border-width',
                                    label: 'Espessura da Borda',
                                    units: ['px'],
                                    defaults: '0',
                                    min: 0,
                                    max: 20,
                                },
                                {
                                    type: 'color',
                                    property: 'border-color',
                                    label: 'Cor da Borda',
                                },
                                {
                                    type: 'select',
                                    property: 'border-style',
                                    label: 'Estilo da Borda',
                                    options: [{
                                            value: 'none',
                                            label: 'Nenhum'
                                        },
                                        {
                                            value: 'solid',
                                            label: 'Sólido'
                                        },
                                        {
                                            value: 'dashed',
                                            label: 'Tracejado'
                                        },
                                        {
                                            value: 'dotted',
                                            label: 'Pontilhado'
                                        },
                                    ]
                                }
                            ]
                        },
                        {
                            name: 'Sombras e Efeitos',
                            properties: [{
                                    type: 'stack',
                                    property: 'box-shadow',
                                    label: 'Sombra',
                                    properties: [{
                                            type: 'slider',
                                            units: ['px'],
                                            property: 'offsetX',
                                            defaults: 0,
                                            min: -50,
                                            max: 50,
                                            label: 'X'
                                        },
                                        {
                                            type: 'slider',
                                            units: ['px'],
                                            property: 'offsetY',
                                            defaults: 0,
                                            min: -50,
                                            max: 50,
                                            label: 'Y'
                                        },
                                        {
                                            type: 'slider',
                                            units: ['px'],
                                            property: 'blur',
                                            defaults: 0,
                                            min: 0,
                                            max: 50,
                                            label: 'Desfoque'
                                        },
                                        {
                                            type: 'slider',
                                            units: ['px'],
                                            property: 'spread',
                                            defaults: 0,
                                            min: 0,
                                            max: 50,
                                            label: 'Expansão'
                                        },
                                        {
                                            type: 'color',
                                            property: 'color',
                                            label: 'Cor'
                                        },
                                    ]
                                },
                                {
                                    type: 'slider',
                                    property: 'opacity',
                                    label: 'Opacidade',
                                    defaults: 1,
                                    step: 0.1,
                                    max: 1,
                                    min: 0,
                                }
                            ]
                        }
                    ]
                }
            });

            // === BOTÃO PARA TORNAR ÁREA DINÂMICA ===
            
            // Adiciona um botão na barra de ferramentas
            editor.Panels.addButton('options', {
                id: 'make-dynamic',
                className: 'fa fa-cog',
                command: 'open-dynamic-modal',
                attributes: { 
                    title: 'Tornar área dinâmica (selecione um elemento primeiro)',
                    style: 'color: #3b82f6; font-size: 16px;'
                }
            });

            // Comando para abrir o modal
            editor.Commands.add('open-dynamic-modal', {
                run(editor, sender) {
                    const selected = editor.getSelected();
                    if (!selected) {
                        alert('Por favor, selecione um elemento (div, section, etc.) primeiro.');
                        return;
                    }

                    // Verifica se o elemento já tem um data-dynamic-area
                    let dynamicId = selected.get('attributes')?.['data-dynamic-area'];
                    const websiteId = <?php echo isset($blog) ? $blog['id'] : 'null'; ?>;

                    // Se não tiver, criar uma nova área dinâmica via AJAX
                    if (!dynamicId) {
                        if (!websiteId) {
                            alert('Salve o site primeiro antes de criar áreas dinâmicas.');
                            return;
                        }
                        
                        // Gerar um ID único
                        dynamicId = 'area_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                        
                        // Mostrar indicador de loading
                        sender.set('active', false);
                        
                        // Enviar requisição para criar a área no banco
                        fetch('ajax/create_dynamic_area.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                website_id: websiteId, 
                                element_id: dynamicId 
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // Atualizar o componente com o atributo
                                selected.addAttributes({ 'data-dynamic-area': data.element_id });
                                
                                // Adicionar uma classe visual para identificar a área
                                const currentClasses = selected.get('classes') || [];
                                selected.set('classes', [...currentClasses, 'dynamic-area-highlight']);
                                
                                // Redirecionar para editar a área
                                window.location.href = `edit_dynamic_area.php?website_id=${websiteId}&element_id=${data.element_id}`;
                            } else {
                                alert('Erro ao criar área dinâmica: ' + (data.error || 'Erro desconhecido'));
                            }
                        })
                        .catch(error => {
                            alert('Erro na requisição: ' + error);
                        });
                    } else {
                        // Se já existe, abrir a página de edição da área
                        window.location.href = `edit_dynamic_area.php?website_id=${websiteId}&element_id=${dynamicId}`;
                    }
                }
            });

            // Adiciona um botão no menu de contexto (clique direito)
            editor.Commands.add('show-dynamic-context', {
                run(editor, sender, opts) {
                    const selected = editor.getSelected();
                    if (!selected) return;
                    
                    const dynamicId = selected.get('attributes')?.['data-dynamic-area'];
                    const websiteId = <?php echo isset($blog) ? $blog['id'] : 'null'; ?>;
                    
                    if (dynamicId && websiteId) {
                        if (confirm('Editar esta área dinâmica?')) {
                            window.location.href = `edit_dynamic_area.php?website_id=${websiteId}&element_id=${dynamicId}`;
                        }
                    }
                }
            });

            // Atalho de teclado (Ctrl+Shift+D) para tornar dinâmico
            editor.on('keydown', (event) => {
                if (event.ctrlKey && event.shiftKey && event.key === 'D') {
                    event.preventDefault();
                    editor.runCommand('open-dynamic-modal');
                }
            });

            // === FIM DO CÓDIGO DO BOTÃO ===

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
                    }
                });
            }

            // Eventos de drag
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

            // Mostrar dica de como usar áreas dinâmicas
            setTimeout(() => {
                console.log('💡 Dica: Selecione um elemento e clique no ícone de engrenagem (⚙️) para torná-lo uma área dinâmica!');
            }, 3000);
        });
    </script>
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
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-eyefind-blue">Criar Novo Blog</h2>
                <div class="bg-blue-50 text-blue-800 px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-info-circle mr-2"></i>
                    Dica: Selecione um elemento e clique no ícone ⚙️ para criar áreas dinâmicas
                </div>
            </div>
            
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

    <!-- Pequeno tutorial flutuante (opcional) -->
    <div class="fixed bottom-4 right-4 bg-white rounded-lg shadow-lg p-4 max-w-xs border-l-4 border-blue-500 hidden md:block">
        <h4 class="font-bold text-blue-600 mb-2"><i class="fas fa-magic mr-2"></i>Áreas Dinâmicas</h4>
        <p class="text-sm text-gray-600 mb-2">
            1. Arraste um elemento (div, section) para a tela<br>
            2. Selecione o elemento<br>
            3. Clique no ícone ⚙️ na barra de ferramentas<br>
            4. Adicione itens (notícias, produtos, etc.)
        </p>
        <button onclick="this.parentElement.remove()" class="text-xs text-gray-500 hover:text-gray-700">
            Fechar dica
        </button>
    </div>
</body>
</html>
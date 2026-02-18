<?php
// Incluir arquivo de configuração e conexão
require_once 'config.php';

// Verificar se o usuário está logado
if (!isLogado()) {
    header('Location: login.php');
    exit;
}

// Verificar se o ID do blog foi passado
if (!isset($_GET['id'])) {
    header('Location: manage_blogs.php');
    exit;
}

// Obter o ID do blog
$blog_id = intval($_GET['id']);

// Obter o usuário atual
$usuario = getUsuarioAtual($pdo);
$usuario_id = $usuario['id'];

// Obter o blog específico
$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$blog_id, $usuario_id]);
$blog = $stmt->fetch(PDO::FETCH_ASSOC);

// Redirecionar se o blog não existir ou não pertencer ao usuário
if (!$blog) {
    header('Location: manage_blogs.php');
    exit;
}

// Obter categorias do banco de dados
$categorias = getCategorias($pdo);

// Processar o formulário de edição do blog
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $imagem_url = $_POST['imagem_url'];
    $categoria_id = $_POST['categoria_id'];
    $conteudo = minifyHtml($_POST['conteudo']); // Minificar HTML
    $css = minifyCss($_POST['css']); // Minificar CSS

    // Atualizar o blog no banco de dados
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

    header('Location: manage_blogs.php');
    exit;
}

// Função para minificar HTML
function minifyHtml($html)
{
    $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html);
    $html = preg_replace('/\s+/', ' ', $html);
    return trim($html);
}

// Função para minificar CSS
function minifyCss($css)
{
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
    return trim($css);
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
    <link href="https://cdn.jsdelivr.net/npm/grapesjs@0.22.6/dist/css/grapes.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/grapesjs@0.22.6/dist/grapes.min.js"></script>
    <script src="https://unpkg.com/grapesjs-plugin-forms"></script>
    <script src="https://unpkg.com/grapesjs-plugin-export"></script>
    <script src="https://unpkg.com/grapesjs-custom-code"></script>
    <script src="https://unpkg.com/grapesjs-blocks-flexbox"></script>
    <script src="https://unpkg.com/grapesjs-blocks-basic"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-plugin-forms@2.0.6/dist/grapesjs-plugin-forms.min.js"></script>
    <script src="https://unpkg.com/grapesjs-tailwind@latest/dist/grapesjs-tailwind.min.js"></script>
    <script src="https://unpkg.com/grapesjs-preset-webpage@1.0.3/dist/grapesjs-preset-webpage.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-plugin-export@1.0.7/dist/grapesjs-plugin-export.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-templates-manager@1.0.0/dist/grapesjs-templates-manager.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-bootstrap5@1.0.0/dist/grapesjs-blocks-bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-plugin-toolbox@0.1.0/dist/grapesjs-plugin-toolbox.min.js"></script>
    <script src="https://unpkg.com/grapesjs-symbols@1.0.0/dist/grapesjs-symbols.min.js"></script>

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
                preview.innerHTML = `<img src="${url}" alt="Pré-visualização da imagem" class="w-full h-48 object-cover rounded">`;
            } else {
                preview.innerHTML = '';
            }
        }

document.addEventListener('DOMContentLoaded', function() {
    const editor = grapesjs.init({
        container: '#grapesjs-editor',
        fromElement: true,
        storageManager: false,
        height: '700px',
        width: 'auto',
        
        // Painéis de controle
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
                            context: 'sw-visibility',
                        },
                        {
                            id: 'export',
                            className: 'btn-open-export',
                            label: '<i class="fa fa-code"></i>',
                            command: 'export-template',
                            context: 'export-template',
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
                    buttons: [{
                        id: 'device-desktop',
                        label: '<i class="fa fa-desktop"></i>',
                        command: 'set-device-desktop',
                        active: true,
                        togglable: false,
                    }, {
                        id: 'device-tablet',
                        label: '<i class="fa fa-tablet"></i>',
                        command: 'set-device-tablet',
                        togglable: false,
                    }, {
                        id: 'device-mobile',
                        label: '<i class="fa fa-mobile"></i>',
                        command: 'set-device-mobile',
                        togglable: false,
                    }]
                }
            ]
        },

        // Gerenciador de camadas
        layerManager: {
            appendTo: '#layers-container',
        },

        // Gerenciador de estilos
        styleManager: {
            appendTo: '#styles-container',
            sectors: [
                {
                    name: 'Tipografia',
                    open: true,
                    properties: [
                        {
                            name: 'Fonte',
                            property: 'font-family',
                            type: 'select',
                            defaults: 'Arial, sans-serif',
                            options: [
                                { value: 'Arial, sans-serif', name: 'Arial' },
                                { value: 'Helvetica, sans-serif', name: 'Helvetica' },
                                { value: 'Georgia, serif', name: 'Georgia' },
                                { value: 'Times New Roman, serif', name: 'Times New Roman' },
                                { value: 'Verdana, sans-serif', name: 'Verdana' },
                                { value: "'Inter', sans-serif", name: 'Inter' },
                                { value: "'Playfair Display', serif", name: 'Playfair Display' }
                            ]
                        },
                        {
                            name: 'Tamanho',
                            property: 'font-size',
                            type: 'slider',
                            units: ['px', 'em', 'rem'],
                            defaults: '16px',
                            min: 8,
                            max: 100,
                        },
                        {
                            name: 'Peso',
                            property: 'font-weight',
                            type: 'select',
                            defaults: '400',
                            options: [
                                { value: '100', name: 'Thin' },
                                { value: '300', name: 'Light' },
                                { value: '400', name: 'Normal' },
                                { value: '500', name: 'Medium' },
                                { value: '600', name: 'Semi Bold' },
                                { value: '700', name: 'Bold' },
                                { value: '800', name: 'Extra Bold' },
                                { value: '900', name: 'Black' }
                            ]
                        },
                        {
                            name: 'Cor',
                            property: 'color',
                            type: 'color',
                        },
                        {
                            name: 'Alinhamento',
                            property: 'text-align',
                            type: 'radio',
                            defaults: 'left',
                            options: [
                                { value: 'left', name: 'Esquerda', className: 'fa fa-align-left' },
                                { value: 'center', name: 'Centro', className: 'fa fa-align-center' },
                                { value: 'right', name: 'Direita', className: 'fa fa-align-right' },
                                { value: 'justify', name: 'Justificar', className: 'fa fa-align-justify' }
                            ]
                        }
                    ]
                },
                {
                    name: 'Fundo',
                    open: false,
                    properties: [
                        {
                            name: 'Cor de Fundo',
                            property: 'background-color',
                            type: 'color',
                        },
                        {
                            name: 'Imagem de Fundo',
                            property: 'background-image',
                            type: 'file',
                        },
                        {
                            name: 'Repetir',
                            property: 'background-repeat',
                            type: 'select',
                            defaults: 'repeat',
                            options: [
                                { value: 'repeat', name: 'Repetir' },
                                { value: 'no-repeat', name: 'Não repetir' },
                                { value: 'repeat-x', name: 'Repetir X' },
                                { value: 'repeat-y', name: 'Repetir Y' }
                            ]
                        },
                        {
                            name: 'Posição',
                            property: 'background-position',
                            type: 'select',
                            defaults: '0% 0%',
                            options: [
                                { value: '0% 0%', name: 'Esquerda Superior' },
                                { value: '50% 0%', name: 'Centro Superior' },
                                { value: '100% 0%', name: 'Direita Superior' },
                                { value: '0% 50%', name: 'Esquerda Meio' },
                                { value: '50% 50%', name: 'Centro Meio' },
                                { value: '100% 50%', name: 'Direita Meio' },
                                { value: '0% 100%', name: 'Esquerda Inferior' },
                                { value: '50% 100%', name: 'Centro Inferior' },
                                { value: '100% 100%', name: 'Direita Inferior' }
                            ]
                        },
                        {
                            name: 'Tamanho',
                            property: 'background-size',
                            type: 'select',
                            defaults: 'auto',
                            options: [
                                { value: 'auto', name: 'Auto' },
                                { value: 'cover', name: 'Cobrir' },
                                { value: 'contain', name: 'Conter' }
                            ]
                        }
                    ]
                },
                {
                    name: 'Espaçamento',
                    open: false,
                    properties: [
                        {
                            name: 'Margem',
                            property: 'margin',
                            type: 'composite',
                            properties: [
                                { name: 'Topo', property: 'margin-top', type: 'slider', units: ['px', '%'], defaults: 0, min: -100, max: 500 },
                                { name: 'Direita', property: 'margin-right', type: 'slider', units: ['px', '%'], defaults: 0, min: -100, max: 500 },
                                { name: 'Inferior', property: 'margin-bottom', type: 'slider', units: ['px', '%'], defaults: 0, min: -100, max: 500 },
                                { name: 'Esquerda', property: 'margin-left', type: 'slider', units: ['px', '%'], defaults: 0, min: -100, max: 500 }
                            ]
                        },
                        {
                            name: 'Preenchimento',
                            property: 'padding',
                            type: 'composite',
                            properties: [
                                { name: 'Topo', property: 'padding-top', type: 'slider', units: ['px', '%'], defaults: 0, min: 0, max: 500 },
                                { name: 'Direita', property: 'padding-right', type: 'slider', units: ['px', '%'], defaults: 0, min: 0, max: 500 },
                                { name: 'Inferior', property: 'padding-bottom', type: 'slider', units: ['px', '%'], defaults: 0, min: 0, max: 500 },
                                { name: 'Esquerda', property: 'padding-left', type: 'slider', units: ['px', '%'], defaults: 0, min: 0, max: 500 }
                            ]
                        }
                    ]
                },
                {
                    name: 'Borda',
                    open: false,
                    properties: [
                        {
                            name: 'Tipo',
                            property: 'border-style',
                            type: 'select',
                            defaults: 'solid',
                            options: [
                                { value: 'none', name: 'Nenhum' },
                                { value: 'solid', name: 'Sólido' },
                                { value: 'dashed', name: 'Tracejado' },
                                { value: 'dotted', name: 'Pontilhado' },
                                { value: 'double', name: 'Dupla' },
                                { value: 'groove', name: 'Ranhura' },
                                { value: 'ridge', name: 'Crista' },
                                { value: 'inset', name: 'Interno' },
                                { value: 'outset', name: 'Externo' }
                            ]
                        },
                        {
                            name: 'Largura',
                            property: 'border-width',
                            type: 'slider',
                            units: ['px'],
                            defaults: 1,
                            min: 0,
                            max: 20,
                        },
                        {
                            name: 'Cor',
                            property: 'border-color',
                            type: 'color',
                            defaults: 'black',
                        },
                        {
                            name: 'Raio',
                            property: 'border-radius',
                            type: 'slider',
                            units: ['px', '%'],
                            defaults: 0,
                            min: 0,
                            max: 100,
                        }
                    ]
                },
                {
                    name: 'Dimensões',
                    open: false,
                    properties: [
                        {
                            name: 'Largura',
                            property: 'width',
                            type: 'slider',
                            units: ['px', '%', 'vw'],
                            defaults: 'auto',
                            min: 0,
                            max: 1920,
                        },
                        {
                            name: 'Altura',
                            property: 'height',
                            type: 'slider',
                            units: ['px', '%', 'vh'],
                            defaults: 'auto',
                            min: 0,
                            max: 1080,
                        },
                        {
                            name: 'Largura Mín.',
                            property: 'min-width',
                            type: 'slider',
                            units: ['px', '%'],
                            defaults: 0,
                            min: 0,
                            max: 1920,
                        },
                        {
                            name: 'Altura Mín.',
                            property: 'min-height',
                            type: 'slider',
                            units: ['px', '%'],
                            defaults: 0,
                            min: 0,
                            max: 1080,
                        }
                    ]
                },
                {
                    name: 'Posição',
                    open: false,
                    properties: [
                        {
                            name: 'Tipo',
                            property: 'position',
                            type: 'select',
                            defaults: 'static',
                            options: [
                                { value: 'static', name: 'Estático' },
                                { value: 'relative', name: 'Relativo' },
                                { value: 'absolute', name: 'Absoluto' },
                                { value: 'fixed', name: 'Fixo' },
                                { value: 'sticky', name: 'Sticky' }
                            ]
                        },
                        {
                            name: 'Esquerda',
                            property: 'left',
                            type: 'slider',
                            units: ['px', '%'],
                            defaults: 'auto',
                            min: -1000,
                            max: 1000,
                        },
                        {
                            name: 'Direita',
                            property: 'right',
                            type: 'slider',
                            units: ['px', '%'],
                            defaults: 'auto',
                            min: -1000,
                            max: 1000,
                        },
                        {
                            name: 'Topo',
                            property: 'top',
                            type: 'slider',
                            units: ['px', '%'],
                            defaults: 'auto',
                            min: -1000,
                            max: 1000,
                        },
                        {
                            name: 'Inferior',
                            property: 'bottom',
                            type: 'slider',
                            units: ['px', '%'],
                            defaults: 'auto',
                            min: -1000,
                            max: 1000,
                        }
                    ]
                }
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
        ],
        
        pluginsOpts: {
            'grapesjs-plugin-forms': {},
            'grapesjs-tailwind': {
                tailwindConfig: {
                    theme: {
                        extend: {
                            colors: {
                                primary: '#ff7a00',
                                darknav: '#2e2e2e',
                                lightgray: '#d9d9d9'
                            },
                            fontFamily: {
                                serif: ['Playfair Display', 'serif'],
                                sans: ['Inter', 'sans-serif']
                            }
                        }
                    }
                }
            },
            'grapesjs-preset-webpage': {},
            'grapesjs-blocks-basic': {},
            'grapesjs-plugin-export': {},
            'grapesjs-custom-code': {},
            'grapesjs-blocks-flexbox': {},
            'grapesjs-templates-manager': {},
            'grapesjs-blocks-bootstrap5': {},
            'grapesjs-plugin-toolbox': {},
            'grapesjs-symbols': {},
            'grapesjs-plugin-absolute': {
                positionFixed: false,
                keepBlockSelected: true
            }
        },
        
        canvas: {
            styles: [
                'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
                'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;800&family=Inter:wght@400;500;600&display=swap',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
            ],
            scripts: [
                'https://cdn.tailwindcss.com'
            ]
        },
        
        deviceManager: {
            devices: [
                {
                    name: 'Desktop',
                    width: '',
                },
                {
                    id: 'tablet',
                    name: 'Tablet',
                    width: '768px',
                    widthMedia: '810px'
                },
                {
                    id: 'mobile-portrait',
                    name: 'Mobile',
                    width: '320px',
                    widthMedia: '480px'
                }
            ]
        },
        
        selectorManager: {
            componentFirst: true
        },
        
        canvasCss: `
            .gjs-dashed *[data-gjs-highlightable] {
                outline: none !important;
            }
            .gjs-selected {
                outline: 2px solid #3b97e3 !important;
            }
            .gjs-block {
                width: auto !important;
                height: auto !important;
                margin: 10px !important;
            }
        `,
        
        blockManager: {
            appendTo: '#blocks-container',
            blocks: [
                // Blocos básicos
                {
                    id: 'section',
                    label: '<i class="fa fa-square-o"></i> Seção',
                    content: '<section style="min-height: 100px; padding: 20px;"><h2>Título da Seção</h2><p>Conteúdo da seção...</p></section>',
                    category: 'Básicos',
                },
                {
                    id: 'text',
                    label: '<i class="fa fa-font"></i> Texto',
                    content: '<p>Digite seu texto aqui...</p>',
                    category: 'Básicos',
                },
                {
                    id: 'heading',
                    label: '<i class="fa fa-header"></i> Título',
                    content: '<h1>Título Principal</h1>',
                    category: 'Básicos',
                },
                {
                    id: 'image',
                    label: '<i class="fa fa-image"></i> Imagem',
                    content: '<img src="https://via.placeholder.com/400x300" alt="Imagem" style="max-width:100%"/>',
                    category: 'Mídia',
                },
                {
                    id: 'video',
                    label: '<i class="fa fa-video-camera"></i> Vídeo',
                    content: '<iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allowfullscreen></iframe>',
                    category: 'Mídia',
                },
                {
                    id: 'button',
                    label: '<i class="fa fa-hand-pointer-o"></i> Botão',
                    content: '<button style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Clique Aqui</button>',
                    category: 'Componentes',
                },
                {
                    id: 'card',
                    label: '<i class="fa fa-id-card-o"></i> Card',
                    content: `
                        <div style="border: 1px solid #ddd; border-radius: 8px; padding: 16px; max-width: 300px; background: white;">
                            <img src="https://via.placeholder.com/300x200" style="width: 100%; border-radius: 4px;">
                            <h3 style="margin: 12px 0 8px;">Título do Card</h3>
                            <p style="color: #666;">Descrição do card aqui...</p>
                            <button style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px;">Saiba Mais</button>
                        </div>
                    `,
                    category: 'Componentes',
                },
                {
                    id: 'form',
                    label: '<i class="fa fa-wpforms"></i> Formulário',
                    content: `
                        <form style="padding: 20px; background: #f9f9f9; border-radius: 8px;">
                            <h3>Formulário de Contato</h3>
                            <input type="text" placeholder="Nome" style="width: 100%; padding: 8px; margin: 8px 0; border: 1px solid #ddd; border-radius: 4px;">
                            <input type="email" placeholder="Email" style="width: 100%; padding: 8px; margin: 8px 0; border: 1px solid #ddd; border-radius: 4px;">
                            <textarea placeholder="Mensagem" style="width: 100%; padding: 8px; margin: 8px 0; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                            <button style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px;">Enviar</button>
                        </form>
                    `,
                    category: 'Componentes',
                },
                // Blocos de layout
                {
                    id: 'grid-2',
                    label: '<i class="fa fa-th-large"></i> 2 Colunas',
                    content: `
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div style="background: #f0f0f0; padding: 20px; text-align: center;">Coluna 1</div>
                            <div style="background: #f0f0f0; padding: 20px; text-align: center;">Coluna 2</div>
                        </div>
                    `,
                    category: 'Layout',
                },
                {
                    id: 'grid-3',
                    label: '<i class="fa fa-th"></i> 3 Colunas',
                    content: `
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                            <div style="background: #f0f0f0; padding: 20px; text-align: center;">Coluna 1</div>
                            <div style="background: #f0f0f0; padding: 20px; text-align: center;">Coluna 2</div>
                            <div style="background: #f0f0f0; padding: 20px; text-align: center;">Coluna 3</div>
                        </div>
                    `,
                    category: 'Layout',
                },
                {
                    id: 'hero',
                    label: '<i class="fa fa-star"></i> Hero Section',
                    content: `
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 80px 20px; text-align: center;">
                            <h1 style="font-size: 3em; margin-bottom: 20px;">Título Hero</h1>
                            <p style="font-size: 1.2em; margin-bottom: 30px;">Descrição impactante aqui...</p>
                            <button style="background: white; color: #667eea; border: none; padding: 15px 30px; border-radius: 5px; font-size: 1.1em;">Começar Agora</button>
                        </div>
                    `,
                    category: 'Layout',
                },
                {
                    id: 'product-grid',
                    label: '<i class="fa fa-shopping-cart"></i> Grade de Produtos',
                    content: `
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; padding: 20px;">
                            <div style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                                <img src="https://via.placeholder.com/250x200" style="width: 100%;">
                                <div style="padding: 15px;">
                                    <h3>Produto 1</h3>
                                    <p style="color: #666;">R$ 99,90</p>
                                    <button style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px;">Comprar</button>
                                </div>
                            </div>
                            <div style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                                <img src="https://via.placeholder.com/250x200" style="width: 100%;">
                                <div style="padding: 15px;">
                                    <h3>Produto 2</h3>
                                    <p style="color: #666;">R$ 149,90</p>
                                    <button style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px;">Comprar</button>
                                </div>
                            </div>
                        </div>
                    `,
                    category: 'Layout',
                },
                // Elementos de navegação
                {
                    id: 'navbar',
                    label: '<i class="fa fa-bars"></i> Menu',
                    content: `
                        <nav style="background: #333; padding: 15px; color: white;">
                            <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto;">
                                <div style="font-size: 1.5em; font-weight: bold;">Logo</div>
                                <div>
                                    <a href="#" style="color: white; margin: 0 15px; text-decoration: none;">Home</a>
                                    <a href="#" style="color: white; margin: 0 15px; text-decoration: none;">Sobre</a>
                                    <a href="#" style="color: white; margin: 0 15px; text-decoration: none;">Serviços</a>
                                    <a href="#" style="color: white; margin: 0 15px; text-decoration: none;">Contato</a>
                                </div>
                            </div>
                        </nav>
                    `,
                    category: 'Navegação',
                },
                {
                    id: 'footer',
                    label: '<i class="fa fa-copyright"></i> Rodapé',
                    content: `
                        <footer style="background: #333; color: white; padding: 40px 20px;">
                            <div style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
                                <div>
                                    <h4>Sobre Nós</h4>
                                    <p style="color: #ccc;">Descrição da empresa...</p>
                                </div>
                                <div>
                                    <h4>Links Rápidos</h4>
                                    <ul style="list-style: none; padding: 0;">
                                        <li><a href="#" style="color: #ccc; text-decoration: none;">Link 1</a></li>
                                        <li><a href="#" style="color: #ccc; text-decoration: none;">Link 2</a></li>
                                    </ul>
                                </div>
                                <div>
                                    <h4>Contato</h4>
                                    <p style="color: #ccc;">email@exemplo.com</p>
                                </div>
                            </div>
                        </footer>
                    `,
                    category: 'Navegação',
                },
                // Elementos de mídia social
                {
                    id: 'social-icons',
                    label: '<i class="fa fa-share-alt"></i> Redes Sociais',
                    content: `
                        <div style="display: flex; gap: 15px; justify-content: center;">
                            <a href="#"><i class="fa fa-facebook" style="font-size: 2em; color: #3b5998;"></i></a>
                            <a href="#"><i class="fa fa-twitter" style="font-size: 2em; color: #1da1f2;"></i></a>
                            <a href="#"><i class="fa fa-instagram" style="font-size: 2em; color: #e4405f;"></i></a>
                            <a href="#"><i class="fa fa-linkedin" style="font-size: 2em; color: #0077b5;"></i></a>
                            <a href="#"><i class="fa fa-youtube" style="font-size: 2em; color: #ff0000;"></i></a>
                        </div>
                    `,
                    category: 'Mídia',
                },
                // Elementos de preços
                {
                    id: 'pricing-table',
                    label: '<i class="fa fa-dollar"></i> Tabela de Preços',
                    content: `
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; max-width: 1200px; margin: 0 auto; padding: 40px 20px;">
                            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; text-align: center;">
                                <h3>Básico</h3>
                                <p style="font-size: 2em; color: #007bff;">R$ 29</p>
                                <ul style="list-style: none; padding: 0; margin: 20px 0;">
                                    <li>Item 1</li>
                                    <li>Item 2</li>
                                    <li>Item 3</li>
                                </ul>
                                <button style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px;">Escolher</button>
                            </div>
                            <div style="border: 2px solid #007bff; border-radius: 8px; padding: 20px; text-align: center;">
                                <h3>Profissional</h3>
                                <p style="font-size: 2em; color: #007bff;">R$ 79</p>
                                <ul style="list-style: none; padding: 0; margin: 20px 0;">
                                    <li>Item 1</li>
                                    <li>Item 2</li>
                                    <li>Item 3</li>
                                </ul>
                                <button style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px;">Escolher</button>
                            </div>
                            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; text-align: center;">
                                <h3>Empresarial</h3>
                                <p style="font-size: 2em; color: #007bff;">R$ 199</p>
                                <ul style="list-style: none; padding: 0; margin: 20px 0;">
                                    <li>Item 1</li>
                                    <li>Item 2</li>
                                    <li>Item 3</li>
                                </ul>
                                <button style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px;">Escolher</button>
                            </div>
                        </div>
                    `,
                    category: 'Componentes',
                }
            ]
        }
    });

    editor.on('load', () => {

    // Captura todos os keydowns dentro do editor
    editor.getContainer().addEventListener('keydown', function (e) {

        // Verifica se é Enter
        if (e.key === 'Enter') {

        // Verifica se está dentro do campo de classe
        const isClassInput = e.target.closest('.gjs-sm-field, .gjs-clm-field');

        if (isClassInput) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        }

    }, true);

    });


    <?php 
        $tipo = $blog['tipo'] ?? 'empresa';
        ?>

        // Blocos universais
        editor.BlockManager.add('dynamic-container', {
            label: 'Container Dinâmico',
            content: '<div class="dynamic-container p-4 border-2 border-dashed border-blue-300" data-dynamic="custom" data-limit="5"><p class="text-center text-gray-500">Área dinâmica - configure itens no painel</p></div>',
            category: 'Blocos Dinâmicos',
        });

        // Blocos específicos por tipo
        <?php if ($tipo == 'blog'): ?>
        editor.BlockManager.add('blog-posts', {
            label: 'Lista de Posts',
            content: '<div class="blog-posts-wrapper" data-dynamic="blog_posts" data-limit="5" data-template="default"><p class="text-center text-gray-500">[Lista de posts será exibida aqui]</p></div>',
            category: 'Blog',
        });

        editor.BlockManager.add('blog-destaque', {
            label: 'Post em Destaque',
            content: '<div class="blog-destaque-wrapper" data-dynamic="blog_destaque"><p class="text-center text-gray-500">[Post mais visto]</p></div>',
            category: 'Blog',
        });
        <?php endif; ?>

        <?php if ($tipo == 'noticias'): ?>
        editor.BlockManager.add('noticias-lista', {
            label: 'Lista de Notícias',
            content: '<div class="noticias-lista-wrapper" data-dynamic="noticias_lista" data-limit="5"><p class="text-center text-gray-500">[Últimas notícias]</p></div>',
            category: 'Notícias',
        });

        editor.BlockManager.add('noticias-destaque', {
            label: 'Notícia em Destaque',
            content: '<div class="noticias-destaque-wrapper" data-dynamic="noticias_destaque"><p class="text-center text-gray-500">[Notícia em destaque]</p></div>',
            category: 'Notícias',
        });

        editor.BlockManager.add('noticias-categorias', {
            label: 'Categorias de Notícias',
            content: '<div class="noticias-categorias-wrapper" data-dynamic="noticias_categorias"><p class="text-center text-gray-500">[Lista de categorias]</p></div>',
            category: 'Notícias',
        });
        <?php endif; ?>

        <?php if ($tipo == 'classificados'): ?>
        editor.BlockManager.add('classificados-lista', {
            label: 'Lista de Anúncios',
            content: '<div class="classificados-lista-wrapper" data-dynamic="classificados_lista" data-limit="6"><p class="text-center text-gray-500">[Anúncios recentes]</p></div>',
            category: 'Classificados',
        });

        editor.BlockManager.add('classificados-destaque', {
            label: 'Anúncio em Destaque',
            content: '<div class="classificados-destaque-wrapper" data-dynamic="classificados_destaque"><p class="text-center text-gray-500">[Anúncio mais visto]</p></div>',
            category: 'Classificados',
        });
        <?php endif; ?>

    // Configurar comandos para dispositivos
    editor.Commands.add('set-device-desktop', {
        run: editor => editor.setDevice('Desktop')
    });
    editor.Commands.add('set-device-tablet', {
        run: editor => editor.setDevice('Tablet')
    });
    editor.Commands.add('set-device-mobile', {
        run: editor => editor.setDevice('Mobile portrait')
    });

    // Configurações adicionais para posicionamento livre
    editor.on('load', function() {
        // Desativa o snap to grid
        editor.Canvas.getModel().set('snap', false);

        // Injeta CSS personalizado no canvas para as cores personalizadas
        const canvas = editor.Canvas;
        const frame = canvas.getFrameEl();
        
        if (frame) {
            const doc = frame.contentDocument;
            if (doc) {
                const style = doc.createElement('style');
                style.textContent = `
                    .bg-primary { background-color: #ff7a00 !important; }
                    .bg-darknav { background-color: #2e2e2e !important; }
                    .bg-lightgray { background-color: #d9d9d9 !important; }
                    .text-primary { color: #ff7a00 !important; }
                    .font-serif { font-family: 'Playfair Display', serif !important; }
                    .font-sans { font-family: 'Inter', sans-serif !important; }
                `;
                doc.head.appendChild(style);
            }
        }

        // Configura todos os componentes como arrastáveis
        editor.DomComponents.getTypes().map(type => {
            editor.DomComponents.addType(type.id, {
                model: {
                    defaults: {
                        draggable: true,
                        resizable: true,
                        style: {
                            position: 'relative',
                            'min-height': 'auto'
                        }
                    }
                }
            });
        });

        // Atualiza o estilo quando um componente é selecionado
        editor.on('component:selected', function(model) {
            model.set('draggable', true);
            model.set('resizable', true);
        });
    });

    // Carregar o conteúdo do blog APÓS o editor ser inicializado
    <?php if (!empty($blog['conteudo'])): ?>
        editor.setComponents(`<?php echo addslashes($blog['conteudo']); ?>`);
    <?php endif; ?>
    
    <?php if (!empty($blog['css'])): ?>
        editor.setStyle(`<?php echo addslashes($blog['css']); ?>`);
    <?php endif; ?>

    // Manipulador do formulário
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
                console.error('Erro ao salvar conteúdo:', error);
            }
        });
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

    <section class="bg-[#488BC2] shadow-md w-full">
        <div class="max-w-7xl mx-auto px-4">
            <div class="py-4 flex flex-col md:flex-row justify-between items-center">

                <div class="flex flex-col md:flex-row items-center gap-6 w-full md:w-auto">

                    <div class="w-64">
                        <img src="imagens/eyefind-logo.png" alt="Eyefind.info Logo" class="w-full">
                    </div>
                </div>

                <div class="flex items-center gap-6 mt-4 md:mt-0">

                    <div class="relative group">
                        <a href="manage_blogs.php"
                           class="p-3 w-12 h-12 flex items-center justify-center hover:scale-110 transition duration-200">
                            <img src="icon/voltar1.png"
                                 class="w-8 h-8 object-contain"
                                 alt="Voltar">
                        </a>

                        <div class="absolute -bottom-10 left-1/2 -translate-x-1/2 
                                    bg-black text-white text-xs px-3 py-1 rounded
                                    opacity-0 group-hover:opacity-100 transition
                                    pointer-events-none whitespace-nowrap">
                            Voltar
                        </div>
                    </div>

                    <div class="relative group">
                        <a href="logout.php"
                           class="p-3 w-12 h-12 flex items-center justify-center hover:scale-110 transition duration-200">
                            <img src="icon/logout1.png"
                                 class="w-8 h-8 object-contain"
                                 alt="Logout">
                        </a>

                        <div class="absolute -bottom-10 left-1/2 -translate-x-1/2 
                                    bg-black text-white text-xs px-3 py-1 rounded
                                    opacity-0 group-hover:opacity-100 transition
                                    pointer-events-none whitespace-nowrap">
                            Logout
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </section>


        <div class="w-full h-2 bg-yellow-400"></div>


<section class="mt-1 bg-white p-6 shadow-md">
    <h2 class="text-2xl font-bold text-eyefind-blue mb-6">Editar Blog</h2>

    <form action="edit_blog.php?id=<?php echo $blog['id']; ?>" method="POST">

        <!-- Nome -->
        <div class="mb-4">
            <label for="nome" class="block text-eyefind-dark font-bold mb-2">Nome do Blog</label>
            <input type="text"
                   name="nome"
                   id="nome"
                   class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue"
                   value="<?php echo htmlspecialchars($blog['nome']); ?>"
                   required>
        </div>

        <!-- Descrição -->
        <div class="mb-4">
            <label for="descricao" class="block text-eyefind-dark font-bold mb-2">Descrição</label>
            <textarea name="descricao"
                      id="descricao"
                      class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue"
                      required><?php echo htmlspecialchars($blog['descricao']); ?></textarea>
        </div>

        <!-- Imagem -->
        <div class="mb-4">
            <label for="imagem_url" class="block text-eyefind-dark font-bold mb-2">URL da Imagem do Blog</label>
            <input type="url"
                   name="imagem_url"
                   id="imagem_url"
                   class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue"
                   value="<?php echo htmlspecialchars($blog['imagem']); ?>"
                   oninput="previewImage()"
                   required>

            <div id="image-preview" class="mt-2">
                <?php if ($blog['imagem']): ?>
                    <img src="<?php echo htmlspecialchars($blog['imagem']); ?>"
                         alt="Pré-visualização da imagem"
                         class="w-full h-48 object-cover rounded">
                <?php endif; ?>
            </div>
        </div>

        <!-- Categoria -->
        <div class="mb-6">
            <label for="categoria_id" class="block text-eyefind-dark font-bold mb-2">Categoria</label>
            <select name="categoria_id"
                    id="categoria_id"
                    class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue"
                    required>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?php echo $categoria['id']; ?>"
                        <?php echo $categoria['id'] == $blog['categoria_id'] ? 'selected' : ''; ?>>
                        <?php echo $categoria['nome']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Editor Completo -->
        <div class="mb-6">
            <label class="block text-eyefind-dark font-bold mb-4">Conteúdo do Blog</label>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

                <!-- Painel Esquerdo -->
                <div class="lg:col-span-1">
                    <div class="bg-gray-100 p-4 rounded-lg shadow">
                        <h3 class="text-lg font-bold text-eyefind-blue mb-4">Elementos</h3>
                        <div id="blocks-container" class="space-y-2 max-h-[600px] overflow-y-auto"></div>
                    </div>

                    <div class="bg-gray-100 p-4 rounded-lg mt-4 shadow">
                        <h3 class="text-lg font-bold text-eyefind-blue mb-4">Camadas</h3>
                        <div id="layers-container" class="max-h-[300px] overflow-y-auto"></div>
                    </div>
                </div>

                <!-- Editor Central -->
                <div class="lg:col-span-2">
                    <div class="bg-white border border-gray-200 rounded-lg shadow">
                        <div class="panel__basic-actions border-b border-gray-200 p-2 flex gap-2"></div>
                        <div class="panel__devices border-b border-gray-200 p-2 flex gap-2"></div>

                        <div id="grapesjs-editor" style="height: 700px;"></div>
                    </div>
                </div>

                <!-- Painel Direito -->
                <div class="lg:col-span-1">
                    <div class="bg-gray-100 p-4 rounded-lg shadow">
                        <h3 class="text-lg font-bold text-eyefind-blue mb-4">Estilos</h3>
                        <div id="styles-container" class="max-h-[700px] overflow-y-auto"></div>
                    </div>
                </div>

            </div>

            <!-- Campos ocultos -->
            <input type="hidden"
                   name="conteudo"
                   id="conteudo"
                   value="<?php echo htmlspecialchars($blog['conteudo']); ?>">

            <input type="hidden"
                   name="css"
                   id="css"
                   value="<?php echo htmlspecialchars($blog['css']); ?>">
        </div>

        <!-- Botão -->
        <div class="flex justify-end">
            <button type="submit"
                    class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700 transition">
                Salvar Alterações
            </button>
        </div>

    </form>
</section>


</html>

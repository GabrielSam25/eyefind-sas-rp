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
            'grapesjs-plugin-absolute'
        ],
        pluginsOpts: {
            'grapesjs-plugin-forms': {},
            'grapesjs-tailwind': {
                // Configuração do Tailwind
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
                'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.js'
            ]
        },
        deviceManager: {
            devices: [{
                name: 'Desktop',
                width: '',
            }]
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
            blocks: [{
                    id: 'text',
                    label: 'Texto',
                    content: {
                        tagName: 'div',
                        style: {
                            position: 'absolute',
                            left: '50px',
                            top: '50px',
                            padding: '10px',
                            'min-width': '50px',
                            'min-height': '20px'
                        },
                        content: '<p>Insira seu texto aqui...</p>'
                    },
                    attributes: {
                        class: 'gjs-block-section'
                    }
                },
                {
                    id: 'heading',
                    label: 'Título',
                    content: {
                        tagName: 'div',
                        style: {
                            position: 'absolute',
                            left: '50px',
                            top: '100px',
                            padding: '10px',
                            'min-width': '50px'
                        },
                        content: '<h1>Insira um título</h1>'
                    }
                },
                {
                    id: 'image',
                    label: 'Imagem',
                    content: {
                        tagName: 'div',
                        style: {
                            position: 'absolute',
                            left: '50px',
                            top: '150px',
                            padding: '10px'
                        },
                        content: '<img src="https://via.placeholder.com/400x200" alt="Imagem" style="max-width:100%">'
                    }
                },
                {
                    id: 'video',
                    label: 'Vídeo',
                    content: {
                        tagName: 'div',
                        style: {
                            position: 'absolute',
                            left: '50px',
                            top: '200px',
                            padding: '10px'
                        },
                        content: '<iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allowfullscreen></iframe>'
                    }
                },
                {
                    id: 'button',
                    label: 'Botão',
                    content: {
                        tagName: 'div',
                        style: {
                            position: 'absolute',
                            left: '50px',
                            top: '250px',
                            padding: '10px'
                        },
                        content: '<button class="bg-blue-500 text-white px-4 py-2 rounded">Clique aqui</button>'
                    }
                },
                {
                    id: 'form',
                    label: 'Formulário',
                    content: {
                        tagName: 'div',
                        style: {
                            position: 'absolute',
                            left: '50px',
                            top: '300px',
                            padding: '10px'
                        },
                        content: '<form><input type="text" placeholder="Nome"><button type="submit">Enviar</button></form>'
                    }
                },
                {
                    id: 'icon',
                    label: 'Ícone',
                    content: {
                        tagName: 'div',
                        style: {
                            position: 'absolute',
                            left: '50px',
                            top: '350px',
                            padding: '10px'
                        },
                        content: '<i class="fas fa-star"></i>'
                    }
                }
            ]
        },
        styleManager: {
            sectors: [{
                    name: 'Posição',
                    open: true,
                    properties: [{
                            type: 'integer',
                            property: 'left',
                            units: ['px', '%'],
                            defaults: 'auto',
                            label: 'Esquerda',
                        },
                        {
                            type: 'integer',
                            property: 'top',
                            units: ['px', '%'],
                            defaults: 'auto',
                            label: 'Topo',
                        },
                        {
                            type: 'integer',
                            property: 'width',
                            units: ['px', '%', 'auto'],
                            defaults: 'auto',
                            label: 'Largura',
                        },
                        {
                            type: 'integer',
                            property: 'height',
                            units: ['px', '%', 'auto'],
                            defaults: 'auto',
                            label: 'Altura',
                        },
                        {
                            type: 'select',
                            property: 'position',
                            defaults: 'absolute',
                            label: 'Posição',
                            options: [{
                                    value: 'absolute',
                                    label: 'Absoluta'
                                },
                                {
                                    value: 'relative',
                                    label: 'Relativa'
                                },
                                {
                                    value: 'fixed',
                                    label: 'Fixa'
                                },
                            ],
                        }
                    ]
                },
                {
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
                }
            ]
        }
    });

    // Configurações adicionais para posicionamento livre
    editor.on('load', function() {
        // Desativa o snap to grid
        editor.Canvas.getModel().set('snap', false);

        // Configura todos os componentes como arrastáveis
        editor.DomComponents.getTypes().map(type => {
            editor.DomComponents.addType(type.id, {
                model: {
                    defaults: {
                        draggable: true,
                        resizable: true,
                        style: {
                            position: 'absolute',
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

            // Garante que o componente tenha posição absoluta
            const style = model.get('style');
            if (!style.position || style.position !== 'absolute') {
                model.set('style', {
                    ...style,
                    position: 'absolute',
                    left: style.left || '50px',
                    top: style.top || '50px'
                });
            }
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
    <div class="max-w-7xl mx-auto">
        <section class="bg-[#488BC2] shadow-md">
            <div class="p-4 flex flex-col md:flex-row justify-between items-center">
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
            <form action="edit_blog.php?id=<?php echo $blog['id']; ?>" method="POST">
                <div class="mb-4">
                    <label for="nome" class="block text-eyefind-dark font-bold mb-2">Nome do Blog</label>
                    <input type="text" name="nome" id="nome" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" value="<?php echo htmlspecialchars($blog['nome']); ?>" required>
                </div>
                <div class="mb-4">
                    <label for="descricao" class="block text-eyefind-dark font-bold mb-2">Descrição</label>
                    <textarea name="descricao" id="descricao" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" required><?php echo htmlspecialchars($blog['descricao']); ?></textarea>
                </div>
                <div class="mb-4">
                    <label for="imagem_url" class="block text-eyefind-dark font-bold mb-2">URL da Imagem do Blog</label>
                    <input type="url" name="imagem_url" id="imagem_url" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" value="<?php echo htmlspecialchars($blog['imagem']); ?>" oninput="previewImage()" required>
                    <div id="image-preview" class="mt-2">
                        <?php if ($blog['imagem']): ?>
                            <img src="<?php echo htmlspecialchars($blog['imagem']); ?>" alt="Pré-visualização da imagem" class="w-full h-48 object-cover rounded">
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
                    <label for="conteudo" class="block text-eyefind-dark font-bold mb-2">Conteúdo do Blog</label>
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

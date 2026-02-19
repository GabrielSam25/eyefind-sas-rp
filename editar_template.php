<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$website_id = intval($_GET['website_id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'noticia'; // post, noticia, anuncio

if (!$website_id || !in_array($tipo, ['post', 'noticia', 'anuncio'])) {
    header('Location: manage_blogs.php');
    exit;
}

$usuario = getUsuarioAtual($pdo);

// Verificar permissão (dono ou colaborador com nível adequado)
$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$website_id, $usuario['id']]);
$website = $stmt->fetch(PDO::FETCH_ASSOC);

$is_dono = !empty($website);
$nivel_colaborador = '';

if (!$is_dono) {
    $stmt = $pdo->prepare("SELECT nivel FROM site_colaboradores WHERE website_id = ? AND usuario_id = ? AND nivel IN ('admin', 'editor')");
    $stmt->execute([$website_id, $usuario['id']]);
    $colaborador = $stmt->fetch();
    
    if (!$colaborador) {
        header('Location: manage_blogs.php?msg=sem_permissao');
        exit;
    }
    $nivel_colaborador = $colaborador['nivel'];
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
    $stmt->execute([$website_id]);
    $website = $stmt->fetch();
}

// Buscar template existente
$stmt = $pdo->prepare("SELECT * FROM templates_visualizacao WHERE website_id = ? AND tipo = ?");
$stmt->execute([$website_id, $tipo]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

// Processar salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $html = $_POST['html'];
    $css = $_POST['css'] ?? '';
    
    if ($template) {
        $stmt = $pdo->prepare("UPDATE templates_visualizacao SET html = ?, css = ? WHERE id = ?");
        $stmt->execute([$html, $css, $template['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO templates_visualizacao (website_id, tipo, html, css) VALUES (?, ?, ?, ?)");
        $stmt->execute([$website_id, $tipo, $html, $css]);
    }
    
    header('Location: manage_blogs.php?msg=template_salvo');
    exit;
}

// Templates de exemplo para cada tipo
$exemplos = [
    'post' => [
        'nome' => 'Post de Blog',
        'html' => '<article class="max-w-3xl mx-auto p-6">
    <h1 class="text-4xl font-bold mb-4 dynamic-titulo"></h1>
    <div class="flex items-center text-gray-500 mb-6">
        <span class="dynamic-autor-nome"></span> • 
        <span class="dynamic-data ml-2"></span> • 
        <span class="dynamic-views ml-2"></span> visualizações
    </div>
    <img class="w-full h-96 object-cover rounded-lg mb-8 dynamic-imagem" src="">
    <div class="prose max-w-none dynamic-conteudo"></div>
</article>',
        'css' => '.prose { font-size: 1.125rem; line-height: 1.8; }'
    ],
    'noticia' => [
        'nome' => 'Notícia',
        'html' => '<article class="max-w-4xl mx-auto p-6">
    <div class="mb-4">
        <span class="inline-block bg-red-600 text-white px-3 py-1 rounded-full text-sm dynamic-categoria"></span>
    </div>
    <h1 class="text-5xl font-bold mb-4 dynamic-titulo"></h1>
    <div class="flex items-center text-gray-500 mb-8">
        <span class="dynamic-autor-nome"></span> • 
        <span class="dynamic-data ml-2"></span> • 
        <span class="dynamic-views ml-2"></span> visualizações
    </div>
    <img class="w-full h-[500px] object-cover rounded-xl mb-8 dynamic-imagem" src="">
    <div class="text-lg leading-relaxed dynamic-conteudo"></div>
</article>',
        'css' => 'body { background: #f3f4f6; } article { background: white; border-radius: 1rem; }'
    ],
    'anuncio' => [
        'nome' => 'Anúncio Classificado',
        'html' => '<div class="max-w-4xl mx-auto p-6">
    <div class="grid md:grid-cols-2 gap-8">
        <div>
            <img class="w-full rounded-lg shadow-lg dynamic-imagem" src="">
        </div>
        <div>
            <h1 class="text-4xl font-bold mb-4 dynamic-titulo"></h1>
            <p class="text-3xl text-green-600 font-bold mb-4 dynamic-preco"></p>
            <div class="space-y-3 mb-6">
                <p><span class="font-bold">Categoria:</span> <span class="dynamic-categoria"></span></p>
                <p><span class="font-bold">Contato:</span> <span class="dynamic-contato"></span></p>
                <p><span class="font-bold">Telefone:</span> <span class="dynamic-telefone"></span></p>
                <p><span class="font-bold">Email:</span> <a class="dynamic-email text-blue-600" href=""></a></p>
            </div>
            <div class="border-t pt-6">
                <h2 class="text-xl font-bold mb-4">Descrição</h2>
                <p class="text-gray-700 dynamic-conteudo"></p>
            </div>
        </div>
    </div>
</div>',
        'css' => ''
    ]
];

$exemplo = $exemplos[$tipo];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Template - <?php echo $exemplo['nome']; ?> - <?php echo htmlspecialchars($website['nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
    <style>
        .editor-panel {
            transition: all 0.3s;
        }
        .preview-frame {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            min-height: 400px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-7xl mx-auto py-8 px-4">
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-blue-600">Editar Template: <?php echo $exemplo['nome']; ?></h1>
                    <p class="text-gray-600">Site: <?php echo htmlspecialchars($website['nome']); ?></p>
                </div>
                <a href="manage_blogs.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Editor -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-lg font-bold mb-4">Editor do Template</h2>
                
                <form method="POST" id="formTemplate">
                    <div class="mb-4">
                        <label class="block font-bold mb-2">HTML do Template</label>
                        <textarea name="html" id="htmlEditor" class="w-full h-96 font-mono text-sm border rounded p-2"><?php echo htmlspecialchars($template['html'] ?? $exemplo['html']); ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block font-bold mb-2">CSS Personalizado (opcional)</label>
                        <textarea name="css" id="cssEditor" class="w-full h-32 font-mono text-sm border rounded p-2"><?php echo htmlspecialchars($template['css'] ?? $exemplo['css']); ?></textarea>
                    </div>
                    
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="carregarExemplo()" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">
                            <i class="fas fa-undo mr-2"></i>Carregar Exemplo
                        </button>
                        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">
                            <i class="fas fa-save mr-2"></i>Salvar Template
                        </button>
                    </div>
                </form>
            </div>

            <!-- Preview e Ajuda -->
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-lg font-bold mb-4">Prévia</h2>
                    <div class="preview-frame" id="preview">
                        <!-- Preview será atualizado via JavaScript -->
                    </div>
                    <button type="button" onclick="atualizarPreview()" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-sync-alt mr-2"></i>Atualizar Prévia
                    </button>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-lg font-bold mb-4">Classes Dinâmicas Disponíveis</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <?php
                        $classes = [
                            'dynamic-titulo' => 'Título',
                            'dynamic-conteudo' => 'Conteúdo (HTML)',
                            'dynamic-resumo' => 'Resumo',
                            'dynamic-imagem' => 'Imagem',
                            'dynamic-data' => 'Data',
                            'dynamic-data-completa' => 'Data completa',
                            'dynamic-autor-nome' => 'Nome do autor',
                            'dynamic-categoria' => 'Categoria',
                            'dynamic-views' => 'Visualizações',
                            'dynamic-ano' => 'Ano atual',
                            'dynamic-preco' => 'Preço (anúncios)',
                            'dynamic-contato' => 'Contato (anúncios)',
                            'dynamic-telefone' => 'Telefone (anúncios)',
                            'dynamic-email' => 'Email (anúncios)'
                        ];
                        
                        foreach ($classes as $classe => $desc): ?>
                            <div class="bg-gray-50 p-2 rounded border">
                                <code class="text-sm text-blue-600"><?php echo $classe; ?></code>
                                <p class="text-xs text-gray-500"><?php echo $desc; ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Função para atualizar a prévia
        function atualizarPreview() {
            const html = document.getElementById('htmlEditor').value;
            const css = document.getElementById('cssEditor').value;
            const preview = document.getElementById('preview');
            
            // Dados de exemplo para prévia
            const dadosExemplo = {
                'titulo': 'Exemplo de Título da Notícia',
                'conteudo': '<p>Este é um exemplo de <strong>conteúdo</strong> com formatação HTML.</p>',
                'resumo': 'Este é um resumo de exemplo para a prévia...',
                'imagem': 'https://via.placeholder.com/800x400',
                'data_publicacao': '2026-02-17',
                'autor_nome': 'João Silva',
                'categoria': 'Tecnologia',
                'views': '1234',
                'preco': '99.90',
                'contato': 'Maria Santos',
                'telefone': '(11) 99999-9999',
                'email': 'contato@exemplo.com'
            };
            
            // Função simples de substituição para prévia
            let previewHtml = html;
            previewHtml = previewHtml.replace(/dynamic-titulo/g, 'dynamic-titulo-preview');
            previewHtml = previewHtml.replace(/dynamic-conteudo/g, 'dynamic-conteudo-preview');
            previewHtml = previewHtml.replace(/dynamic-resumo/g, 'dynamic-resumo-preview');
            previewHtml = previewHtml.replace(/dynamic-imagem/g, 'dynamic-imagem-preview');
            previewHtml = previewHtml.replace(/dynamic-data/g, 'dynamic-data-preview');
            previewHtml = previewHtml.replace(/dynamic-data-completa/g, 'dynamic-data-completa-preview');
            previewHtml = previewHtml.replace(/dynamic-autor-nome/g, 'dynamic-autor-nome-preview');
            previewHtml = previewHtml.replace(/dynamic-categoria/g, 'dynamic-categoria-preview');
            previewHtml = previewHtml.replace(/dynamic-views/g, 'dynamic-views-preview');
            previewHtml = previewHtml.replace(/dynamic-ano/g, 'dynamic-ano-preview');
            previewHtml = previewHtml.replace(/dynamic-preco/g, 'dynamic-preco-preview');
            previewHtml = previewHtml.replace(/dynamic-contato/g, 'dynamic-contato-preview');
            previewHtml = previewHtml.replace(/dynamic-telefone/g, 'dynamic-telefone-preview');
            previewHtml = previewHtml.replace(/dynamic-email/g, 'dynamic-email-preview');
            
            // Adicionar CSS
            preview.innerHTML = `<style>${css}</style>${previewHtml}`;
            
            // Preencher com dados de exemplo
            document.querySelectorAll('.dynamic-titulo-preview').forEach(el => el.textContent = dadosExemplo.titulo);
            document.querySelectorAll('.dynamic-conteudo-preview').forEach(el => el.innerHTML = dadosExemplo.conteudo);
            document.querySelectorAll('.dynamic-resumo-preview').forEach(el => el.textContent = dadosExemplo.resumo);
            document.querySelectorAll('.dynamic-imagem-preview').forEach(el => {
                if (el.tagName === 'IMG') el.src = dadosExemplo.imagem;
            });
            document.querySelectorAll('.dynamic-data-preview').forEach(el => el.textContent = '17/02/2026');
            document.querySelectorAll('.dynamic-data-completa-preview').forEach(el => el.textContent = 'Tuesday, February 17, 2026');
            document.querySelectorAll('.dynamic-autor-nome-preview').forEach(el => el.textContent = dadosExemplo.autor_nome);
            document.querySelectorAll('.dynamic-categoria-preview').forEach(el => el.textContent = dadosExemplo.categoria);
            document.querySelectorAll('.dynamic-views-preview').forEach(el => el.textContent = dadosExemplo.views);
            document.querySelectorAll('.dynamic-ano-preview').forEach(el => el.textContent = '2026');
            document.querySelectorAll('.dynamic-preco-preview').forEach(el => el.textContent = 'R$ 99,90');
            document.querySelectorAll('.dynamic-contato-preview').forEach(el => el.textContent = dadosExemplo.contato);
            document.querySelectorAll('.dynamic-telefone-preview').forEach(el => el.textContent = dadosExemplo.telefone);
            document.querySelectorAll('.dynamic-email-preview').forEach(el => {
                if (el.tagName === 'A') {
                    el.href = 'mailto:' + dadosExemplo.email;
                    el.textContent = dadosExemplo.email;
                } else {
                    el.textContent = dadosExemplo.email;
                }
            });
        }

        function carregarExemplo() {
            document.getElementById('htmlEditor').value = <?php echo json_encode($exemplo['html']); ?>;
            document.getElementById('cssEditor').value = <?php echo json_encode($exemplo['css']); ?>;
            atualizarPreview();
        }

        // Atualizar prévia ao carregar
        window.addEventListener('load', atualizarPreview);
    </script>
</body>
</html>
<?php
session_start();

$db_host = 'localhost';
$db_name = 'eyefind';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

function isLogado()
{
    return isset($_SESSION['usuario_id']);
}

function usuarioTemBlog($pdo, $usuario_id)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM websites WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    return $stmt->fetchColumn() > 0;
}

function getUsuarioAtual($pdo)
{
    if (!isLogado()) return null;

    $stmt = $pdo->prepare("SELECT id, nome, email, is_admin FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getBlogDoUsuario($pdo, $usuario_id)
{
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE usuario_id = ? LIMIT 1");
    $stmt->execute([$usuario_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCategorias($pdo)
{
    $stmt = $pdo->query("SELECT * FROM categorias ORDER BY ordem");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getWebsiteDoMinuto($pdo)
{
    $stmt = $pdo->query("
        SELECT * 
        FROM websites 
        WHERE status = 'approved' 
        ORDER BY RAND() 
        LIMIT 1
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


function getNoticiaDestaque($pdo)
{
    $stmt = $pdo->query("SELECT * FROM noticias WHERE destaque = 1 LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getBleets($pdo)
{
    $stmt = $pdo->query("SELECT * FROM bleets LIMIT 5");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPublicidadeAtiva($pdo)
{
    $stmt = $pdo->query("SELECT * FROM publicidade WHERE ativo = 1 ORDER BY RAND() LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getWebsites($pdo, $categoria_id = null, $limite = 4)
{
    $sql = "SELECT * FROM websites WHERE status = 'approved'";
    $params = [];

    if ($categoria_id) {
        $sql .= " AND categoria_id = ?";
        $params[] = $categoria_id;
    }

    $sql .= " ORDER BY id DESC, destaque DESC, ordem ASC LIMIT " . intval($limite);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCategoriaById($pdo, $id)
{
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getWebsitesByCategoria($pdo, $categoria_id)
{
    $stmt = $pdo->prepare("
        SELECT * 
        FROM websites 
        WHERE categoria_id = ? 
        AND status = 'approved'
    ");
    $stmt->execute([$categoria_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTipoSite($pdo, $website_id) {
    $stmt = $pdo->prepare("SELECT tipo FROM websites WHERE id = ?");
    $stmt->execute([$website_id]);
    return $stmt->fetchColumn();
}

// Funções para Blog
function getBlogPosts($pdo, $website_id, $limit = 10, $offset = 0) {
    $stmt = $pdo->prepare("
        SELECT * FROM blog_posts 
        WHERE website_id = ? AND status = 'publicado' 
        ORDER BY data_publicacao DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$website_id, $limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBlogPost($pdo, $post_id) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$post_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getBlogPostBySlug($pdo, $website_id, $slug) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE website_id = ? AND slug = ?");
    $stmt->execute([$website_id, $slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function criarBlogPost($pdo, $website_id, $dados) {
    $slug = criarSlug($dados['titulo']);
    $stmt = $pdo->prepare("
        INSERT INTO blog_posts (website_id, titulo, slug, conteudo, resumo, imagem, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([
        $website_id,
        $dados['titulo'],
        $slug,
        $dados['conteudo'],
        $dados['resumo'] ?? null,
        $dados['imagem'] ?? null,
        $dados['status'] ?? 'rascunho'
    ]);
}

// Funções para Notícias
function getNoticiasArtigos($pdo, $website_id, $categoria = null, $limit = 10) {
    $sql = "SELECT * FROM noticias_artigos WHERE website_id = ? AND status = 'publicado'";
    $params = [$website_id];
    
    if ($categoria) {
        $sql .= " AND categoria = ?";
        $params[] = $categoria;
    }
    
    $sql .= " ORDER BY destaque DESC, data_publicacao DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNoticiaDestaque($pdo, $website_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM noticias_artigos 
        WHERE website_id = ? AND status = 'publicado' AND destaque = 1 
        ORDER BY data_publicacao DESC LIMIT 1
    ");
    $stmt->execute([$website_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Funções para Classificados
function getClassificadosAnuncios($pdo, $website_id, $categoria = null, $limit = 20) {
    $sql = "SELECT * FROM classificados_anuncios WHERE website_id = ? AND status = 'ativo'";
    $params = [$website_id];
    
    if ($categoria) {
        $sql .= " AND categoria = ?";
        $params[] = $categoria;
    }
    
    $sql .= " ORDER BY data_criacao DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função auxiliar para criar slug
function criarSlug($texto) {
    $texto = preg_replace('~[^\pL\d]+~u', '-', $texto);
    $texto = iconv('utf-8', 'us-ascii//TRANSLIT', $texto);
    $texto = preg_replace('~[^-\w]+~', '', $texto);
    $texto = trim($texto, '-');
    $texto = preg_replace('~-+~', '-', $texto);
    $texto = strtolower($texto);
    return empty($texto) ? 'post-' . time() : $texto;
}

// Função para renderizar blocos dinâmicos
function renderDynamicBlocks($html, $website_id, $pdo) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//*[@data-dynamic]");
    
    foreach ($nodes as $node) {
        $type = $node->getAttribute('data-dynamic');
        $limit = $node->getAttribute('data-limit') ?: 5;
        $template = $node->getAttribute('data-template') ?: 'default';
        $class = $node->getAttribute('class');
        
        $generatedHtml = gerarConteudoDinamico($type, $website_id, $pdo, $limit, $template, $class);
        
        // Substituir o nó pelo HTML gerado
        $fragment = $dom->createDocumentFragment();
        @$fragment->appendXML($generatedHtml);
        $node->parentNode->replaceChild($fragment, $node);
    }
    
    return $dom->saveHTML();
}

function gerarConteudoDinamico($type, $website_id, $pdo, $limit, $template, $class) {
    switch ($type) {
        case 'blog_posts':
            $posts = getBlogPosts($pdo, $website_id, $limit);
            return renderBlogPosts($posts, $template, $class, $website_id);
            
        case 'blog_destaque':
            $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE website_id = ? AND status = 'publicado' ORDER BY views DESC LIMIT 1");
            $stmt->execute([$website_id]);
            $post = $stmt->fetch();
            return $post ? renderBlogPostDestaque($post, $template, $class, $website_id) : '';
            
        case 'noticias_lista':
            $noticias = getNoticiasArtigos($pdo, $website_id, null, $limit);
            return renderNoticiasLista($noticias, $template, $class, $website_id);
            
        case 'noticias_destaque':
            $noticia = getNoticiaDestaque($pdo, $website_id);
            return $noticia ? renderNoticiaDestaque($noticia, $template, $class, $website_id) : '';
            
        case 'noticias_categorias':
            $stmt = $pdo->prepare("SELECT DISTINCT categoria FROM noticias_artigos WHERE website_id = ? AND status = 'publicado' AND categoria IS NOT NULL");
            $stmt->execute([$website_id]);
            $categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return renderCategoriasNoticias($categorias, $template, $class, $website_id);
            
        case 'classificados_lista':
            $anuncios = getClassificadosAnuncios($pdo, $website_id, null, $limit);
            return renderClassificadosLista($anuncios, $template, $class, $website_id);
            
        case 'classificados_destaque':
            $stmt = $pdo->prepare("SELECT * FROM classificados_anuncios WHERE website_id = ? AND status = 'ativo' ORDER BY views DESC LIMIT 1");
            $stmt->execute([$website_id]);
            $anuncio = $stmt->fetch();
            return $anuncio ? renderAnuncioDestaque($anuncio, $template, $class, $website_id) : '';
            
        default:
            return '';
    }
}

// Funções de renderização para cada template
function renderBlogPosts($posts, $template, $class, $website_id) {
    if (empty($posts)) return '<p class="text-gray-500">Nenhum post encontrado.</p>';
    
    $html = '<div class="blog-posts ' . htmlspecialchars($class) . '">';
    foreach ($posts as $post) {
        $html .= '
        <article class="blog-post mb-8 border-b pb-4">
            ' . ($post['imagem'] ? '<img src="' . htmlspecialchars($post['imagem']) . '" alt="' . htmlspecialchars($post['titulo']) . '" class="w-full h-48 object-cover rounded mb-4">' : '') . '
            <h3 class="text-xl font-bold mb-2">
                <a href="ver_post.php?website_id=' . $website_id . '&post_id=' . $post['id'] . '">' . htmlspecialchars($post['titulo']) . '</a>
            </h3>
            <p class="text-gray-600 mb-2">' . htmlspecialchars($post['resumo'] ?? substr($post['conteudo'], 0, 150) . '...') . '</p>
            <div class="text-sm text-gray-500">' . date('d/m/Y', strtotime($post['data_publicacao'])) . '</div>
        </article>';
    }
    $html .= '</div>';
    return $html;
}

function renderNoticiasLista($noticias, $template, $class, $website_id) {
    if (empty($noticias)) return '<p class="text-gray-500">Nenhuma notícia encontrada.</p>';
    
    $html = '<div class="noticias-lista ' . htmlspecialchars($class) . '">';
    foreach ($noticias as $noticia) {
        $html .= '
        <article class="noticia-item mb-6 p-4 bg-white rounded shadow">
            ' . ($noticia['imagem'] ? '<img src="' . htmlspecialchars($noticia['imagem']) . '" alt="' . htmlspecialchars($noticia['titulo']) . '" class="w-full h-40 object-cover rounded mb-3">' : '') . '
            <h3 class="text-lg font-bold mb-2">
                <a href="ver_noticia.php?website_id=' . $website_id . '&noticia_id=' . $noticia['id'] . '">' . htmlspecialchars($noticia['titulo']) . '</a>
            </h3>
            <p class="text-gray-600 text-sm mb-2">' . htmlspecialchars($noticia['resumo'] ?? substr($noticia['conteudo'], 0, 100) . '...') . '</p>
            <div class="flex justify-between text-xs text-gray-500">
                <span>' . ($noticia['categoria'] ?? 'Geral') . '</span>
                <span>' . date('d/m/Y', strtotime($noticia['data_publicacao'])) . '</span>
            </div>
        </article>';
    }
    $html .= '</div>';
    return $html;
}

function renderClassificadosLista($anuncios, $template, $class, $website_id) {
    if (empty($anuncios)) return '<p class="text-gray-500">Nenhum anúncio encontrado.</p>';
    
    $html = '<div class="classificados-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 ' . htmlspecialchars($class) . '">';
    foreach ($anuncios as $anuncio) {
        $html .= '
        <div class="anuncio-card border rounded-lg overflow-hidden bg-white">
            ' . ($anuncio['imagem'] ? '<img src="' . htmlspecialchars($anuncio['imagem']) . '" alt="' . htmlspecialchars($anuncio['titulo']) . '" class="w-full h-40 object-cover">' : '') . '
            <div class="p-4">
                <h3 class="font-bold text-lg mb-2">
                    <a href="ver_anuncio.php?website_id=' . $website_id . '&anuncio_id=' . $anuncio['id'] . '">' . htmlspecialchars($anuncio['titulo']) . '</a>
                </h3>
                <p class="text-gray-600 text-sm mb-2">' . htmlspecialchars(substr($anuncio['descricao'], 0, 80)) . '...</p>
                ' . ($anuncio['preco'] ? '<p class="text-green-600 font-bold text-xl mb-2">R$ ' . number_format($anuncio['preco'], 2, ',', '.') . '</p>' : '') . '
                <a href="ver_anuncio.php?website_id=' . $website_id . '&anuncio_id=' . $anuncio['id'] . '" class="text-blue-600 hover:underline text-sm">Ver detalhes →</a>
            </div>
        </div>';
    }
    $html .= '</div>';
    return $html;
}
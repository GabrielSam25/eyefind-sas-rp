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

// ===== NOVAS FUNÇÕES PARA SISTEMA DE TIPOS =====

function getTipoSite($pdo, $website_id) {
    $stmt = $pdo->prepare("SELECT tipo FROM websites WHERE id = ?");
    $stmt->execute([$website_id]);
    return $stmt->fetchColumn();
}

// ===== FUNÇÕES PARA BLOG =====

function getBlogPosts($pdo, $website_id, $limit = 10, $offset = 0) {
    $stmt = $pdo->prepare("
        SELECT * FROM blog_posts 
        WHERE website_id = ? AND status = 'publicado' 
        ORDER BY data_publicacao DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $website_id, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(3, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
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

// ===== FUNÇÕES PARA NOTÍCIAS (CORRIGIDAS) =====

function getNoticiasArtigos($pdo, $website_id, $categoria = null, $limit = 10) {
    $sql = "SELECT * FROM noticias_artigos WHERE website_id = ? AND status = 'publicado'";
    $params = [$website_id];
    
    if ($categoria) {
        $sql .= " AND categoria = ?";
        $params[] = $categoria;
    }
    
    $sql .= " ORDER BY destaque DESC, data_publicacao DESC LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $website_id, PDO::PARAM_INT);
    
    if ($categoria) {
        $stmt->bindValue(2, $categoria, PDO::PARAM_STR);
        $stmt->bindValue(3, (int)$limit, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNoticiaDestaqueSite($pdo, $website_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM noticias_artigos 
        WHERE website_id = ? AND status = 'publicado' AND destaque = 1 
        ORDER BY data_publicacao DESC LIMIT 1
    ");
    $stmt->execute([$website_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getNoticiaAutor($pdo, $autor_id) {
    if (!$autor_id || $autor_id === 'NULL' || $autor_id === null) {
        return ['nome' => 'Redação'];
    }
    
    $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
    $stmt->execute([$autor_id]);
    $autor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($autor && isset($autor['nome'])) {
        return $autor;
    }
    
    return ['nome' => 'Redação'];
}

// ===== FUNÇÕES PARA CLASSIFICADOS =====

function getClassificadosAnuncios($pdo, $website_id, $categoria = null, $limit = 20) {
    $sql = "SELECT * FROM classificados_anuncios WHERE website_id = ? AND status = 'ativo'";
    $params = [$website_id];
    
    if ($categoria) {
        $sql .= " AND categoria = ?";
        $params[] = $categoria;
    }
    
    $sql .= " ORDER BY data_criacao DESC LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $website_id, PDO::PARAM_INT);
    if ($categoria) {
        $stmt->bindValue(2, $categoria, PDO::PARAM_STR);
        $stmt->bindValue(3, (int)$limit, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
    }
    $stmt->execute();   
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== FUNÇÃO AUXILIAR PARA SLUG =====

function criarSlug($texto) {
    $texto = preg_replace('~[^\pL\d]+~u', '-', $texto);
    $texto = iconv('utf-8', 'us-ascii//TRANSLIT', $texto);
    $texto = preg_replace('~[^-\w]+~', '', $texto);
    $texto = trim($texto, '-');
    $texto = preg_replace('~-+~', '-', $texto);
    $texto = strtolower($texto);
    return empty($texto) ? 'post-' . time() : $texto;
}

// ===== FUNÇÃO PRINCIPAL DE RENDERIZAÇÃO =====

function renderDynamicBlocks($html, $website_id, $pdo) {
    // Primeiro processa as classes dinâmicas
    $html = renderDynamicContent($html, $website_id, $pdo);
    
    // Depois processa os data-dynamic
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//*[@data-dynamic]");
    
    foreach ($nodes as $node) {
        $type = $node->getAttribute('data-dynamic');
        $limit = $node->getAttribute('data-limit') ?: 5;
        $class = $node->getAttribute('class');
        
        $generatedHtml = gerarConteudoDinamico($type, $website_id, $pdo, $limit, $class);
        
        $fragment = $dom->createDocumentFragment();
        @$fragment->appendXML($generatedHtml);
        $node->parentNode->replaceChild($fragment, $node);
    }
    
    return $dom->saveHTML();
}

function gerarConteudoDinamico($type, $website_id, $pdo, $limit, $class) {
    switch ($type) {
        case 'blog_posts':
            $posts = getBlogPosts($pdo, $website_id, $limit);
            return renderBlogPosts($posts, $class, $website_id);
            
        case 'blog_destaque':
            $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE website_id = ? AND status = 'publicado' ORDER BY views DESC LIMIT 1");
            $stmt->execute([$website_id]);
            $post = $stmt->fetch();
            return $post ? renderBlogPostDestaque($post, $class, $website_id) : '';
            
        case 'noticias_lista':
            $noticias = getNoticiasArtigos($pdo, $website_id, null, $limit);
            return renderNoticiasLista($noticias, $class, $website_id);
            
        case 'noticias_destaque':
            $noticia = getNoticiaDestaqueSite($pdo, $website_id);
            return $noticia ? renderNoticiaDestaque($noticia, $class, $website_id) : '';
            
        case 'classificados_lista':
            $anuncios = getClassificadosAnuncios($pdo, $website_id, null, $limit);
            return renderClassificadosLista($anuncios, $class, $website_id);
            
        default:
            return '<p class="text-gray-500">Conteúdo dinâmico não disponível</p>';
    }
}

// ===== FUNÇÕES DE RENDERIZAÇÃO PARA BLOG =====

function renderBlogPosts($posts, $class, $website_id) {
    if (empty($posts)) return '<p class="text-gray-500">Nenhum post encontrado.</p>';
    
    $html = '<div class="blog-posts ' . htmlspecialchars($class) . '">';
    foreach ($posts as $post) {
        $html .= '
        <article class="blog-post mb-6 p-4 bg-white rounded shadow">
            ' . ($post['imagem'] ? '<img src="' . htmlspecialchars($post['imagem']) . '" alt="' . htmlspecialchars($post['titulo']) . '" class="w-full h-48 object-cover rounded mb-4">' : '') . '
            <h3 class="text-xl font-bold mb-2">
                <a href="ver_post.php?website_id=' . $website_id . '&post_id=' . $post['id'] . '" class="text-blue-600 hover:underline">' . htmlspecialchars($post['titulo']) . '</a>
            </h3>
            <p class="text-gray-600 mb-2">' . htmlspecialchars($post['resumo'] ?? substr(strip_tags($post['conteudo']), 0, 150) . '...') . '</p>
            <div class="text-sm text-gray-500">' . date('d/m/Y', strtotime($post['data_publicacao'])) . '</div>
        </article>';
    }
    $html .= '</div>';
    return $html;
}

function renderBlogPostDestaque($post, $class, $website_id) {
    return '
    <div class="blog-destaque ' . htmlspecialchars($class) . ' mb-6 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg shadow-lg">
        ' . ($post['imagem'] ? '<img src="' . htmlspecialchars($post['imagem']) . '" alt="' . htmlspecialchars($post['titulo']) . '" class="w-full h-64 object-cover rounded-lg mb-4">' : '') . '
        <h2 class="text-2xl font-bold mb-2">
            <a href="ver_post.php?website_id=' . $website_id . '&post_id=' . $post['id'] . '" class="text-blue-700 hover:underline">' . htmlspecialchars($post['titulo']) . '</a>
        </h2>
        <p class="text-gray-700 mb-3">' . htmlspecialchars($post['resumo'] ?? substr(strip_tags($post['conteudo']), 0, 200) . '...') . '</p>
        <div class="flex justify-between items-center">
            <span class="text-sm text-gray-500">' . date('d/m/Y', strtotime($post['data_publicacao'])) . '</span>
            <a href="ver_post.php?website_id=' . $website_id . '&post_id=' . $post['id'] . '" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Leia mais →</a>
        </div>
    </div>';
}

// ===== FUNÇÕES DE RENDERIZAÇÃO PARA NOTÍCIAS (CORRIGIDAS) =====

function renderNoticiasLista($noticias, $class, $website_id) {
    if (empty($noticias)) {
        return '<div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-center">
                    <p class="text-yellow-700">Nenhuma notícia publicada ainda.</p>
                    <p class="text-sm text-gray-500 mt-2">Vá em "Gerenciar Notícias" e crie sua primeira notícia.</p>
                </div>';
    }
    
    $html = '<div class="noticias-lista ' . htmlspecialchars($class) . '">';
    
    foreach ($noticias as $noticia) {
        // Garantir que todos os campos existam
        $titulo = $noticia['titulo'] ?? 'Sem título';
        $conteudo = $noticia['conteudo'] ?? '';
        $resumo = !empty($noticia['resumo']) ? $noticia['resumo'] : (substr(strip_tags($conteudo), 0, 150) . '...');
        $categoria = $noticia['categoria'] ?? 'Geral';
        $imagem = !empty($noticia['imagem']) ? $noticia['imagem'] : 'https://via.placeholder.com/800x400?text=Sem+Imagem';
        $data = !empty($noticia['data_publicacao']) ? date('d/m/Y', strtotime($noticia['data_publicacao'])) : date('d/m/Y');
        $views = isset($noticia['views']) ? number_format($noticia['views']) : '0';
        
        // Autor com fallback
        $autor = getNoticiaAutor($GLOBALS['pdo'], $noticia['autor_id'] ?? null);
        $autor_nome = $autor['nome'] ?? 'Redação';
        
        $html .= '
        <div class="noticia-item mb-6 border-b pb-4">
            <a href="ver_noticia.php?website_id=' . $website_id . '&noticia_id=' . $noticia['id'] . '" class="block group">
                ' . ($noticia['imagem'] ? '<img src="' . htmlspecialchars($imagem) . '" alt="' . htmlspecialchars($titulo) . '" class="w-full h-48 object-cover rounded-lg mb-3 group-hover:opacity-90 transition">' : '') . '
                <span class="text-xs font-semibold text-red-600 uppercase tracking-wider">' . htmlspecialchars($categoria) . '</span>
                <h3 class="font-bold text-xl mt-1 mb-2 group-hover:text-red-600 transition">' . htmlspecialchars($titulo) . '</h3>
                <p class="text-gray-600 text-sm">' . htmlspecialchars($resumo) . '</p>
                <div class="flex items-center text-xs text-gray-500 mt-2">
                    <span>Por ' . htmlspecialchars($autor_nome) . '</span>
                    <span class="mx-2">•</span>
                    <span>' . $data . '</span>
                    <span class="mx-2">•</span>
                    <span>' . $views . ' visualizações</span>
                </div>
            </a>
        </div>';
    }
    
    $html .= '</div>';
    return $html;
}

function renderNoticiaDestaque($noticia, $class, $website_id) {
    if (!$noticia) {
        return '<div class="p-8 bg-gray-100 rounded-lg text-center">
                    <p class="text-gray-500">Nenhuma notícia em destaque.</p>
                </div>';
    }
    
    // Garantir que todos os campos existam
    $titulo = $noticia['titulo'] ?? 'Sem título';
    $conteudo = $noticia['conteudo'] ?? '';
    $resumo = !empty($noticia['resumo']) ? $noticia['resumo'] : (substr(strip_tags($conteudo), 0, 200) . '...');
    $imagem = !empty($noticia['imagem']) ? $noticia['imagem'] : 'https://via.placeholder.com/1200x600?text=Sem+Imagem';
    $data = !empty($noticia['data_publicacao']) ? date('d/m/Y', strtotime($noticia['data_publicacao'])) : date('d/m/Y');
    $views = isset($noticia['views']) ? number_format($noticia['views']) : '0';
    
    // Autor com fallback
    $autor = getNoticiaAutor($GLOBALS['pdo'], $noticia['autor_id'] ?? null);
    $autor_nome = $autor['nome'] ?? 'Redação';
    
    return '
    <div class="noticia-destaque ' . htmlspecialchars($class) . ' relative rounded-xl overflow-hidden">
        <img src="' . htmlspecialchars($imagem) . '" alt="' . htmlspecialchars($titulo) . '" class="w-full h-[400px] object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
        <div class="absolute bottom-0 left-0 right-0 p-8 text-white">
            <span class="bg-red-600 text-white px-3 py-1 rounded-full text-xs font-bold mb-3 inline-block">DESTAQUE</span>
            <h2 class="text-4xl font-bold mb-3">
                <a href="ver_noticia.php?website_id=' . $website_id . '&noticia_id=' . $noticia['id'] . '" class="hover:underline">' . htmlspecialchars($titulo) . '</a>
            </h2>
            <p class="text-lg mb-4 text-gray-200">' . htmlspecialchars($resumo) . '</p>
            <div class="flex items-center text-sm text-gray-300">
                <span>Por ' . htmlspecialchars($autor_nome) . '</span>
                <span class="mx-2">•</span>
                <span>' . $data . '</span>
                <span class="mx-2">•</span>
                <span>' . $views . ' visualizações</span>
            </div>
        </div>
    </div>';
}

function renderNoticiaDestaque($noticia, $class, $website_id) {
    $autor = getNoticiaAutor($GLOBALS['pdo'], $noticia['autor_id']);
    $data = date('d/m/Y', strtotime($noticia['data_publicacao']));
    $resumo = htmlspecialchars($noticia['resumo'] ?? substr(strip_tags($noticia['conteudo']), 0, 200) . '...');
    $imagem = $noticia['imagem'] ?? 'https://via.placeholder.com/1200x600?text=Sem+Imagem';
    
    return '
    <div class="noticia-destaque ' . htmlspecialchars($class) . ' relative rounded-xl overflow-hidden">
        <img src="' . htmlspecialchars($imagem) . '" alt="' . htmlspecialchars($noticia['titulo']) . '" class="w-full h-[400px] object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
        <div class="absolute bottom-0 left-0 right-0 p-8 text-white">
            <span class="bg-red-600 text-white px-3 py-1 rounded-full text-xs font-bold mb-3 inline-block">DESTAQUE</span>
            <h2 class="text-4xl font-bold mb-3">
                <a href="ver_noticia.php?website_id=' . $website_id . '&noticia_id=' . $noticia['id'] . '" class="hover:underline">' . htmlspecialchars($noticia['titulo']) . '</a>
            </h2>
            <p class="text-lg mb-4 text-gray-200">' . $resumo . '</p>
            <div class="flex items-center text-sm text-gray-300">
                <span>Por ' . htmlspecialchars($autor['nome']) . '</span>
                <span class="mx-2">•</span>
                <span>' . $data . '</span>
                <span class="mx-2">•</span>
                <span>' . number_format($noticia['views']) . ' visualizações</span>
            </div>
        </div>
    </div>';
}

// ===== FUNÇÕES DE RENDERIZAÇÃO PARA CLASSIFICADOS =====

function renderClassificadosLista($anuncios, $class, $website_id) {
    if (empty($anuncios)) return '<p class="text-gray-500">Nenhum anúncio encontrado.</p>';
    
    $html = '<div class="classificados-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 ' . htmlspecialchars($class) . '">';
    foreach ($anuncios as $anuncio) {
        $html .= '
        <div class="anuncio-card border rounded-lg overflow-hidden bg-white hover:shadow-lg transition">
            ' . ($anuncio['imagem'] ? '<img src="' . htmlspecialchars($anuncio['imagem']) . '" alt="' . htmlspecialchars($anuncio['titulo']) . '" class="w-full h-48 object-cover">' : '') . '
            <div class="p-4">
                <h3 class="font-bold text-lg mb-2">
                    <a href="ver_anuncio.php?website_id=' . $website_id . '&anuncio_id=' . $anuncio['id'] . '" class="text-purple-600 hover:underline">' . htmlspecialchars($anuncio['titulo']) . '</a>
                </h3>
                <p class="text-gray-600 text-sm mb-2">' . htmlspecialchars(substr($anuncio['descricao'], 0, 80)) . '...</p>
                ' . ($anuncio['preco'] ? '<p class="text-green-600 font-bold text-xl mb-2">R$ ' . number_format($anuncio['preco'], 2, ',', '.') . '</p>' : '') . '
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-500"><i class="fas fa-phone mr-1"></i>' . ($anuncio['contato'] ?? 'Ver contato') . '</span>
                    <a href="ver_anuncio.php?website_id=' . $website_id . '&anuncio_id=' . $anuncio['id'] . '" class="text-purple-600 hover:underline">Detalhes →</a>
                </div>
            </div>
        </div>';
    }
    $html .= '</div>';
    return $html;
}

// ===== FUNÇÃO PARA RENDERIZAR CONTEÚDO COM CLASSES DINÂMICAS =====

function renderDynamicContent($html, $website_id, $pdo) {
    // Pega a primeira notícia publicada (você pode modificar para pegar um específico)
    $stmt = $pdo->prepare("SELECT * FROM noticias_artigos WHERE website_id = ? AND status = 'publicado' ORDER BY data_publicacao DESC LIMIT 1");
    $stmt->execute([$website_id]);
    $noticia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$noticia) {
        return $html; // Se não tiver notícia, retorna o HTML original
    }
    
    $autor = getNoticiaAutor($pdo, $noticia['autor_id']);
    $categoria = $noticia['categoria'] ?? 'Geral';
    
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // 1. SUBSTITUIR TÍTULOS
    $titulos = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dynamic-titulo ')]");
    foreach ($titulos as $elemento) {
        $elemento->nodeValue = htmlspecialchars($noticia['titulo']);
    }
    
    // 2. SUBSTITUIR CONTEÚDO
    $conteudos = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dynamic-conteudo ')]");
    foreach ($conteudos as $elemento) {
        $elemento->nodeValue = strip_tags($noticia['conteudo']);
    }
    
    // 3. SUBSTITUIR RESUMO
    $resumos = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dynamic-resumo ')]");
    foreach ($resumos as $elemento) {
        $resumo = $noticia['resumo'] ?? substr(strip_tags($noticia['conteudo']), 0, 150) . '...';
        $elemento->nodeValue = htmlspecialchars($resumo);
    }
    
    // 4. SUBSTITUIR IMAGENS
    $imagens = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dynamic-imagem ')]");
    foreach ($imagens as $elemento) {
        if ($elemento->nodeName === 'img') {
            $imagem = $noticia['imagem'] ?? 'https://via.placeholder.com/800x400';
            $elemento->setAttribute('src', $imagem);
        }
    }
    
    // 5. SUBSTITUIR DATAS
    $datas = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dynamic-data ')]");
    foreach ($datas as $elemento) {
        $data = date('d/m/Y', strtotime($noticia['data_publicacao']));
        $elemento->nodeValue = $data;
    }
    
    // 6. SUBSTITUIR DATA COMPLETA
    $datas_completas = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dynamic-data-completa ')]");
    foreach ($datas_completas as $elemento) {
        $data = date('l, F j, Y', strtotime($noticia['data_publicacao']));
        $elemento->nodeValue = $data;
    }
    
    // 7. SUBSTITUIR AUTOR
    $autores = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dynamic-autor-nome ')]");
    foreach ($autores as $elemento) {
        $elemento->nodeValue = $autor['nome'];
    }
    
    // 8. SUBSTITUIR CATEGORIA
    $categorias = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dynamic-categoria ')]");
    foreach ($categorias as $elemento) {
        $elemento->nodeValue = $categoria;
    }
    
    // 9. SUBSTITUIR VIEWS
    $views = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dynamic-views ')]");
    foreach ($views as $elemento) {
        $elemento->nodeValue = number_format($noticia['views']);
    }
    
    // 10. SUBSTITUIR ANO
    $anos = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dynamic-ano ')]");
    foreach ($anos as $elemento) {
        $elemento->nodeValue = date('Y');
    }
    
    // 11. SUBSTITUIR LINKS
    $links = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' dynamic-link ')]");
    foreach ($links as $elemento) {
        if ($elemento->nodeName === 'a') {
            $elemento->setAttribute('href', 'ver_noticia.php?website_id=' . $website_id . '&noticia_id=' . $noticia['id']);
        }
    }
    
    return $dom->saveHTML();
}
?>
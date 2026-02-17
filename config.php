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

// ===== FUNÇÕES PARA SISTEMA DE TIPOS =====

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

// ===== FUNÇÕES PARA NOTÍCIAS =====

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

// ===== FUNÇÃO PRINCIPAL DE RENDERIZAÇÃO (CORRIGIDA) =====

function renderDynamicBlocks($html, $website_id, $pdo) {
    // Primeiro processa as classes dinâmicas (substitui valores)
    $html = renderDynamicContent($html, $website_id, $pdo);
    
    // Remove qualquer data-dynamic que possa ter sobrado (não vamos usar mais)
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//*[@data-dynamic]");
    
    // Remove os atributos data-dynamic mas mantém o conteúdo
    foreach ($nodes as $node) {
        $node->removeAttribute('data-dynamic');
        $node->removeAttribute('data-limit');
    }
    
    return $dom->saveHTML();
}

// ===== FUNÇÃO PARA RENDERIZAR CONTEÚDO COM CLASSES DINÂMICAS (APENAS SUBSTITUIÇÃO) =====

function renderDynamicContent($html, $website_id, $pdo) {
    $tipo = getTipoSite($pdo, $website_id);
    
    // Buscar dados
    $itens = [];
    switch ($tipo) {
        case 'blog':
            $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE website_id = ? AND status = 'publicado' ORDER BY data_publicacao DESC");
            $stmt->execute([$website_id]);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        case 'noticias':
            $stmt = $pdo->prepare("SELECT * FROM noticias_artigos WHERE website_id = ? AND status = 'publicado' ORDER BY data_publicacao DESC");
            $stmt->execute([$website_id]);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        case 'classificados':
            $stmt = $pdo->prepare("SELECT * FROM classificados_anuncios WHERE website_id = ? AND status = 'ativo' ORDER BY data_criacao DESC");
            $stmt->execute([$website_id]);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        default:
            return $html;
    }
    
    if (empty($itens)) return $html;
    
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // Processa elementos sem data-post-id (globais) usando o primeiro item
    $elementos_sem_id = $xpath->query("//*[not(@data-post-id)]");
    foreach ($elementos_sem_id as $el) {
        processarClasses($el, $itens[0], $pdo, $website_id);
    }
    
    // Processa cada post individualmente
    foreach ($itens as $item) {
        $post_id = $item['id'];
        $elementos = $xpath->query("//*[@data-post-id='$post_id']");
        foreach ($elementos as $el) {
            processarClasses($el, $item, $pdo, $website_id);
        }
    }
    
    return $dom->saveHTML();
}

function processarClasses($elemento, $dados, $pdo, $website_id) {
    $classAttr = $elemento->getAttribute('class');
    $classes = explode(' ', $classAttr);
    
    foreach ($classes as $classe) {
        $classe = trim($classe);
        switch ($classe) {
            case 'dynamic-titulo':
                $elemento->nodeValue = htmlspecialchars($dados['titulo'] ?? '');
                break;
            case 'dynamic-resumo':
                $resumo = $dados['resumo'] ?? substr(strip_tags($dados['conteudo'] ?? ''), 0, 150) . '...';
                $elemento->nodeValue = htmlspecialchars($resumo);
                break;
            case 'dynamic-imagem':
                if ($elemento->nodeName === 'img') {
                    $imagem = $dados['imagem'] ?? 'https://via.placeholder.com/800x400';
                    $elemento->setAttribute('src', $imagem);
                }
                break;
            case 'dynamic-data':
                $data = !empty($dados['data_publicacao']) ? date('d/m/Y', strtotime($dados['data_publicacao'])) : date('d/m/Y');
                $elemento->nodeValue = $data;
                break;
            case 'dynamic-data-completa':
                $data = !empty($dados['data_publicacao']) ? date('l, F j, Y', strtotime($dados['data_publicacao'])) : date('l, F j, Y');
                $elemento->nodeValue = $data;
                break;
            case 'dynamic-autor-nome':
                $autor = getNoticiaAutor($pdo, $dados['autor_id'] ?? null);
                $elemento->nodeValue = htmlspecialchars($autor['nome']);
                break;
            case 'dynamic-categoria':
                $categoria = $dados['categoria'] ?? 'Geral';
                $elemento->nodeValue = htmlspecialchars($categoria);
                break;
            case 'dynamic-views':
                $views = isset($dados['views']) ? number_format($dados['views']) : '0';
                $elemento->nodeValue = $views;
                break;
            case 'dynamic-ano':
                $elemento->nodeValue = date('Y');
                break;
            case 'dynamic-link':
                if ($elemento->nodeName === 'a' && isset($dados['id'])) {
                    $link = "ver_noticia.php?website_id=$website_id&noticia_id=" . $dados['id'];
                    $elemento->setAttribute('href', $link);
                }
                break;
        }
    }
}

// ===== FUNÇÃO AUXILIAR PARA PROCESSAR CLASSES EM UM ELEMENTO =====

function processarElementoComClasses($element, $dados, $pdo, $website_id) {
    // Pega todas as classes do elemento
    $classAttr = $element->getAttribute('class');
    $classes = explode(' ', $classAttr);
    
    foreach ($classes as $classe) {
        $classe = trim($classe);
        
        switch ($classe) {
            case 'dynamic-titulo':
                $element->nodeValue = htmlspecialchars($dados['titulo'] ?? '');
                break;
                
            case 'dynamic-conteudo':
                // Para conteúdo, mantém o HTML interno
                $conteudo = $dados['conteudo'] ?? '';
                // Remove o conteúdo antigo e adiciona o novo como HTML
                while ($element->firstChild) {
                    $element->removeChild($element->firstChild);
                }
                $fragment = $element->ownerDocument->createDocumentFragment();
                @$fragment->appendXML($conteudo);
                $element->appendChild($fragment);
                break;
                
            case 'dynamic-resumo':
                $resumo = $dados['resumo'] ?? substr(strip_tags($dados['conteudo'] ?? ''), 0, 150) . '...';
                $element->nodeValue = htmlspecialchars($resumo);
                break;
                
            case 'dynamic-imagem':
                if ($element->nodeName === 'img') {
                    $imagem = $dados['imagem'] ?? 'https://via.placeholder.com/800x400';
                    $element->setAttribute('src', $imagem);
                    $element->setAttribute('alt', htmlspecialchars($dados['titulo'] ?? 'Imagem'));
                }
                break;
                
            case 'dynamic-data':
                $data = !empty($dados['data_publicacao']) ? date('d/m/Y', strtotime($dados['data_publicacao'])) : date('d/m/Y');
                $element->nodeValue = $data;
                break;
                
            case 'dynamic-data-completa':
                $data = !empty($dados['data_publicacao']) ? date('l, F j, Y', strtotime($dados['data_publicacao'])) : date('l, F j, Y');
                $element->nodeValue = $data;
                break;
                
            case 'dynamic-autor':
            case 'dynamic-autor-nome':
                $autor = getNoticiaAutor($pdo, $dados['autor_id'] ?? null);
                $element->nodeValue = htmlspecialchars($autor['nome']);
                break;
                
            case 'dynamic-categoria':
                $categoria = $dados['categoria'] ?? 'Geral';
                $element->nodeValue = htmlspecialchars($categoria);
                break;
                
            case 'dynamic-views':
                $views = isset($dados['views']) ? number_format($dados['views']) : '0';
                $element->nodeValue = $views;
                break;
                
            case 'dynamic-ano':
                $element->nodeValue = date('Y');
                break;
                
            case 'dynamic-link':
                if ($element->nodeName === 'a') {
                    if (isset($dados['id'])) {
                        // Determinar o tipo para criar link correto
                        $tipo = $dados['tipo'] ?? getTipoSite($pdo, $website_id);
                        switch ($tipo) {
                            case 'blog':
                                $link = "ver_post.php?website_id=$website_id&post_id=" . $dados['id'];
                                break;
                            case 'noticias':
                                $link = "ver_noticia.php?website_id=$website_id&noticia_id=" . $dados['id'];
                                break;
                            case 'classificados':
                                $link = "ver_anuncio.php?website_id=$website_id&anuncio_id=" . $dados['id'];
                                break;
                            default:
                                $link = "#";
                        }
                        $element->setAttribute('href', $link);
                    }
                }
                break;
                
            case 'dynamic-preco':
                if (isset($dados['preco'])) {
                    $preco = 'R$ ' . number_format($dados['preco'], 2, ',', '.');
                    $element->nodeValue = $preco;
                }
                break;
                
            case 'dynamic-contato':
                $element->nodeValue = htmlspecialchars($dados['contato'] ?? '');
                break;
                
            case 'dynamic-telefone':
                $element->nodeValue = htmlspecialchars($dados['telefone'] ?? '');
                break;
                
            case 'dynamic-email':
                if ($element->nodeName === 'a') {
                    $email = $dados['email'] ?? '';
                    $element->setAttribute('href', 'mailto:' . $email);
                    $element->nodeValue = $email;
                } else {
                    $element->nodeValue = htmlspecialchars($dados['email'] ?? '');
                }
                break;
        }
    }
}
?>
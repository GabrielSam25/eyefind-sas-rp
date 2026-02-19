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

// ===== FUNÇÕES BÁSICAS =====
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
    
    if ($categoria) {
        $sql .= " AND categoria = ?";
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
    // Primeiro tenta buscar uma notícia com destaque = 1
    $stmt = $pdo->prepare("
        SELECT * FROM noticias_artigos 
        WHERE website_id = ? AND status = 'publicado' AND destaque = 1 
        ORDER BY data_publicacao DESC LIMIT 1
    ");
    $stmt->execute([$website_id]);
    $noticia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se não encontrar com destaque, pega a mais recente
    if (!$noticia) {
        $stmt = $pdo->prepare("
            SELECT * FROM noticias_artigos 
            WHERE website_id = ? AND status = 'publicado' 
            ORDER BY data_publicacao DESC LIMIT 1
        ");
        $stmt->execute([$website_id]);
        $noticia = $stmt->fetch(PDO::FETCH_ASSOC);
    }   
    
    return $noticia;
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
    
    if ($categoria) {
        $sql .= " AND categoria = ?";
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

// ===== FUNÇÃO PRINCIPAL DE RENDERIZAÇÃO (VERSÃO FINAL COM TEMPLATES) =====

function renderDynamicBlocks($html, $website_id, $pdo) {
    if (empty($html)) {
        return '';
    }
    
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    
    // Adiciona meta charset para evitar problemas com caracteres especiais
    $htmlWithMeta = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html;
    @$dom->loadHTML($htmlWithMeta, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//*[@data-dynamic]");
    
    // Converte para array para evitar problemas durante a remoção de nós
    $nodesArray = [];
    foreach ($nodes as $node) {
        $nodesArray[] = $node;
    }

    foreach ($nodesArray as $node) {
        // Pula nós que não são elementos (ex: texto, comentário)
        if (!($node instanceof DOMElement)) {
            continue;
        }
        
        $type = $node->getAttribute('data-dynamic');
        $limit = $node->getAttribute('data-limit') ?: 5;

        // Obter dados conforme o tipo
        $itens = [];
        switch ($type) {
            case 'blog_posts':
                $itens = getBlogPosts($pdo, $website_id, $limit);
                break;
            case 'blog_destaque':
                $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE website_id = ? AND status = 'publicado' ORDER BY views DESC LIMIT 1");
                $stmt->execute([$website_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($item) $itens = [$item];
                break;
            case 'noticias_lista':
                $itens = getNoticiasArtigos($pdo, $website_id, null, $limit);
                break;
            case 'noticias_destaque':
                $item = getNoticiaDestaqueSite($pdo, $website_id);
                if ($item) $itens = [$item];
                break;
            case 'classificados_lista':
                $itens = getClassificadosAnuncios($pdo, $website_id, null, $limit);
                break;
            default:
                continue 2;
        }

        if (empty($itens)) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
            continue;
        }

        // Verificar se é uma lista (termina com _lista)
        if (strpos($type, '_lista') !== false) {
            // Procura o primeiro filho que seja elemento (template)
            $template = null;
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $template = $child;
                    break;
                }
            }
            if (!$template) {
                $template = $dom->createElement('div');
                $template->setAttribute('class', 'item');
                $template->appendChild($dom->createElement('h3', 'Título'));
            }

            // Remove todo o conteúdo original
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }

            // Clona e preenche para cada item
            foreach ($itens as $item) {
                $clone = $template->cloneNode(true);
                preencherElementoComDados($clone, $item, $pdo, $website_id);
                $node->appendChild($clone);
            }
        } else {
            // Item único: limpa e preenche o próprio nó
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }
            preencherElementoComDados($node, $itens[0], $pdo, $website_id);
        }

        // Remove os atributos dinâmicos
        $node->removeAttribute('data-dynamic');
        $node->removeAttribute('data-limit');
    }

    $result = $dom->saveHTML();
    // Remove o meta tag adicionado temporariamente
    $result = preg_replace('/<meta http-equiv="Content-Type" content="text\/html; charset=utf-8">/', '', $result, 1);
    return $result;
}


// ===== FUNÇÃO PARA PREENCHER ELEMENTO COM DADOS (RECURSIVA) =====

function preencherElementoComDados($elemento, $dados, $pdo, $website_id) {
    if (!($elemento instanceof DOMElement)) {
        return;
    }
    
    $classAttr = $elemento->getAttribute('class');
    $classes = explode(' ', $classAttr);
    
    foreach ($classes as $classe) {
        $classe = trim($classe);
        switch ($classe) {
            case 'dynamic-titulo':
                while ($elemento->firstChild) $elemento->removeChild($elemento->firstChild);
                $elemento->appendChild($elemento->ownerDocument->createTextNode(htmlspecialchars($dados['titulo'] ?? '')));
                break;
            case 'dynamic-conteudo':
                while ($elemento->firstChild) $elemento->removeChild($elemento->firstChild);
                if (!empty($dados['conteudo'])) {
                    $fragment = $elemento->ownerDocument->createDocumentFragment();
                    @$fragment->appendXML('<![CDATA[' . $dados['conteudo'] . ']]>');
                    if ($fragment->hasChildNodes()) {
                        $elemento->appendChild($fragment);
                    }
                }
                break;
            case 'dynamic-resumo':
                while ($elemento->firstChild) $elemento->removeChild($elemento->firstChild);
                $resumo = $dados['resumo'] ?? substr(strip_tags($dados['conteudo'] ?? ''), 0, 150) . '...';
                $elemento->appendChild($elemento->ownerDocument->createTextNode(htmlspecialchars($resumo)));
                break;
            case 'dynamic-imagem':
                if ($elemento->nodeName === 'img') {
                    $imagem = $dados['imagem'] ?? 'https://via.placeholder.com/800x400';
                    $elemento->setAttribute('src', $imagem);
                    $elemento->setAttribute('alt', htmlspecialchars($dados['titulo'] ?? 'Imagem'));
                }
                break;
            case 'dynamic-data':
                while ($elemento->firstChild) $elemento->removeChild($elemento->firstChild);
                $data = !empty($dados['data_publicacao']) ? date('d/m/Y', strtotime($dados['data_publicacao'])) : date('d/m/Y');
                $elemento->appendChild($elemento->ownerDocument->createTextNode($data));
                break;
            case 'dynamic-data-completa':
                while ($elemento->firstChild) $elemento->removeChild($elemento->firstChild);
                $data = !empty($dados['data_publicacao']) ? date('l, F j, Y', strtotime($dados['data_publicacao'])) : date('l, F j, Y');
                $elemento->appendChild($elemento->ownerDocument->createTextNode($data));
                break;
            case 'dynamic-autor':
            case 'dynamic-autor-nome':
                while ($elemento->firstChild) $elemento->removeChild($elemento->firstChild);
                $autor = getNoticiaAutor($pdo, $dados['autor_id'] ?? null);
                $elemento->appendChild($elemento->ownerDocument->createTextNode(htmlspecialchars($autor['nome'])));
                break;
            case 'dynamic-categoria':
                while ($elemento->firstChild) $elemento->removeChild($elemento->firstChild);
                $categoria = $dados['categoria'] ?? 'Geral';
                $elemento->appendChild($elemento->ownerDocument->createTextNode(htmlspecialchars($categoria)));
                break;
            case 'dynamic-views':
                while ($elemento->firstChild) $elemento->removeChild($elemento->firstChild);
                $views = isset($dados['views']) ? number_format($dados['views']) : '0';
                $elemento->appendChild($elemento->ownerDocument->createTextNode($views));
                break;
            case 'dynamic-ano':
                while ($elemento->firstChild) $elemento->removeChild($elemento->firstChild);
                $elemento->appendChild($elemento->ownerDocument->createTextNode(date('Y')));
                break;
            case 'dynamic-link':
                if ($elemento->nodeName === 'a' && isset($dados['id'])) {
                    $tipo = getTipoSite($pdo, $website_id);
                    switch ($tipo) {
                        case 'blog': $link = "ver_post.php?website_id=$website_id&post_id=" . $dados['id']; break;
                        case 'noticias': $link = "ver_noticia.php?website_id=$website_id&noticia_id=" . $dados['id']; break;
                        case 'classificados': $link = "ver_anuncio.php?website_id=$website_id&anuncio_id=" . $dados['id']; break;
                        default: $link = "#";
                    }
                    $elemento->setAttribute('href', $link);
                }
                break;
            case 'dynamic-preco':
                while ($elemento->firstChild) $elemento->removeChild($elemento->firstChild);
                if (isset($dados['preco'])) {
                    $preco = 'R$ ' . number_format($dados['preco'], 2, ',', '.');
                    $elemento->appendChild($elemento->ownerDocument->createTextNode($preco));
                }
                break;
            case 'dynamic-contato':
            case 'dynamic-telefone':
                while ($elemento->firstChild) $elemento->removeChild($elemento->firstChild);
                $valor = $dados[str_replace('dynamic-', '', $classe)] ?? '';
                $elemento->appendChild($elemento->ownerDocument->createTextNode(htmlspecialchars($valor)));
                break;
            case 'dynamic-email':
                while ($elemento->firstChild) $elemento->removeChild($elemento->firstChild);
                $email = $dados['email'] ?? '';
                if ($elemento->nodeName === 'a') {
                    $elemento->setAttribute('href', 'mailto:' . $email);
                }
                $elemento->appendChild($elemento->ownerDocument->createTextNode(htmlspecialchars($email)));
                break;
        }
    }

    // Processa filhos recursivamente
    foreach ($elemento->childNodes as $filho) {
        if ($filho instanceof DOMElement) {
            preencherElementoComDados($filho, $dados, $pdo, $website_id);
        }
    }
}
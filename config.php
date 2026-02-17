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
function isLogado() {
    return isset($_SESSION['usuario_id']);
}

function getUsuarioAtual($pdo) {
    if (!isLogado()) return null;
    $stmt = $pdo->prepare("SELECT id, nome, email, is_admin FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCategorias($pdo) {
    $stmt = $pdo->query("SELECT * FROM categorias ORDER BY ordem");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getWebsiteDoMinuto($pdo) {
    $stmt = $pdo->query("SELECT * FROM websites WHERE status = 'approved' ORDER BY RAND() LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getPublicidadeAtiva($pdo) {
    $stmt = $pdo->query("SELECT * FROM publicidade WHERE ativo = 1 ORDER BY RAND() LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getWebsites($pdo, $categoria_id = null, $limite = 4) {
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

function getCategoriaById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getWebsitesByCategoria($pdo, $categoria_id) {
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE categoria_id = ? AND status = 'approved'");
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
    $stmt = $pdo->prepare("
        SELECT * FROM noticias_artigos 
        WHERE website_id = ? AND status = 'publicado' AND destaque = 1 
        ORDER BY data_publicacao DESC LIMIT 1
    ");
    $stmt->execute([$website_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getNoticiaAutor($pdo, $autor_id) {
    if (!$autor_id) return ['nome' => 'Redação'];
    $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
    $stmt->execute([$autor_id]);
    $autor = $stmt->fetch();
    return $autor ?: ['nome' => 'Redação'];
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

// ===== FUNÇÃO PRINCIPAL DE RENDERIZAÇÃO =====
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
        $class = $node->getAttribute('class');

        // Obter dados conforme o tipo
        $itens = [];
        switch ($type) {
            case 'blog_posts':
                $itens = getBlogPosts($pdo, $website_id, $limit);
                break;
            case 'blog_destaque':
                $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE website_id = ? AND status = 'publicado' ORDER BY views DESC LIMIT 1");
                $stmt->execute([$website_id]);
                $item = $stmt->fetch();
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
                // Se não reconhecer, pula
                continue 2;
        }

        if (empty($itens)) {
            // Se não houver itens, remove o nó ou coloca uma mensagem?
            $node->parentNode->removeChild($node);
            continue;
        }

        // O nó atual contém um template interno? Vamos verificar.
        // Se o nó tiver filhos, usamos o primeiro filho como template (para listas) ou o próprio nó para item único?
        // Para simplificar, vamos tratar: se for uma lista (tipo terminado em _lista), vamos clonar o template interno.
        // Se for um item único (destaque), vamos preencher o próprio nó com os dados do primeiro item.

        if (strpos($type, '_lista') !== false) {
            // É uma lista: o conteúdo interno é o template
            $template = $node->firstChild; // Pode ser um elemento ou texto
            if (!$template) {
                // Se não houver template, cria um padrão simples
                $template = $dom->createElement('div');
                $template->setAttribute('class', 'item');
                $template->appendChild($dom->createElement('h3', 'Título'));
            }

            // Remover o conteúdo original
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }

            // Para cada item, clonar o template e preencher
            foreach ($itens as $item) {
                $clone = $template->cloneNode(true);
                // Preencher classes dinâmicas no clone
                preencherElementoComDados($clone, $item, $pdo, $website_id);
                $node->appendChild($clone);
            }
        } else {
            // É um item único: preencher o próprio nó com os dados do primeiro item
            preencherElementoComDados($node, $itens[0], $pdo, $website_id);
        }

        // Remover o atributo data-dynamic para não ser processado novamente
        $node->removeAttribute('data-dynamic');
        $node->removeAttribute('data-limit');
    }

    return $dom->saveHTML();
}

// Função auxiliar para preencher um elemento com dados, percorrendo recursivamente os filhos
function preencherElementoComDados($elemento, $dados, $pdo, $website_id) {
    // Processa o próprio elemento (se tiver classes dinâmicas)
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
                    $elemento->setAttribute('alt', htmlspecialchars($dados['titulo'] ?? 'Imagem'));
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
                    // Determinar o tipo pelo contexto (poderia ser passado)
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
                    $elemento->setAttribute('href', $link);
                }
                break;
            // Outras classes podem ser adicionadas aqui
        }
    }

    // Processa os filhos recursivamente
    foreach ($elemento->childNodes as $filho) {
        if ($filho instanceof DOMElement) {
            preencherElementoComDados($filho, $dados, $pdo, $website_id);
        }
    }
}
?>
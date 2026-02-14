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

// ========== FUNÇÕES DE AUTENTICAÇÃO ==========
function isLogado()
{
    return isset($_SESSION['usuario_id']);
}

function getUsuarioAtual($pdo)
{
    if (!isLogado()) return null;
    $stmt = $pdo->prepare("SELECT id, nome, email, is_admin FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ========== FUNÇÕES DE BLOG ==========
function usuarioTemBlog($pdo, $usuario_id)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM websites WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    return $stmt->fetchColumn() > 0;
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
    $stmt = $pdo->query("SELECT * FROM websites ORDER BY RAND() LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getNoticiaDestaque($pdo)
{
    $stmt = $pdo->query("SELECT * FROM noticias WHERE destaque = 1 LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getBleets($pdo)
{
    $stmt = $pdo->query("SELECT * FROM bleets ORDER BY created_at DESC LIMIT 5");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPublicidadeAtiva($pdo)
{
    $stmt = $pdo->query("SELECT * FROM publicidade WHERE ativo = 1 ORDER BY RAND() LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getWebsites($pdo, $categoria_id = null, $limite = 4)
{
    $sql = "SELECT * FROM websites WHERE 1=1";
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
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE categoria_id = ?");
    $stmt->execute([$categoria_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ========== FUNÇÕES DE MINIFICAÇÃO ==========
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

// ========== FUNÇÕES PARA BLOCOS DINÂMICOS ==========

/**
 * Renderiza um bloco dinâmico baseado no tipo e atributos
 */
function renderDynamicBlock($pdo, $type, $attributes = [])
{
    $limit = isset($attributes['data-limit']) ? intval($attributes['data-limit']) : 5;
    $class = isset($attributes['data-class']) ? $attributes['data-class'] : '';
    $classAttr = $class ? ' class="' . htmlspecialchars($class) . '"' : '';

    switch ($type) {
        case 'latest-news':
            // Buscar últimas notícias do banco
            $stmt = $pdo->prepare("SELECT titulo, conteudo, autor, fonte, imagem, created_at 
                                   FROM noticias ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($noticias)) {
                return '<div class="dynamic-block empty">Nenhuma notícia encontrada.</div>';
            }
            
            $html = '<div class="dynamic-news"' . $classAttr . '>';
            foreach ($noticias as $n) {
                $data = date('d/m/Y', strtotime($n['created_at']));
                $imagem = $n['imagem'] ? '<img src="' . htmlspecialchars($n['imagem']) . '" alt="' . htmlspecialchars($n['titulo']) . '" class="news-image">' : '';
                
                $html .= '<div class="news-item">';
                $html .= $imagem;
                $html .= '<h3 class="news-title">' . htmlspecialchars($n['titulo']) . '</h3>';
                $html .= '<p class="news-meta">Por ' . htmlspecialchars($n['autor'] ?: 'Desconhecido') . ' - ' . $data . '</p>';
                $html .= '<p class="news-excerpt">' . htmlspecialchars(substr($n['conteudo'], 0, 150)) . '...</p>';
                $html .= '</div>';
            }
            $html .= '</div>';
            return $html;
            
        case 'recent-bleets':
            // Buscar bleets recentes
            $stmt = $pdo->prepare("SELECT conteudo, autor, created_at FROM bleets ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            $bleets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($bleets)) {
                return '<div class="dynamic-block empty">Nenhum bleet encontrado.</div>';
            }
            
            $html = '<div class="dynamic-bleets"' . $classAttr . '>';
            foreach ($bleets as $b) {
                $data = date('d/m/Y H:i', strtotime($b['created_at']));
                $html .= '<div class="bleet-item">';
                $html .= '<p class="bleet-content">' . htmlspecialchars($b['conteudo']) . '</p>';
                $html .= '<p class="bleet-meta">— ' . htmlspecialchars($b['autor']) . ' em ' . $data . '</p>';
                $html .= '</div>';
            }
            $html .= '</div>';
            return $html;
            
        case 'random-quote':
            // Citações aleatórias
            $quotes = [
                ['texto' => 'A criatividade é a inteligência se divertindo.', 'autor' => 'Albert Einstein'],
                ['texto' => 'Simplicidade é o último grau de sofisticação.', 'autor' => 'Leonardo da Vinci'],
                ['texto' => 'A imaginação é mais importante que o conhecimento.', 'autor' => 'Albert Einstein'],
                ['texto' => 'Não espere por oportunidades, crie-as.', 'autor' => 'George Bernard Shaw'],
                ['texto' => 'O sucesso é a soma de pequenos esforços repetidos dia após dia.', 'autor' => 'Robert Collier'],
                ['texto' => 'A única forma de fazer um ótimo trabalho é amar o que você faz.', 'autor' => 'Steve Jobs'],
            ];
            
            $quote = $quotes[array_rand($quotes)];
            return '<div class="dynamic-quote"' . $classAttr . '>
                    <p class="quote-text">"' . htmlspecialchars($quote['texto']) . '"</p>
                    <p class="quote-author">— ' . htmlspecialchars($quote['autor']) . '</p>
                    </div>';
            
        case 'featured-products':
            // Produtos em destaque (mockado, mas poderia vir do banco)
            $products = [
                ['nome' => 'Produto Premium', 'preco' => 'R$ 99,90', 'imagem' => 'https://via.placeholder.com/200x200?text=Produto+1'],
                ['nome' => 'Pacote Completo', 'preco' => 'R$ 149,90', 'imagem' => 'https://via.placeholder.com/200x200?text=Produto+2'],
                ['nome' => 'Edição Limitada', 'preco' => 'R$ 199,90', 'imagem' => 'https://via.placeholder.com/200x200?text=Produto+3'],
                ['nome' => 'Acessórios', 'preco' => 'R$ 49,90', 'imagem' => 'https://via.placeholder.com/200x200?text=Produto+4'],
            ];
            
            // Limitar conforme solicitado
            $products = array_slice($products, 0, $limit);
            
            $html = '<div class="dynamic-products product-grid"' . $classAttr . '>';
            foreach ($products as $p) {
                $html .= '<div class="product-item">';
                $html .= '<img src="' . htmlspecialchars($p['imagem']) . '" alt="' . htmlspecialchars($p['nome']) . '" class="product-image">';
                $html .= '<h4 class="product-name">' . htmlspecialchars($p['nome']) . '</h4>';
                $html .= '<p class="product-price">' . htmlspecialchars($p['preco']) . '</p>';
                $html .= '<button class="product-buy">Comprar</button>';
                $html .= '</div>';
            }
            $html .= '</div>';
            return $html;
            
        case 'user-stats':
            // Estatísticas do usuário (exemplo)
            $usuario = getUsuarioAtual($pdo);
            if (!$usuario) {
                return '<div class="dynamic-block">Faça login para ver estatísticas.</div>';
            }
            
            // Contar blogs do usuário
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM websites WHERE usuario_id = ?");
            $stmt->execute([$usuario['id']]);
            $totalBlogs = $stmt->fetchColumn();
            
            $html = '<div class="dynamic-stats"' . $classAttr . '>';
            $html .= '<h4>Olá, ' . htmlspecialchars($usuario['nome']) . '!</h4>';
            $html .= '<ul class="stats-list">';
            $html .= '<li><strong>Total de blogs:</strong> ' . $totalBlogs . '</li>';
            $html .= '<li><strong>Email:</strong> ' . htmlspecialchars($usuario['email']) . '</li>';
            $html .= '</ul>';
            $html .= '</div>';
            return $html;
            
        case 'counter':
            // Contador simples
            $initial = isset($attributes['data-initial']) ? intval($attributes['data-initial']) : 0;
            $id = 'counter-' . uniqid();
            return '<div class="dynamic-counter"' . $classAttr . '>
                    <span id="' . $id . '">' . $initial . '</span>
                    <button onclick="document.getElementById(\'' . $id . '\').innerText = parseInt(document.getElementById(\'' . $id . '\').innerText) + 1">+</button>
                    <button onclick="document.getElementById(\'' . $id . '\').innerText = parseInt(document.getElementById(\'' . $id . '\').innerText) - 1">-</button>
                    </div>';
            
        default:
            return '<!-- Bloco dinâmico não reconhecido: ' . htmlspecialchars($type) . ' -->';
    }
}

/**
 * Processa o HTML do website, substituindo blocos dinâmicos
 */
function processDynamicBlocks($pdo, $html)
{
    // Usar DOMDocument para processar com segurança
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // Ignorar warnings de HTML malformado
    
    // Adicionar wrapper para evitar problemas de codificação
    $htmlWithMeta = '<?xml encoding="UTF-8">' . $html;
    $dom->loadHTML($htmlWithMeta, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    $dynamicNodes = $xpath->query("//*[@data-dynamic-type]");
    
    foreach ($dynamicNodes as $node) {
        $type = $node->getAttribute('data-dynamic-type');
        
        // Coletar todos os atributos data-*
        $attributes = [];
        foreach ($node->attributes as $attr) {
            if ($attr->nodeName !== 'data-dynamic-type' && strpos($attr->nodeName, 'data-') === 0) {
                $attributes[$attr->nodeName] = $attr->nodeValue;
            }
        }
        
        // Gerar novo conteúdo
        $newContent = renderDynamicBlock($pdo, $type, $attributes);
        
        // Criar fragmento com o novo HTML
        $fragment = $dom->createDocumentFragment();
        $fragment->appendXML($newContent);
        
        // Substituir o nó original
        $node->parentNode->replaceChild($fragment, $node);
    }
    
    // Extrair apenas o conteúdo do body ou o HTML completo
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body) {
        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }
        return $result;
    }
    
    // Se não encontrou body, retorna o HTML do documento
    return $dom->saveHTML();
}

/**
 * Obtém estatísticas para o dashboard
 */
function getSiteStats($pdo)
{
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
    $stats['total_usuarios'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM websites");
    $stats['total_websites'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM publicidade WHERE ativo = 1");
    $stats['publicidades_ativas'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM noticias");
    $stats['total_noticias'] = $stmt->fetchColumn();
    
    return $stats;
}
?>
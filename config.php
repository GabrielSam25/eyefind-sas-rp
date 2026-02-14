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

// ========== FUNÇÕES PARA CONTEÚDO DINÂMICO SIMPLES ==========

/**
 * Substitui marcadores {{tipo}} no HTML por conteúdo dinâmico
 */
function processDynamicContent($pdo, $html)
{
    // Padrão: encontra {{tipo:parametro}} ou {{tipo}}
    $pattern = '/{{(.*?)}}/';
    
    return preg_replace_callback($pattern, function($matches) use ($pdo) {
        $tag = trim($matches[1]);
        
        // Verificar se tem parâmetro (ex: noticias:5)
        $parts = explode(':', $tag);
        $type = strtolower(trim($parts[0]));
        $param = isset($parts[1]) ? trim($parts[1]) : null;
        
        switch ($type) {
            case 'noticias':
            case 'noticia':
                return renderNoticias($pdo, $param);
                
            case 'bleets':
            case 'bleet':
                return renderBleets($pdo, $param);
                
            case 'citacao':
            case 'quote':
                return renderCitacao();
                
            case 'produtos':
            case 'produto':
                return renderProdutos($pdo, $param);
                
            case 'contador':
            case 'counter':
                return renderContador($param);
                
            case 'data':
            case 'date':
                return date('d/m/Y');
                
            case 'hora':
            case 'time':
                return date('H:i:s');
                
            case 'usuario':
                return renderUsuarioAtual($pdo);
                
            default:
                // Se não reconhece, mantém o marcador
                return $matches[0];
        }
    }, $html);
}

function renderNoticias($pdo, $limite = 3)
{
    $limite = intval($limite) ?: 3;
    
    try {
        $stmt = $pdo->prepare("SELECT titulo, conteudo, autor, imagem, created_at FROM noticias ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limite]);
        $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($noticias)) {
            return '<p>Nenhuma notícia encontrada.</p>';
        }
        
        $html = '<div class="dynamic-noticias">';
        foreach ($noticias as $n) {
            $data = date('d/m/Y', strtotime($n['created_at']));
            $imagem = $n['imagem'] ? '<img src="' . htmlspecialchars($n['imagem']) . '" alt="' . htmlspecialchars($n['titulo']) . '" class="noticia-imagem">' : '';
            
            $html .= '<div class="noticia-item">';
            $html .= $imagem;
            $html .= '<h3 class="noticia-titulo">' . htmlspecialchars($n['titulo']) . '</h3>';
            $html .= '<p class="noticia-meta">Por ' . htmlspecialchars($n['autor'] ?: 'Desconhecido') . ' em ' . $data . '</p>';
            $html .= '<p class="noticia-resumo">' . htmlspecialchars(substr($n['conteudo'], 0, 200)) . '...</p>';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        return $html;
        
    } catch (PDOException $e) {
        return '<p>Erro ao carregar notícias.</p>';
    }
}

function renderBleets($pdo, $limite = 5)
{
    $limite = intval($limite) ?: 5;
    
    try {
        $stmt = $pdo->prepare("SELECT conteudo, autor, created_at FROM bleets ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limite]);
        $bleets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($bleets)) {
            return '<p>Nenhum bleet encontrado.</p>';
        }
        
        $html = '<div class="dynamic-bleets">';
        foreach ($bleets as $b) {
            $data = date('d/m/Y H:i', strtotime($b['created_at']));
            $html .= '<div class="bleet-item">';
            $html .= '<p class="bleet-conteudo">' . htmlspecialchars($b['conteudo']) . '</p>';
            $html .= '<p class="bleet-meta">— ' . htmlspecialchars($b['autor']) . ' · ' . $data . '</p>';
            $html .= '</div>';
        }
        $html .= '</div>';
        
        return $html;
        
    } catch (PDOException $e) {
        return '<p>Erro ao carregar bleets.</p>';
    }
}

function renderCitacao()
{
    $citacoes = [
        ['texto' => 'A vida é o que acontece enquanto você está ocupado fazendo outros planos.', 'autor' => 'John Lennon'],
        ['texto' => 'Seja a mudança que você quer ver no mundo.', 'autor' => 'Mahatma Gandhi'],
        ['texto' => 'O sucesso não é final, o fracasso não é fatal: é a coragem de continuar que conta.', 'autor' => 'Winston Churchill'],
        ['texto' => 'A imaginação é mais importante que o conhecimento.', 'autor' => 'Albert Einstein'],
        ['texto' => 'A simplicidade é o último grau da sofisticação.', 'autor' => 'Leonardo da Vinci'],
        ['texto' => 'O único modo de fazer um excelente trabalho é amar o que você faz.', 'autor' => 'Steve Jobs'],
        ['texto' => 'Não espere por oportunidades, crie-as.', 'autor' => 'George Bernard Shaw'],
    ];
    
    $c = $citacoes[array_rand($citacoes)];
    
    return '<div class="dynamic-citacao">
            <p class="citacao-texto">"' . htmlspecialchars($c['texto']) . '"</p>
            <p class="citacao-autor">— ' . htmlspecialchars($c['autor']) . '</p>
            </div>';
}

function renderProdutos($pdo, $limite = 4)
{
    $limite = intval($limite) ?: 4;
    
    // Por enquanto, produtos mockados. Depois pode ter tabela própria
    $produtos = [
        ['nome' => 'Produto Premium', 'preco' => 'R$ 99,90', 'imagem' => 'https://via.placeholder.com/200x150?text=Premium'],
        ['nome' => 'Pacote Completo', 'preco' => 'R$ 149,90', 'imagem' => 'https://via.placeholder.com/200x150?text=Pacote'],
        ['nome' => 'Edição Limitada', 'preco' => 'R$ 199,90', 'imagem' => 'https://via.placeholder.com/200x150?text=Limited'],
        ['nome' => 'Acessórios', 'preco' => 'R$ 49,90', 'imagem' => 'https://via.placeholder.com/200x150?text=Acessorios'],
        ['nome' => 'Curso Online', 'preco' => 'R$ 297,00', 'imagem' => 'https://via.placeholder.com/200x150?text=Curso'],
        ['nome' => 'E-book', 'preco' => 'R$ 29,90', 'imagem' => 'https://via.placeholder.com/200x150?text=Ebook'],
    ];
    
    $produtos = array_slice($produtos, 0, $limite);
    
    $html = '<div class="dynamic-produtos grade-produtos">';
    foreach ($produtos as $p) {
        $html .= '<div class="produto-item">';
        $html .= '<img src="' . $p['imagem'] . '" alt="' . $p['nome'] . '" class="produto-imagem">';
        $html .= '<h4 class="produto-nome">' . $p['nome'] . '</h4>';
        $html .= '<p class="produto-preco">' . $p['preco'] . '</p>';
        $html .= '<button class="produto-botao">Comprar</button>';
        $html .= '</div>';
    }
    $html .= '</div>';
    
    return $html;
}

function renderContador($inicial = 0)
{
    $inicial = intval($inicial) ?: 0;
    $id = 'counter-' . uniqid();
    
    return '<div class="dynamic-contador">
            <span id="' . $id . '" class="contador-valor">' . $inicial . '</span>
            <div class="contador-botoes">
                <button onclick="document.getElementById(\'' . $id . '\').innerText = parseInt(document.getElementById(\'' . $id . '\').innerText) + 1">+</button>
                <button onclick="document.getElementById(\'' . $id . '\').innerText = parseInt(document.getElementById(\'' . $id . '\').innerText) - 1">-</button>
                <button onclick="document.getElementById(\'' . $id . '\').innerText = ' . $inicial . '">Reset</button>
            </div>
            </div>';
}

function renderUsuarioAtual($pdo)
{
    if (!isLogado()) {
        return '<p>Você não está logado.</p>';
    }
    
    $usuario = getUsuarioAtual($pdo);
    
    return '<div class="dynamic-usuario">
            <p>Olá, <strong>' . htmlspecialchars($usuario['nome']) . '</strong>!</p>
            <p>Email: ' . htmlspecialchars($usuario['email']) . '</p>
            </div>';
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
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bleets");
    $stats['total_bleets'] = $stmt->fetchColumn();
    
    return $stats;
}
?>
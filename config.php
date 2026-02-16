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


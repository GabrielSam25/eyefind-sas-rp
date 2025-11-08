<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit();
}

$perfil_id = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['usuario_id'];

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$perfil_id]);
$perfil = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$perfil) {
    header('Location: index.php');
    exit();
}

$eh_perfil_pessoal = ($perfil_id == $_SESSION['usuario_id']);

// Processar atualização do perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil']) && $eh_perfil_pessoal) {
    $dados = [
        'bio' => trim($_POST['bio'] ?? ''),
        'estado_civil' => trim($_POST['estado_civil'] ?? ''),
        'genero' => trim($_POST['genero'] ?? ''),
        'data_nascimento' => trim($_POST['data_nascimento'] ?? null),
        'cidade' => trim($_POST['cidade'] ?? ''),
        'trabalho' => trim($_POST['trabalho'] ?? ''),
        'escolaridade' => trim($_POST['escolaridade'] ?? ''),
        'interesses' => trim($_POST['interesses'] ?? ''),
        'id' => $perfil_id
    ];

    // Processar upload de foto de perfil
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/perfis/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
        $extensao = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));

        if (in_array($extensao, $extensoes_permitidas)) {
            $nome_arquivo = uniqid() . '.' . $extensao;
            $destino = $upload_dir . $nome_arquivo;

            if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $destino)) {
                $dados['foto_perfil'] = $destino;

                // Remover foto antiga se existir
                if (!empty($perfil['foto_perfil']) && file_exists($perfil['foto_perfil'])) {
                    unlink($perfil['foto_perfil']);
                }
            }
        }
    }

    try {
        $campos = [];
        $valores = [];
        foreach ($dados as $campo => $valor) {
            if ($campo !== 'id' && $valor !== null) {
                $campos[] = "$campo = ?";
                $valores[] = $valor;
            }
        }
        $valores[] = $dados['id'];

        $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($valores);

        $_SESSION['sucesso'] = "Perfil atualizado com sucesso!";
        header("Location: profile.php?id=$perfil_id");
        exit();
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao atualizar perfil: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_post'])) {
    $post_id = (int)$_POST['post_id'];

    $stmt = $pdo->prepare("SELECT usuario_id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if ($post && ($post['usuario_id'] == $_SESSION['usuario_id'] || $_SESSION['is_admin'])) {
        $pdo->prepare("DELETE FROM comentarios WHERE post_id = ?")->execute([$post_id]);
        $pdo->prepare("DELETE FROM curtidas WHERE post_id = ?")->execute([$post_id]);
        $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$post_id]);

        $_SESSION['sucesso'] = "Post excluído com sucesso!";
    } else {
        $_SESSION['erro'] = "Você não tem permissão para excluir este post";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM posts WHERE usuario_id = ?) as total_posts,
        (SELECT COUNT(*) FROM amigos WHERE (usuario_id = ? OR amigo_id = ?) AND 
         (usuario_id = ? OR amigo_id = ?)) as total_amigos,
        (SELECT COUNT(*) FROM seguidores WHERE perfil_id = ?) as total_seguidores
");
$stmt->execute([$perfil_id, $perfil_id, $perfil_id, $perfil_id, $perfil_id, $perfil_id]);
$estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM curtidas WHERE post_id = p.id) as curtidas,
           (SELECT COUNT(*) FROM comentarios WHERE post_id = p.id) as comentarios,
           EXISTS(SELECT 1 FROM curtidas WHERE post_id = p.id AND usuario_id = ?) as curtido
    FROM posts p
    WHERE p.usuario_id = ?
    ORDER BY p.data_postagem DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['usuario_id'], $perfil_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['seguir'])) {
        $stmt = $pdo->prepare("
            INSERT INTO seguidores (usuario_id, perfil_id) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE ativo = NOT ativo
        ");
        $stmt->execute([$_SESSION['usuario_id'], $perfil_id]);
    } elseif (isset($_POST['curtir'])) {
        $post_id = (int)$_POST['post_id'];
        $stmt = $pdo->prepare("
            INSERT INTO curtidas (usuario_id, post_id) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE ativo = NOT ativo
        ");
        $stmt->execute([$_SESSION['usuario_id'], $post_id]);
    } elseif (isset($_POST['comentar'])) {
        $post_id = (int)$_POST['post_id'];
        $comentario = trim($_POST['comentario']);
        if (!empty($comentario)) {
            $stmt = $pdo->prepare("
                INSERT INTO comentarios (usuario_id, post_id, conteudo) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$_SESSION['usuario_id'], $post_id, $comentario]);
        }
    }

    header("Location: profile.php?id=$perfil_id");
    exit();
}

$stmt = $pdo->prepare("
    SELECT 1 FROM seguidores 
    WHERE usuario_id = ? AND perfil_id = ? AND ativo = 1
");
$stmt->execute([$_SESSION['usuario_id'], $perfil_id]);
$segue_perfil = $stmt->fetchColumn();

$publicidade = $pdo->query("SELECT * FROM publicidade WHERE ativo = 1 ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);

function getIniciais($nome)
{
    $nomes = explode(' ', $nome);
    $iniciais = strtoupper(substr($nomes[0], 0, 1));
    if (count($nomes) > 1) {
        $iniciais .= strtoupper(substr(end($nomes), 0, 1));
    }
    return $iniciais;
}

function formatarData($data)
{
    $agora = new DateTime('now');
    $postagem = new DateTime($data);
    $intervalo = $agora->diff($postagem);

    if ($intervalo->y > 0) return "há {$intervalo->y} ano" . ($intervalo->y > 1 ? 's' : '');
    if ($intervalo->m > 0) return "há {$intervalo->m} mês" . ($intervalo->m > 1 ? 'es' : '');
    if ($intervalo->d > 0) return "há {$intervalo->d} dia" . ($intervalo->d > 1 ? 's' : '');
    if ($intervalo->h > 0) return "há {$intervalo->h} hora" . ($intervalo->h > 1 ? 's' : '');
    if ($intervalo->i > 0) return "há {$intervalo->i} minuto" . ($intervalo->i > 1 ? 's' : '');
    return "agora mesmo";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($perfil['nome']) ?> - Lifeinvader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .red-gradient {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
        }

        .hover-lift {
            transition: transform 0.2s ease;
        }

        .hover-lift:hover {
            transform: translateY(-1px);
        }
    </style>
</head>

<body class="bg-pink-50">
    <!-- Header -->
    <header class="red-gradient text-white">
        <div class="flex justify-between items-center px-6 py-4">
            <div class="flex items-center space-x-2">
                <img src="/imagens/lifelogo.png" alt="LifeInvader Logo" class="h-16">
            </div>
            <div class="flex items-center space-x-4">
                <div class="bg-white bg-opacity-20 px-2 py-1 rounded flex items-center">
                    <div class="w-6 h-6 bg-white rounded mr-2"></div>
                    <span class="text-sm"><?= htmlspecialchars($_SESSION['usuario_nome']) ?></span>
                </div>
                <a href="logout.php" class="text-sm hover:underline">sair</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-4">
        <div class="grid grid-cols-12 gap-4">
            <!-- Sidebar Esquerda - About (3 colunas) -->
            <div class="col-span-3">
                <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
                    <h3 class="font-bold text-base mb-3 text-gray-800">Sobre</h3>

                    <?php if ($eh_perfil_pessoal): ?>
                        <button onclick="toggleEditarPerfil()" class="mb-3 text-sm bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700">
                            <i class="fas fa-edit mr-1"></i> Editar Perfil
                        </button>

                        <div id="editar-perfil" class="hidden mb-4">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Foto de Perfil</label>
                                        <input type="file" name="foto_perfil" accept="image/*" class="mt-1 block w-full text-xs">
                                        <?php if (!empty($perfil['foto_perfil'])): ?>
                                            <img src="<?= htmlspecialchars($perfil['foto_perfil']) ?>" class="mt-2 h-16 w-16 rounded-full object-cover">
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Biografia</label>
                                        <textarea name="bio" class="mt-1 block w-full p-2 border border-gray-300 rounded text-sm"><?= htmlspecialchars($perfil['bio'] ?? '') ?></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Estado Civil</label>
                                        <select name="estado_civil" class="mt-1 block w-full p-2 border border-gray-300 rounded text-sm">
                                            <option value="">Selecione</option>
                                            <option value="solteiro(a)" <?= ($perfil['estado_civil'] ?? '') === 'solteiro(a)' ? 'selected' : '' ?>>Solteiro(a)</option>
                                            <option value="casado(a)" <?= ($perfil['estado_civil'] ?? '') === 'casado(a)' ? 'selected' : '' ?>>Casado(a)</option>
                                            <option value="divorciado(a)" <?= ($perfil['estado_civil'] ?? '') === 'divorciado(a)' ? 'selected' : '' ?>>Divorciado(a)</option>
                                            <option value="viuvo(a)" <?= ($perfil['estado_civil'] ?? '') === 'viuvo(a)' ? 'selected' : '' ?>>Viúvo(a)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Gênero</label>
                                        <select name="genero" class="mt-1 block w-full p-2 border border-gray-300 rounded text-sm">
                                            <option value="">Selecione</option>
                                            <option value="masculino" <?= ($perfil['genero'] ?? '') === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                                            <option value="feminino" <?= ($perfil['genero'] ?? '') === 'feminino' ? 'selected' : '' ?>>Feminino</option>
                                            <option value="outro" <?= ($perfil['genero'] ?? '') === 'outro' ? 'selected' : '' ?>>Outro</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Data de Nascimento</label>
                                        <input type="date" name="data_nascimento" value="<?= htmlspecialchars($perfil['data_nascimento'] ?? '') ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded text-sm">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Cidade</label>
                                        <input type="text" name="cidade" value="<?= htmlspecialchars($perfil['cidade'] ?? '') ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded text-sm">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Trabalho</label>
                                        <input type="text" name="trabalho" value="<?= htmlspecialchars($perfil['trabalho'] ?? '') ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded text-sm">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Escolaridade</label>
                                        <input type="text" name="escolaridade" value="<?= htmlspecialchars($perfil['escolaridade'] ?? '') ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded text-sm">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium text-gray-700">Interesses</label>
                                        <textarea name="interesses" class="mt-1 block w-full p-2 border border-gray-300 rounded text-sm"><?= htmlspecialchars($perfil['interesses'] ?? '') ?></textarea>
                                    </div>

                                    <div class="pt-2">
                                        <button type="submit" name="atualizar_perfil" class="bg-red-600 text-white px-4 py-2 rounded text-sm hover:bg-red-700">
                                            Salvar Alterações
                                        </button>
                                        <button type="button" onclick="toggleEditarPerfil()" class="ml-2 bg-gray-200 text-gray-800 px-4 py-2 rounded text-sm hover:bg-gray-300">
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div id="info-perfil">
                        <!-- Exibir foto de perfil se existir -->
                        <?php if (!empty($perfil['foto_perfil'])): ?>
                            <div class="mb-3">
                                <img src="<?= htmlspecialchars($perfil['foto_perfil']) ?>" class="h-24 w-24 rounded-full object-cover">
                            </div>
                        <?php endif; ?>

                        <p class="text-xs text-gray-600 leading-relaxed mb-3">
                            <?= nl2br(htmlspecialchars($perfil['bio'] ?? 'Este usuário ainda não adicionou uma biografia.')) ?>
                        </p>

                        <div class="space-y-3 mt-4">
                            <div>
                                <h4 class="text-xs font-bold text-gray-800">Detalhes Pessoais</h4>
                                <?php if (!empty($perfil['estado_civil'])): ?>
                                    <p class="text-xs text-gray-600">
                                        <i class="fas fa-heart mr-1"></i>
                                        <?= htmlspecialchars($perfil['estado_civil']) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($perfil['genero'])): ?>
                                    <p class="text-xs text-gray-600">
                                        <i class="fas fa-venus-mars mr-1"></i>
                                        <?= htmlspecialchars($perfil['genero']) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($perfil['data_nascimento'])): ?>
                                    <p class="text-xs text-gray-600">
                                        <i class="fas fa-birthday-cake mr-1"></i>
                                        Nascimento: <?= date('d/m/Y', strtotime($perfil['data_nascimento'])) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($perfil['cidade'])): ?>
                                    <p class="text-xs text-gray-600">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        <?= htmlspecialchars($perfil['cidade']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <h4 class="text-xs font-bold text-gray-800">Profissional</h4>
                                <?php if (!empty($perfil['trabalho'])): ?>
                                    <p class="text-xs text-gray-600">
                                        <i class="fas fa-briefcase mr-1"></i>
                                        <?= htmlspecialchars($perfil['trabalho']) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($perfil['escolaridade'])): ?>
                                    <p class="text-xs text-gray-600">
                                        <i class="fas fa-graduation-cap mr-1"></i>
                                        <?= htmlspecialchars($perfil['escolaridade']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($perfil['interesses'])): ?>
                                <div>
                                    <h4 class="text-xs font-bold text-gray-800">Interesses</h4>
                                    <p class="text-xs text-gray-600">
                                        <?= nl2br(htmlspecialchars($perfil['interesses'])) ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <div>
                                <h4 class="text-xs font-bold text-gray-800">Estatísticas</h4>
                                <p class="text-xs text-gray-600">
                                    <i class="fas fa-newspaper mr-1"></i>
                                    <?= $estatisticas['total_posts'] ?> posts
                                </p>
                                <p class="text-xs text-gray-600">
                                    <i class="fas fa-users mr-1"></i>
                                    <?= $estatisticas['total_amigos'] ?> amigos
                                </p>
                                <p class="text-xs text-gray-600">
                                    <i class="fas fa-user-friends mr-1"></i>
                                    <?= $estatisticas['total_seguidores'] ?> seguidores
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Conteúdo Central - Profile e Posts (6 colunas) -->
            <div class="col-span-6">
                <!-- Profile Header -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-4">
                    <div class="flex items-start gap-4">
                        <!-- Profile Image -->
                        <div class="flex-shrink-0">
                            <?php if (!empty($perfil['foto_perfil'])): ?>
                                <img src="<?= htmlspecialchars($perfil['foto_perfil']) ?>" class="w-24 h-24 rounded-full object-cover border-2 border-white shadow">
                            <?php else: ?>
                                <div class="w-24 h-24 bg-gradient-to-br from-red-400 to-red-600 rounded-full flex items-center justify-center text-white text-3xl font-bold">
                                    <?= getIniciais($perfil['nome']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Profile Info -->
                        <div class="flex-1">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h1 class="text-2xl font-bold text-red-600 mb-1"><?= htmlspecialchars($perfil['nome']) ?></h1>
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="text-gray-600 text-sm">
                                            <i class="fas fa-at"></i> <?= htmlspecialchars($perfil['email']) ?>
                                        </span>
                                    </div>
                                </div>

                                <?php if (!$eh_perfil_pessoal): ?>
                                    <form method="POST">
                                        <button type="submit" name="seguir" class="px-4 py-2 rounded-full text-sm font-medium <?= $segue_perfil ? 'bg-gray-200 text-gray-800' : 'bg-red-600 text-white' ?>">
                                            <?= $segue_perfil ? 'Seguindo' : 'Seguir' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="bg-red-50 border-l-4 border-red-400 p-3 mb-3">
                                <p class="text-red-700 font-medium text-sm">
                                    <?= $eh_perfil_pessoal ? 'Seu perfil' : 'Perfil de usuário' ?>
                                </p>
                                <p class="text-red-600 text-xs">
                                    <?= $estatisticas['total_seguidores'] ?> pessoa<?= $estatisticas['total_seguidores'] != 1 ? 's' : '' ?> seguindo
                                </p>
                            </div>
                            <!-- Photos Section -->
                            <div class="mt-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-camera text-blue-600"></i>
                                    <span class="text-blue-600 text-sm font-medium">Fotos</span>
                                </div>

                                <?php
                                // Buscar as últimas 6 imagens dos posts do usuário
                                $stmt_fotos = $pdo->prepare("
        SELECT imagem 
        FROM posts 
        WHERE usuario_id = ? AND imagem IS NOT NULL 
        ORDER BY data_postagem DESC 
        LIMIT 6
    ");
                                $stmt_fotos->execute([$perfil_id]);
                                $fotos = $stmt_fotos->fetchAll(PDO::FETCH_ASSOC);

                                if (!empty($fotos)): ?>
                                    <div class="grid grid-cols-3 gap-2">
                                        <?php foreach ($fotos as $foto): ?>
                                            <a href="#" onclick="abrirModal('<?= htmlspecialchars($foto['imagem']) ?>')" class="block overflow-hidden rounded-lg hover:opacity-90 transition">
                                                <img src="<?= htmlspecialchars($foto['imagem']) ?>"
                                                    alt="Foto do post"
                                                    class="w-full h-24 object-cover">
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-gray-500 text-sm">Nenhuma foto publicada ainda.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Posts -->
                <div class="space-y-4">
                    <?php if (empty($posts) && $eh_perfil_pessoal): ?>
                        <div class="bg-white rounded-lg shadow-sm p-6 text-center">
                            <p class="text-gray-600 mb-4">Você ainda não fez nenhum post.</p>
                            <a href="lifeinvader.php" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700 transition">
                                Criar primeiro post
                            </a>
                        </div>
                    <?php elseif (empty($posts)): ?>
                        <div class="bg-white rounded-lg shadow-sm p-6 text-center">
                            <p class="text-gray-600">Este usuário ainda não fez nenhum post.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <div class="bg-white rounded-lg shadow-sm p-4 hover-lift">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-gradient-to-br from-red-400 to-red-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                            <?= getIniciais($perfil['nome']) ?>
                                        </div>
                                        <span class="text-sm font-medium"><?= htmlspecialchars($perfil['nome']) ?></span>
                                        <span class="text-xs text-gray-500"><?= formatarData($post['data_postagem']) ?></span>
                                    </div>

                                    <!-- Botão de menu e dropdown -->
                                    <div class="relative">
                                        <button onclick="toggleMenu(<?= $post['id'] ?>)" class="text-gray-400 hover:text-gray-600 p-2 rounded-full hover:bg-gray-100">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>

                                        <!-- Menu dropdown -->
                                        <div id="menu-<?= $post['id'] ?>" class="hidden absolute right-0 mt-1 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200 post-menu">
                                            <?php if ($post['usuario_id'] == $_SESSION['usuario_id'] || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])): ?>
                                                <form method="POST" class="p-1">
                                                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                                    <button type="submit" name="excluir_post"
                                                        class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-md flex items-center"
                                                        onclick="return confirm('Tem certeza que deseja excluir este post?');">
                                                        <i class="fas fa-trash mr-2"></i> Excluir post
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md flex items-center">
                                                <i class="fas fa-flag mr-2"></i> Denunciar
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <p class="mt-3 text-sm text-gray-700 mb-4">
                                    <?= nl2br(htmlspecialchars($post['conteudo'])) ?>
                                </p>

                                <!-- Adicione esta parte para exibir a imagem do post -->
                                <?php if (!empty($post['imagem'])): ?>
                                    <div class="mb-4">
                                        <img src="<?= htmlspecialchars($post['imagem']) ?>"
                                            alt="Imagem do post"
                                            class="w-full h-auto max-h-96 object-cover rounded-lg">
                                    </div>
                                <?php endif; ?>

                                <!-- Interações -->
                                <div class="flex items-center justify-between text-sm text-gray-500 pt-4 border-t">
                                    <form method="POST" class="flex items-center space-x-2">
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                        <button type="submit" name="curtir" class="hover:text-red-600 transition-colors">
                                            <i class="fas fa-heart <?= $post['curtido'] ? 'text-red-600' : '' ?>"></i>
                                            <span><?= $post['curtidas'] ?> curtidas</span>
                                        </button>
                                    </form>

                                    <button onclick="toggleComentarios(<?= $post['id'] ?>)" class="flex items-center space-x-2 hover:text-blue-600 transition-colors">
                                        <i class="fas fa-comment"></i>
                                        <span><?= $post['comentarios'] ?> comentários</span>
                                    </button>

                                    <button class="flex items-center space-x-2 hover:text-green-600 transition-colors">
                                        <i class="fas fa-share"></i>
                                        <span>Compartilhar</span>
                                    </button>
                                </div>

                                <!-- Seção de comentários (simplificada) -->
                                <div id="comentarios-<?= $post['id'] ?>" class="mt-4 hidden">
                                    <form method="POST" class="flex items-center gap-2 bg-gray-50 p-2 rounded-lg">
                                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-red-600">
                                            <?= getIniciais($_SESSION['usuario_nome']) ?>
                                        </div>
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                        <input type="text" name="comentario" placeholder="Escreva um comentário..."
                                            class="flex-1 px-3 py-2 border border-gray-300 rounded-full text-sm focus:outline-none focus:border-red-300"
                                            required>
                                        <button type="submit" name="comentar" class="text-red-600 hover:text-red-800 p-2">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar direita -->
            <aside class="col-span-3 space-y-6">
                <!-- Área de Patrocinados -->
                <?php if ($publicidade): ?>
                    <div class="sponsored-gradient rounded-xl shadow-lg p-6 border-2 border-yellow-200">
                        <h2 class="text-orange-800 font-bold mb-4 text-lg">
                            <i class="fas fa-bullhorn mr-2"></i>Patrocinados
                        </h2>
                        <div class="space-y-4">
                            <a href="<?= htmlspecialchars($publicidade['url']) ?>" class="block overflow-hidden rounded-lg shadow-md border border-orange-200 hover:scale-[1.02] transition">
                                <img src="<?= htmlspecialchars($publicidade['imagem']) ?>" alt="<?= htmlspecialchars($publicidade['nome']) ?>" class="w-full h-auto rounded-lg">
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <script>
        function toggleEditarPerfil() {
            const editarPerfil = document.getElementById('editar-perfil');
            const infoPerfil = document.getElementById('info-perfil');

            editarPerfil.classList.toggle('hidden');
            infoPerfil.classList.toggle('hidden');
        }

        function toggleComentarios(postId) {
            const comentarios = document.getElementById(`comentarios-${postId}`);
            comentarios.classList.toggle('hidden');

            if (!comentarios.classList.contains('hidden')) {
                setTimeout(() => {
                    const input = comentarios.querySelector('input[name="comentario"]');
                    input.focus();
                }, 100);
            }
        }

        function toggleMenu(postId) {
            const menu = document.getElementById(`menu-${postId}`);
            menu.classList.toggle('hidden');
        }

        // Fechar menus quando clicar fora
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.post-menu') && !event.target.matches('[onclick^="toggleMenu"]')) {
                document.querySelectorAll('.post-menu').forEach(menu => {
                    menu.classList.add('hidden');
                });
            }
        });
    </script>
</body>

</html>

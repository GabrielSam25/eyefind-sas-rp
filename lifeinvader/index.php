<?php
date_default_timezone_set('America/Sao_Paulo');

require_once 'config.php';

if (!isLogado()) {
    header('Location: /login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentar'])) {
    $post_id = (int)$_POST['post_id'];
    $comentario = trim($_POST['comentario']);
    $imagem_comentario = null;

    if (isset($_FILES['imagem_comentario']) && $_FILES['imagem_comentario']['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($_FILES['imagem_comentario']['name'], PATHINFO_EXTENSION));
        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($extensao, $extensoes_permitidas)) {
            $upload_dir = 'uploads/comentarios/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $nome_arquivo = uniqid() . '.' . $extensao;
            $destino = $upload_dir . $nome_arquivo;

            if (move_uploaded_file($_FILES['imagem_comentario']['tmp_name'], $destino)) {
                $imagem_comentario = $destino;
            }
        }
    }

    if (!empty($comentario) || !empty($imagem_comentario)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO comentarios (post_id, usuario_id, conteudo, imagem, data_comentario) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$post_id, $_SESSION['usuario_id'], $comentario, $imagem_comentario]);

            $_SESSION['sucesso'] = "Comentário adicionado com sucesso!";
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao adicionar comentário: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Você precisa digitar um comentário ou adicionar uma imagem!";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_amigo'])) {
    $amigo_id = (int)$_POST['amigo_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO amigos (usuario_id, amigo_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['usuario_id'], $amigo_id]);
        $_SESSION['sucesso'] = "Amigo adicionado com sucesso!";
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao adicionar amigo: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['curtir'])) {
    $post_id = (int)$_POST['post_id'];

    $stmt = $pdo->prepare("SELECT id FROM curtidas WHERE post_id = ? AND usuario_id = ?");
    $stmt->execute([$post_id, $_SESSION['usuario_id']]);
    $curtida = $stmt->fetch();

    try {
        if ($curtida) {

            $stmt = $pdo->prepare("DELETE FROM curtidas WHERE id = ?");
            $stmt->execute([$curtida['id']]);
        } else {

            $stmt = $pdo->prepare("INSERT INTO curtidas (post_id, usuario_id, data_curtida) VALUES (?, ?, NOW())");
            $stmt->execute([$post_id, $_SESSION['usuario_id']]);
        }
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao processar curtida: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conteudo'])) {
    error_log("Iniciando processamento do post");

    $conteudo = trim($_POST['conteudo']);
    $imagens_json = null;
    $video_path = null;

    // Processamento de imagens (mantenha seu código existente)
    $upload_dir = 'uploads/posts/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Falha ao criar diretório: " . $upload_dir);
            $_SESSION['erro'] = "Erro no servidor ao processar mídia";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Processamento de vídeos
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
        $extensoes_permitidas = ['mp4', 'webm', 'mov', 'avi'];

        if (!in_array($extensao, $extensoes_permitidas)) {
            $_SESSION['erro'] = "Formato de vídeo não suportado. Use MP4, WebM, MOV ou AVI.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        // Verificar tamanho do vídeo
        $max_size = 100 * 1024 * 1024; // 100MB
        if ($_FILES['video']['size'] > $max_size) {
            // Se for maior que 100MB, tentar comprimir
            $video_path = comprimirVideo($_FILES['video']['tmp_name'], $extensao, $upload_dir);

            if (!$video_path) {
                $_SESSION['erro'] = "Falha ao comprimir vídeo. Tente um arquivo menor.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        } else {
            // Se for menor que 100MB, mover normalmente
            $nome_arquivo = uniqid() . '.' . $extensao;
            $destino = $upload_dir . $nome_arquivo;

            if (move_uploaded_file($_FILES['video']['tmp_name'], $destino)) {
                $video_path = $destino;
            } else {
                error_log("Falha ao mover vídeo: " . print_r(error_get_last(), true));
                $_SESSION['erro'] = "Erro ao enviar vídeo.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        }
    }

    // Processamento de imagens (mantenha seu código existente)
    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
    $imagens_path = [];

    if (isset($_FILES['imagens']) && is_array($_FILES['imagens']['name'])) {
        foreach ($_FILES['imagens']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['imagens']['error'][$key] === UPLOAD_ERR_OK) {
                $extensao = strtolower(pathinfo($_FILES['imagens']['name'][$key], PATHINFO_EXTENSION));

                if (in_array($extensao, $extensoes_permitidas)) {
                    $nome_arquivo = uniqid() . '.' . $extensao;
                    $destino = $upload_dir . $nome_arquivo;

                    if (move_uploaded_file($tmp_name, $destino)) {
                        $imagens_path[] = $destino;
                    }
                }
            }
        }
    }

    if (!empty($imagens_path)) {
        $imagens_json = json_encode($imagens_path);
    }

    if (!empty($conteudo) || !empty($imagens_path) || !empty($video_path)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO posts (usuario_id, conteudo, imagem, video, data_postagem) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt->execute([$_SESSION['usuario_id'], $conteudo, $imagens_json, $video_path])) {
                $_SESSION['sucesso'] = "Post publicado com sucesso!";
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("Erro ao inserir post: " . print_r($errorInfo, true));
                $_SESSION['erro'] = "Erro ao publicar post: " . $errorInfo[2];
            }
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao publicar post: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Você precisa adicionar um texto, imagem ou vídeo!";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM posts WHERE usuario_id = u.id) as total_posts,
        (SELECT COUNT(*) FROM amigos WHERE usuario_id = u.id OR amigo_id = u.id) as total_amigos,
        (SELECT COUNT(*) FROM curtidas WHERE usuario_id = u.id) as total_likes
    FROM usuarios u
    WHERE u.id = ?
");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

function getIniciais($nome)
{
    $nomes = explode(' ', $nome);
    $iniciais = strtoupper(substr($nomes[0], 0, 1));
    if (count($nomes) > 1) {
        $iniciais .= strtoupper(substr(end($nomes), 0, 1));
    }
    return $iniciais;
}

function gerarUsername($nome)
{
    return substr(preg_replace('/[^a-z0-9]/', '', strtolower(str_replace(' ', '', $nome))), 0, 15);
}

function formatarData($data)
{
    $agora = new DateTime();
    $postagem = new DateTime($data);
    $intervalo = $agora->diff($postagem);

    if ($intervalo->y > 0) return "há {$intervalo->y} ano" . ($intervalo->y > 1 ? 's' : '');
    if ($intervalo->m > 0) return "há {$intervalo->m} mês" . ($intervalo->m > 1 ? 'es' : '');
    if ($intervalo->d > 0) return "há {$intervalo->d} dia" . ($intervalo->d > 1 ? 's' : '');
    if ($intervalo->h > 0) return "há {$intervalo->h} hora" . ($intervalo->h > 1 ? 's' : '');
    if ($intervalo->i > 0) return "há {$intervalo->i} minuto" . ($intervalo->i > 1 ? 's' : '');
    return "agora mesmo";
}

$iniciais = getIniciais($usuario_nome);
$username = gerarUsername($usuario_nome);

$posts = $pdo->query("
    SELECT p.*, u.nome as autor, 
           (SELECT COUNT(*) FROM curtidas WHERE post_id = p.id) as curtidas,
           (SELECT COUNT(*) FROM comentarios WHERE post_id = p.id) as comentarios,
           EXISTS(SELECT 1 FROM curtidas WHERE post_id = p.id AND usuario_id = $usuario_id) as curtido
    FROM posts p
    JOIN usuarios u ON p.usuario_id = u.id
    ORDER BY p.data_postagem DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($posts as &$post) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.nome as autor, u.foto_perfil 
        FROM comentarios c
        JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.post_id = ?
        ORDER BY c.data_comentario DESC
        LIMIT 3
");
    $stmt->execute([$post['id']]);
    $post['comentarios_lista'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($post);

$publicidade = $pdo->query("SELECT * FROM publicidade WHERE ativo = 1 ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$sugestoes_amigos = [];
$stmt = $pdo->prepare("
    SELECT u.id, u.nome, u.foto_perfil 
    FROM usuarios u
    WHERE u.id != ?
    ORDER BY RAND()
    LIMIT 3
");
$stmt->execute([$usuario_id]);
$sugestoes_amigos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lifeinvader - Início</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/index.css">
</head>

<body class="bg-pink-50">

    <header class="red-gradient text-white">
        <div class="flex justify-between items-center px-6 py-4">
            <div class="flex items-center space-x-2">
                <img src="/imagens/lifelogo.png" alt="LifeInvader Logo" class="h-16">
            </div>
            <div class="flex items-center space-x-4">
                <div class="bg-white bg-opacity-20 px-2 py-1 rounded flex items-center">
                    <div class="w-6 h-6 bg-white rounded mr-2"></div>
                    <span class="text-sm"><?= htmlspecialchars($username) ?></span>
                </div>
                <a href="logout.php" class="text-sm hover:underline">sair</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-12 gap-6">

        <aside class="col-span-3 space-y-6">

            <div class="bg-white rounded-xl shadow-lg p-6 hover-lift">
                <h2 class="text-red-600 font-bold mb-4 text-lg">
                    <i class="fas fa-user mr-2"></i>Seu Perfil
                </h2>
                <div class="flex items-center space-x-4 mb-6">
                    <?php if (!empty($usuario['foto_perfil'])): ?>
                        <img src="<?= htmlspecialchars($usuario['foto_perfil']) ?>"
                            class="w-16 h-16 rounded-full object-cover border-2 border-red-200 shadow-lg"
                            alt="Foto de perfil">
                    <?php else: ?>
                        <div class="w-16 h-16 bg-gradient-to-br from-red-400 to-red-600 rounded-full flex items-center justify-center text-white text-xl font-bold shadow-lg">
                            <?= $iniciais ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($usuario_nome) ?></p>
                        <p class="text-xs text-gray-500 mb-2">@<?= htmlspecialchars($username) ?></p>
                        <a href="profile.php" class="text-xs bg-red-100 text-red-700 px-3 py-1 rounded-full hover:bg-red-200 transition-colors">
                            Ver perfil completo
                        </a>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4 text-center text-sm">
                    <div>
                        <p class="font-bold text-red-600"><?= $usuario['total_posts'] ?? 0 ?></p>
                        <p class="text-gray-500">Posts</p>
                    </div>
                    <div>
                        <p class="font-bold text-red-600"><?= $usuario['total_amigos'] ?? 0 ?></p>
                        <p class="text-gray-500">Amigos</p>
                    </div>
                    <div>
                        <p class="font-bold text-red-600"><?= $usuario['total_likes'] ?? 0 ?></p>
                        <p class="text-gray-500">Likes</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <ul class="space-y-3">
                    <li><a href="friends.php" class="flex items-center text-gray-700 hover:text-red-600 hover:bg-red-50 px-3 py-2 rounded-lg transition-all">
                            <i class="fas fa-users mr-3"></i>Amigos
                        </a></li>
                    <li><a href="#" class="flex items-center text-gray-700 hover:text-red-600 hover:bg-red-50 px-3 py-2 rounded-lg transition-all">
                            <i class="fas fa-comments mr-3"></i>Mensagens
                        </a></li>
                    <li><a href="#" class="flex items-center text-gray-700 hover:text-red-600 hover:bg-red-50 px-3 py-2 rounded-lg transition-all">
                            <i class="fas fa-camera mr-3"></i>Fotos
                        </a></li>
                    <li><a href="#" class="flex items-center text-gray-700 hover:text-red-600 hover:bg-red-50 px-3 py-2 rounded-lg transition-all">
                            <i class="fas fa-gamepad mr-3"></i>Games
                        </a></li>
                </ul>
            </div>
        </aside>

        <section class="col-span-6 space-y-6">

            <?php if (isset($_SESSION['sucesso'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-lg">
                    <p><?= htmlspecialchars($_SESSION['sucesso']) ?></p>
                </div>
                <?php unset($_SESSION['sucesso']); ?>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-lg p-6 hover-lift">
                <div class="flex items-start space-x-4 mb-4">
                    <?php if (!empty($usuario['foto_perfil'])): ?>
                        <img src="<?= htmlspecialchars($usuario['foto_perfil']) ?>"
                            class="w-12 h-12 rounded-full object-cover border-2 border-red-200 flex-shrink-0"
                            alt="Foto de perfil">
                    <?php else: ?>
                        <div class="w-12 h-12 bg-gradient-to-br from-red-400 to-red-600 rounded-full flex items-center justify-center text-white font-bold flex-shrink-0">
                            <?= $iniciais ?>
                        </div>
                    <?php endif; ?>

                    <div class="flex-1">
                        <form method="POST" enctype="multipart/form-data" id="form-post" class="dropzone">
                            <div class="relative">
                                <textarea name="conteudo" id="post-textarea"
                                    class="w-full p-4 border-2 border-gray-200 rounded-xl text-sm resize-none focus:border-red-300 focus:outline-none transition-colors min-h-[120px]"
                                    rows="3"
                                    placeholder="No que você está pensando hoje? 🤔"></textarea>

                                <div id="emoji-picker-container" class="absolute right-2 bottom-2"></div>
                            </div>

                            <div id="drop-area" class="mt-3 border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hidden">
                                <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                <p class="text-gray-500">Arraste e solte imagens ou vídeos aqui</p>
                                <p class="text-xs text-gray-400 mt-1">Ou clique para selecionar</p>
                            </div>

                            <div id="media-previews" class="mt-3 space-y-2 hidden">

                                <div id="image-previews" class="grid grid-cols-3 gap-2 hidden"></div>

                                <div id="video-preview" class="hidden">
                                    <video controls class="w-full rounded-lg" id="video-player">
                                        <source id="video-source" type="video/mp4">
                                        Seu navegador não suporta o elemento de vídeo.
                                    </video>
                                    <button type="button" onclick="removerVideo()" class="mt-1 text-red-600 hover:text-red-800 text-sm flex items-center">
                                        <i class="fas fa-times mr-1"></i> Remover vídeo
                                    </button>
                                </div>

                                <div id="audio-preview" class="hidden p-3 bg-gray-100 rounded-lg">
                                    <audio controls class="w-full">
                                        <source id="audio-source" type="audio/mpeg">
                                    </audio>
                                    <button type="button" onclick="removerAudio()" class="mt-1 text-red-600 hover:text-red-800 text-sm flex items-center">
                                        <i class="fas fa-times mr-1"></i> Remover áudio
                                    </button>
                                </div>
                            </div>

                            <div class="flex justify-between items-center mt-4 pt-3 border-t border-gray-200">
                                <div class="flex space-x-2">

                                    <input type="file" id="image-input" name="imagens[]" accept="image/*" multiple class="hidden">
                                    <input type="file" id="video-input" name="video" accept="video/*" class="hidden">
                                    <input type="file" id="audio-input" name="audio" accept="audio/*" class="hidden">

                                    <button type="button" onclick="document.getElementById('image-input').click()"
                                        class="flex items-center text-gray-500 hover:text-red-600 transition-colors p-2 rounded-full hover:bg-gray-100">
                                        <i class="fas fa-camera mr-1"></i>
                                        <span class="text-xs">Foto</span>
                                    </button>

                                    <button type="button" onclick="document.getElementById('video-input').click()"
                                        class="flex items-center text-gray-500 hover:text-red-600 transition-colors p-2 rounded-full hover:bg-gray-100">
                                        <i class="fas fa-video mr-1"></i>
                                        <span class="text-xs">Vídeo</span>
                                    </button>

                                    <button type="button" onclick="document.getElementById('audio-input').click()"
                                        class="flex items-center text-gray-500 hover:text-red-600 transition-colors p-2 rounded-full hover:bg-gray-100">
                                        <i class="fas fa-music mr-1"></i>
                                        <span class="text-xs">Áudio</span>
                                    </button>

                                    <button type="button" id="emoji-picker-button"
                                        class="flex items-center text-gray-500 hover:text-red-600 transition-colors p-2 rounded-full hover:bg-gray-100">
                                        <i class="fas fa-smile mr-1"></i>
                                        <span class="text-xs">Emoji</span>
                                    </button>
                                </div>

                                <button type="submit"
                                    class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-2 rounded-lg hover:from-red-700 hover:to-red-800 transition-all shadow-lg disabled:opacity-50"
                                    id="submit-button" disabled>
                                    Publicar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <?php foreach ($posts as $post):
                    $stmt_autor = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");
                    $stmt_autor->execute([$post['usuario_id']]);
                    $autor = $stmt_autor->fetch(PDO::FETCH_ASSOC);
                ?>
                    <div class="bg-white rounded-xl shadow-lg p-6 hover-lift relative" data-post-id="<?= $post['id'] ?>">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <?php if (!empty($autor['foto_perfil'])): ?>
                                    <img src="<?= htmlspecialchars($autor['foto_perfil']) ?>"
                                        class="w-12 h-12 rounded-full object-cover border-2 border-gray-200"
                                        alt="Foto de perfil de <?= htmlspecialchars($post['autor']) ?>">
                                <?php else: ?>
                                    <?php
                                    $post_iniciais = getIniciais($post['autor']);
                                    $cores = ['blue', 'purple', 'green', 'yellow', 'pink', 'indigo'];
                                    $cor = $cores[array_rand($cores)];
                                    ?>
                                    <div class="w-12 h-12 bg-gradient-to-br from-<?= $cor ?>-400 to-<?= $cor ?>-600 rounded-full flex items-center justify-center text-white font-bold">
                                        <?= $post_iniciais ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($post['autor']) ?></p>
                                    <p class="text-xs text-gray-500"><?= formatarData($post['data_postagem']) ?></p>
                                </div>
                            </div>

                            <div class="relative">
                                <button onclick="toggleMenu(<?= $post['id'] ?>)" class="text-gray-400 hover:text-gray-600 p-2 rounded-full hover:bg-gray-100">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>

                                <div id="menu-<?= $post['id'] ?>" class="hidden absolute right-0 mt-1 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200 post-menu">
                                    <?php if ($post['usuario_id'] == $_SESSION['usuario_id'] || (isset($usuario['is_admin']) && $usuario['is_admin'])): ?>
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

                        <div class="post-content">
                            <p class="text-gray-700 mb-4 leading-relaxed">
                                <?= nl2br(htmlspecialchars($post['conteudo'])) ?>
                            </p>
                            <?php if (!empty($post['imagem'])): ?>

                            <?php endif; ?>
                        </div>

                        <?php if (!empty($post['imagem'])): ?>
                            <?php
                            $imagens = json_decode($post['imagem']);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($imagens)): ?>
                                <div class="mb-4 relative group" id="post-<?= $post['id'] ?>">

                                    <div class="relative overflow-hidden rounded-xl bg-gray-100 aspect-[4/3]">
                                        <?php foreach ($imagens as $index => $imagem): ?>
                                            <div class="absolute inset-0 transition-opacity duration-500 ease-in-out <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>"
                                                data-carousel-item="<?= $index ?>">
                                                <img src="<?= htmlspecialchars($imagem) ?>"
                                                    alt="Imagem do post"
                                                    class="w-full h-full object-cover">
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if (count($imagens) > 1): ?>
                                            <button class="absolute left-4 top-1/2 -translate-y-1/2 bg-black/30 text-white rounded-full w-10 h-10 flex items-center justify-center z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200 hover:bg-black/50"
                                                data-carousel-prev>
                                                <i class="fas fa-chevron-left text-lg"></i>
                                            </button>
                                            <button class="absolute right-4 top-1/2 -translate-y-1/2 bg-black/30 text-white rounded-full w-10 h-10 flex items-center justify-center z-10 opacity-0 group-hover:opacity-100 transition-opacity duration-200 hover:bg-black/50"
                                                data-carousel-next>
                                                <i class="fas fa-chevron-right text-lg"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (count($imagens) > 1): ?>
                                        <div class="flex justify-center mt-3 space-x-2">
                                            <?php foreach ($imagens as $index => $imagem): ?>
                                                <button class="block w-2 h-2 rounded-full transition-all duration-300 <?= $index === 0 ? 'bg-gray-600 w-4' : 'bg-gray-300' ?>"
                                                    data-carousel-indicator="<?= $index ?>"></button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="flex items-center justify-between text-sm text-gray-500 pt-4 border-t">

                            <form method="POST" class="flex items-center space-x-2">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <button type="submit" name="curtir" class="hover:text-red-600 transition-colors">
                                    <i class="fas fa-heart <?= $post['curtido'] ? 'text-red-600' : '' ?>"></i>
                                    <span><?= $post['curtidas'] ?> curtidas</span>
                                </button>
                            </form>

                            <button onclick="abrirModalComentarios(<?= $post['id'] ?>)" class="flex items-center space-x-2 hover:text-blue-600 transition-colors">
                                <i class="fas fa-comment"></i>
                                <span><?= $post['comentarios'] ?> comentários</span>
                            </button>

                            <button class="flex items-center space-x-2 hover:text-green-600 transition-colors">
                                <i class="fas fa-share"></i>
                                <span>Compartilhar</span>
                            </button>
                        </div>

                        <div id="comentarios-<?= $post['id'] ?>" class="mt-4 hidden transition-all duration-300 ease-in-out">

                            <?php if (count($post['comentarios_lista']) > 0): ?>
                                <div class="space-y-3 mb-4 max-h-60 overflow-y-auto pr-2">
                                    <?php foreach ($post['comentarios_lista'] as $comentario):
                                        $foto_perfil = $comentario['foto_perfil'] ?? null;
                                    ?>
                                        <div class="flex items-start gap-3 p-2 hover:bg-gray-50 rounded-lg">
                                            <a href="profile.php?id=<?= $comentario['usuario_id'] ?>" class="flex-shrink-0">
                                                <?php if (!empty($foto_perfil)): ?>
                                                    <img src="<?= htmlspecialchars($foto_perfil) ?>"
                                                        class="w-8 h-8 rounded-full object-cover border border-gray-200"
                                                        alt="Foto de <?= htmlspecialchars($comentario['autor']) ?>">
                                                <?php else: ?>
                                                    <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                                        <i class="fas fa-user text-gray-400 text-xs"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </a>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-baseline gap-2">
                                                    <a href="profile.php?id=<?= $comentario['usuario_id'] ?>" class="text-sm font-semibold hover:text-red-600 truncate">
                                                        <?= htmlspecialchars($comentario['autor']) ?>
                                                    </a>
                                                    <span class="text-xs text-gray-500 whitespace-nowrap">
                                                        <?= formatarData($comentario['data_comentario']) ?>
                                                    </span>
                                                </div>
                                                <p class="text-sm mt-1 break-words">
                                                    <?= htmlspecialchars($comentario['conteudo']) ?>
                                                </p>
                                                <?php if (!empty($comentario['imagem'])): ?>
                                                    <div class="mt-2">
                                                        <img src="<?= htmlspecialchars($comentario['imagem']) ?>"
                                                            alt="Imagem do comentário"
                                                            class="max-w-full h-auto max-h-40 rounded-lg border border-gray-200">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" enctype="multipart/form-data" class="bg-gray-50 p-2 rounded-lg">
                                <div class="flex items-start gap-2">
                                    <?php if (!empty($usuario['foto_perfil'])): ?>
                                        <img src="<?= htmlspecialchars($usuario['foto_perfil']) ?>"
                                            class="w-8 h-8 rounded-full object-cover border border-gray-200 flex-shrink-0"
                                            alt="Sua foto de perfil">
                                    <?php else: ?>
                                        <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-user text-gray-400 text-xs"></i>
                                        </div>
                                    <?php endif; ?>

                                    <div class="flex-1 space-y-2">
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">

                                        <textarea name="comentario" placeholder="Escreva um comentário..."
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-red-300 resize-none"
                                            rows="2"></textarea>

                                        <img id="preview-comentario-<?= $post['id'] ?>"
                                            class="max-w-full h-auto max-h-40 rounded-lg border border-gray-200 hidden"
                                            src="#" alt="Preview da imagem">

                                        <div class="flex justify-between items-center">
                                            <div class="flex gap-2">

                                                <input type="file" id="imagem-comentario-<?= $post['id'] ?>"
                                                    name="imagem_comentario" accept="image/*" class="hidden"
                                                    onchange="previewComentarioImage(this, <?= $post['id'] ?>)">
                                                <button type="button" onclick="document.getElementById('imagem-comentario-<?= $post['id'] ?>').click()"
                                                    class="text-gray-500 hover:text-red-600 transition-colors p-1">
                                                    <i class="fas fa-camera"></i>
                                                </button>
                                            </div>

                                            <button type="submit" name="comentar"
                                                class="bg-red-600 text-white px-3 py-1 rounded-full text-sm hover:bg-red-700 transition">
                                                <i class="fas fa-paper-plane"></i> Enviar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="col-span-3 space-y-6">

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

            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-red-600 font-bold mb-4 text-lg">
                    <i class="fas fa-users mr-2"></i>Pessoas que você pode conhecer
                </h2>
                <div class="space-y-4">
                    <?php foreach ($sugestoes_amigos as $amigo): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <a href="profile.php?id=<?= $amigo['id'] ?>">
                                    <?php if (!empty($amigo['foto_perfil'])): ?>
                                        <img src="<?= htmlspecialchars($amigo['foto_perfil']) ?>"
                                            class="w-10 h-10 rounded-full object-cover border-2 border-gray-200"
                                            alt="Foto de <?= htmlspecialchars($amigo['nome']) ?>">
                                    <?php else: ?>
                                        <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                                <div>
                                    <a href="profile.php?id=<?= $amigo['id'] ?>" class="font-semibold text-sm text-gray-800 hover:text-red-600 transition-colors">
                                        <?= htmlspecialchars($amigo['nome']) ?>
                                    </a>
                                    <p class="text-xs text-gray-500">Amigos em comum</p>
                                </div>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="amigo_id" value="<?= $amigo['id'] ?>">
                                <button type="submit" name="adicionar_amigo" class="bg-red-100 text-red-700 px-3 py-1 rounded-lg text-xs hover:bg-red-200 transition-colors">
                                    Adicionar
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="w-full mt-4 text-center text-xs text-red-600 hover:text-red-700 hover:underline">
                    Ver mais sugestões
                </button>
            </div>
        </aside>
    </main>

    <div id="modal-comentarios" class="modal-comentarios">
        <div class="close-modal" onclick="fecharModalComentarios()">&times;</div>
        <div class="modal-content">
            <!-- Lado esquerdo - Mídia -->
            <div class="modal-media" id="modal-media-content">
                <!-- Aqui serão inseridas as imagens/vídeos -->
            </div>

            <!-- Lado direito - Conteúdo e comentários -->
            <div class="modal-sidebar">
                <!-- Cabeçalho com autor -->
                <div class="modal-header" id="modal-header-content">
                    <!-- Aqui será inserido o cabeçalho com foto e nome do autor -->
                </div>

                <!-- Conteúdo do post -->
                <div class="modal-post-content" id="modal-text-content">
                    <!-- Aqui será inserido o texto do post -->
                </div>

                <!-- Ações (curtir, comentar, etc) -->
                <div class="modal-actions">
                    <div class="flex space-x-4">
                        <button id="modal-curtir-btn" class="text-gray-700 hover:text-red-500 text-xl">
                            <i class="far fa-heart"></i>
                        </button>
                        <button class="text-gray-700 hover:text-blue-500 text-xl">
                            <i class="far fa-comment"></i>
                        </button>
                        <button class="text-gray-700 hover:text-green-500 text-xl">
                            <i class="far fa-share-square"></i>
                        </button>
                    </div>
                    <div class="text-sm font-semibold mt-2" id="modal-likes-count"></div>
                </div>

                <!-- Lista de comentários -->
                <div class="modal-comments-list" id="modal-comments-list">
                    <!-- Comentários serão carregados aqui -->
                </div>

                <!-- Formulário de comentário -->
                <div class="modal-comment-form">
                    <form method="POST" enctype="multipart/form-data" class="flex items-start gap-2">
                        <input type="hidden" name="post_id" id="modal-post-id">

                        <?php if (!empty($usuario['foto_perfil'])): ?>
                            <img src="<?= htmlspecialchars($usuario['foto_perfil']) ?>"
                                class="w-10 h-10 rounded-full object-cover border border-gray-200 flex-shrink-0"
                                alt="Sua foto">
                        <?php else: ?>
                            <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                        <?php endif; ?>

                        <div class="flex-1 flex flex-col gap-2">
                            <textarea name="comentario" placeholder="Adicione um comentário..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-red-300 resize-none"
                                rows="2"></textarea>

                            <div class="flex justify-between items-center">
                                <button type="button" onclick="document.getElementById('modal-comentario-imagem').click()"
                                    class="text-gray-500 hover:text-red-600 p-1">
                                    <i class="fas fa-camera"></i>
                                </button>
                                <input type="file" id="modal-comentario-imagem" name="imagem_comentario"
                                    accept="image/*" class="hidden">

                                <button type="submit" name="comentar"
                                    class="bg-red-600 text-white px-4 py-1 rounded-lg text-sm hover:bg-red-700 transition">
                                    Publicar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script type="module">
        import {
            Picker
        } from 'https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js';

        const picker = new Picker();
        picker.classList.add('absolute', 'bottom-10', 'right-0', 'z-10', 'shadow-lg', 'hidden');
        document.getElementById('emoji-picker-container').appendChild(picker);

        picker.addEventListener('emoji-click', event => {
            const textarea = document.getElementById('post-textarea');
            const emoji = event.detail.unicode;
            const startPos = textarea.selectionStart;
            const endPos = textarea.selectionEnd;

            textarea.value = textarea.value.substring(0, startPos) +
                emoji +
                textarea.value.substring(endPos, textarea.value.length);

            textarea.selectionStart = textarea.selectionEnd = startPos + emoji.length;

            textarea.focus();
            picker.classList.add('hidden');

            textarea.dispatchEvent(new Event('input'));
        });

        window.emojiPicker = picker;
    </script>
    <script src="/js/index.js"></script>
</body>

</html>

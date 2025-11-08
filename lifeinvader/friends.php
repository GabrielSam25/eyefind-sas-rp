<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'];

// Adicionar/remover amigo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_amigo'])) {
    $amigo_id = (int)$_POST['amigo_id'];
    $acao = $_POST['acao_amigo'];

    try {
        if ($acao === 'adicionar') {
            // Verifica se a amizade já existe
            $verificaStmt = $pdo->prepare("SELECT COUNT(*) FROM amigos WHERE 
                (usuario_id = ? AND amigo_id = ?) OR (usuario_id = ? AND amigo_id = ?)");
            $verificaStmt->execute([$usuario_id, $amigo_id, $amigo_id, $usuario_id]);
            $existe = $verificaStmt->fetchColumn();

            if ($existe == 0) {
                // Insere com menor ID primeiro para evitar duplicações invertidas
                $id1 = min($usuario_id, $amigo_id);
                $id2 = max($usuario_id, $amigo_id);
                $stmt = $pdo->prepare("INSERT INTO amigos (usuario_id, amigo_id) VALUES (?, ?)");
                $stmt->execute([$id1, $id2]);
                $_SESSION['sucesso'] = "Amigo adicionado com sucesso!";
            } else {
                $_SESSION['erro'] = "Vocês já são amigos!";
            }
        } elseif ($acao === 'remover') {
            $stmt = $pdo->prepare("DELETE FROM amigos WHERE (usuario_id = ? AND amigo_id = ?) OR (usuario_id = ? AND amigo_id = ?)");
            $stmt->execute([$usuario_id, $amigo_id, $amigo_id, $usuario_id]);
            $_SESSION['sucesso'] = "Amigo removido com sucesso!";
        }
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro: " . $e->getMessage();
    }

    header("Location: friends.php");
    exit();
}


// Buscar amigos do usuário
$stmt = $pdo->prepare("
    SELECT u.id, u.nome, u.email, u.bio, u.data_registro
    FROM usuarios u
    JOIN amigos a ON (u.id = a.amigo_id AND a.usuario_id = ?) OR (u.id = a.usuario_id AND a.amigo_id = ?)
    WHERE u.id != ?
    GROUP BY u.id
");
$stmt->execute([$usuario_id, $usuario_id, $usuario_id]);
$amigos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT u.id, u.nome, u.email, COUNT(m.id) as amigos_em_comum
    FROM usuarios u
    LEFT JOIN amigos a1 ON (a1.usuario_id = ? AND a1.amigo_id = u.id) OR (a1.amigo_id = ? AND a1.usuario_id = u.id)
    LEFT JOIN amigos m ON 
        (m.usuario_id = u.id OR m.amigo_id = u.id)
        AND (
            m.usuario_id IN (
                SELECT amigo_id FROM amigos WHERE usuario_id = ?
                UNION
                SELECT usuario_id FROM amigos WHERE amigo_id = ?
            )
            OR
            m.amigo_id IN (
                SELECT amigo_id FROM amigos WHERE usuario_id = ?
                UNION
                SELECT usuario_id FROM amigos WHERE amigo_id = ?
            )
        )
    WHERE u.id != ? AND a1.usuario_id IS NULL
    GROUP BY u.id
    ORDER BY amigos_em_comum DESC
    LIMIT 5
");

$stmt->execute([$usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id]);
$sugestoes_amigos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar estatísticas do usuário
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM posts WHERE usuario_id = ?) as total_posts,
        (SELECT COUNT(*) FROM amigos WHERE (usuario_id = ? OR amigo_id = ?) AND 
         (usuario_id = ? OR amigo_id = ?)) as total_amigos,
        (SELECT COUNT(*) FROM curtidas WHERE usuario_id = ?) as total_likes
");
$stmt->execute([$usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id]);
$estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);

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
$publicidade = $pdo->query("SELECT * FROM publicidade WHERE ativo = 1 ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amigos - Lifeinvader</title>
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

        .sponsored-gradient {
            background: linear-gradient(45deg, #fef3c7, #fed7aa);
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
                    <span class="text-sm"><?= htmlspecialchars($usuario_nome) ?></span>
                </div>
                <a href="logout.php" class="text-sm hover:underline">sair</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 py-4">
        <div class="grid grid-cols-12 gap-4">
            <!-- Sidebar Esquerda - Perfil (3 colunas) -->
            <div class="col-span-3">
                <div class="bg-white rounded-lg shadow-sm p-4 mb-4 hover-lift">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-16 h-16 bg-gradient-to-br from-red-400 to-red-600 rounded-full flex items-center justify-center text-white text-xl font-bold">
                            <?= $iniciais ?>
                        </div>
                        <div>
                            <h2 class="font-bold text-gray-800"><?= htmlspecialchars($usuario_nome) ?></h2>
                            <p class="text-xs text-gray-500">@<?= strtolower(str_replace(' ', '', htmlspecialchars($usuario_nome))) ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center text-sm mb-4">
                        <div>
                            <p class="font-bold text-red-600"><?= $estatisticas['total_posts'] ?></p>
                            <p class="text-gray-500">Posts</p>
                        </div>
                        <div>
                            <p class="font-bold text-red-600"><?= $estatisticas['total_amigos'] ?></p>
                            <p class="text-gray-500">Amigos</p>
                        </div>
                        <div>
                            <p class="font-bold text-red-600"><?= $estatisticas['total_likes'] ?></p>
                            <p class="text-gray-500">Likes</p>
                        </div>
                    </div>

                    <a href="profile.php" class="block w-full bg-red-100 text-red-700 text-center py-2 rounded-lg text-sm font-medium hover:bg-red-200 transition">
                        Ver meu perfil
                    </a>
                </div>

                <!-- Menu de navegação -->
                <div class="bg-white rounded-lg shadow-sm p-4 hover-lift">
                    <ul class="space-y-2">
                        <li>
                            <a href="index.php" class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:text-red-600 hover:bg-red-50 rounded transition">
                                <i class="fas fa-home w-5 text-center"></i>
                                <span>Início</span>
                            </a>
                        </li>
                        <li>
                            <a href="friends.php" class="flex items-center gap-2 px-3 py-2 bg-red-50 text-red-600 rounded transition">
                                <i class="fas fa-users w-5 text-center"></i>
                                <span>Amigos</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:text-red-600 hover:bg-red-50 rounded transition">
                                <i class="fas fa-comments w-5 text-center"></i>
                                <span>Mensagens</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:text-red-600 hover:bg-red-50 rounded transition">
                                <i class="fas fa-camera w-5 text-center"></i>
                                <span>Fotos</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Conteúdo Central - Lista de Amigos (6 colunas) -->
            <div class="col-span-6">
                <!-- Mensagens de feedback -->
                <?php if (isset($_SESSION['sucesso'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-lg">
                        <p><?= htmlspecialchars($_SESSION['sucesso']) ?></p>
                    </div>
                    <?php unset($_SESSION['sucesso']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['erro'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-lg">
                        <p><?= htmlspecialchars($_SESSION['erro']) ?></p>
                    </div>
                    <?php unset($_SESSION['erro']); ?>
                <?php endif; ?>

                <!-- Título e busca -->
                <div class="bg-white rounded-lg shadow-sm p-4 mb-4 hover-lift">
                    <div class="flex justify-between items-center mb-4">
                        <h1 class="text-xl font-bold text-red-600">
                            <i class="fas fa-users mr-2"></i>Meus Amigos
                        </h1>
                        <div class="relative">
                            <input type="text" placeholder="Buscar amigos..." class="pl-8 pr-4 py-2 border border-gray-300 rounded-full text-sm focus:outline-none focus:border-red-300 w-64">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>

                    <div class="flex border-b">
                        <button class="px-4 py-2 font-medium text-red-600 border-b-2 border-red-600">Todos (<?= count($amigos) ?>)</button>
                        <button class="px-4 py-2 font-medium text-gray-500 hover:text-red-600">Online</button>
                        <button class="px-4 py-2 font-medium text-gray-500 hover:text-red-600">Sugestões</button>
                    </div>
                </div>

                <!-- Lista de amigos -->
                <div class="space-y-4">
                    <?php if (empty($amigos)): ?>
                        <div class="bg-white rounded-lg shadow-sm p-6 text-center hover-lift">
                            <div class="text-gray-400 mb-4">
                                <i class="fas fa-user-friends text-4xl"></i>
                            </div>
                            <p class="text-gray-600 mb-4">Você ainda não tem amigos adicionados.</p>
                            <p class="text-sm text-gray-500">Encontre amigos nas sugestões ao lado ou busque por pessoas que você conhece.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-2 gap-4">
                            <?php foreach ($amigos as $amigo):
                                $amigo_iniciais = getIniciais($amigo['nome']);
                                $cores = ['blue', 'purple', 'green', 'yellow', 'pink', 'indigo'];
                                $cor = $cores[array_rand($cores)];
                            ?>
                                <div class="bg-white rounded-lg shadow-sm p-4 hover-lift">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-3">
                                            <a href="profile.php?id=<?= $amigo['id'] ?>" class="w-12 h-12 bg-gradient-to-br from-<?= $cor ?>-400 to-<?= $cor ?>-600 rounded-full flex items-center justify-center text-white font-bold">
                                                <?= $amigo_iniciais ?>
                                            </a>
                                            <div>
                                                <a href="profile.php?id=<?= $amigo['id'] ?>" class="font-medium text-gray-800 hover:text-red-600"><?= htmlspecialchars($amigo['nome']) ?></a>
                                                <p class="text-xs text-gray-500">Amigos desde <?= date('m/Y', strtotime($amigo['data_registro'])) ?></p>
                                            </div>
                                        </div>
                                        <div class="relative">
                                            <button onclick="toggleMenu(<?= $amigo['id'] ?>)" class="text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-100">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>

                                            <!-- Menu dropdown -->
                                            <div id="menu-<?= $amigo['id'] ?>" class="hidden absolute right-0 mt-1 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200 post-menu">
                                                <form method="POST">
                                                    <input type="hidden" name="amigo_id" value="<?= $amigo['id'] ?>">
                                                    <input type="hidden" name="acao_amigo" value="remover">
                                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-md flex items-center">
                                                        <i class="fas fa-user-minus mr-2"></i> Remover amigo
                                                    </button>
                                                </form>
                                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md flex items-center">
                                                    <i class="fas fa-comment mr-2"></i> Enviar mensagem
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-between text-sm">
                                        <a href="profile.php?id=<?= $amigo['id'] ?>" class="text-blue-600 hover:underline text-xs">
                                            Ver perfil
                                        </a>
                                        <span class="text-gray-500 text-xs">
                                            <i class="fas fa-circle text-green-500"></i> Online
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar direita - Sugestões de amigos (3 colunas) -->
            <div class="col-span-3">
                <!-- Área de Patrocinados -->
                <?php if ($publicidade): ?>
                    <div class="sponsored-gradient rounded-xl shadow-lg p-6 border-2 border-yellow-200 mb-4">
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

                <!-- Sugestões de amigos -->
                <div class="bg-white rounded-lg shadow-sm p-4 hover-lift">
                    <h2 class="text-red-600 font-bold mb-4 text-lg">
                        <i class="fas fa-user-plus mr-2"></i>Sugestões de amigos
                    </h2>

                    <?php if (empty($sugestoes_amigos)): ?>
                        <p class="text-gray-500 text-sm">Nenhuma sugestão no momento.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($sugestoes_amigos as $sugestao):
                                $sugestao_iniciais = getIniciais($sugestao['nome']);
                                $cores = ['blue', 'purple', 'green', 'yellow', 'pink', 'indigo'];
                                $cor = $cores[array_rand($cores)];
                            ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <a href="profile.php?id=<?= $sugestao['id'] ?>" class="w-10 h-10 bg-gradient-to-br from-<?= $cor ?>-400 to-<?= $cor ?>-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            <?= $sugestao_iniciais ?>
                                        </a>
                                        <div>
                                            <a href="profile.php?id=<?= $sugestao['id'] ?>" class="font-medium text-sm text-gray-800 hover:text-red-600"><?= htmlspecialchars($sugestao['nome']) ?></a>
                                            <p class="text-xs text-gray-500"><?= $sugestao['amigos_em_comum'] ?> amigo(s) em comum</p>
                                        </div>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="amigo_id" value="<?= $sugestao['id'] ?>">
                                        <input type="hidden" name="acao_amigo" value="adicionar">
                                        <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded-full text-xs hover:bg-red-700 transition">
                                            Adicionar
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <a href="#" class="block mt-4 text-center text-sm text-red-600 hover:text-red-700 hover:underline">
                            Ver mais sugestões
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleMenu(userId) {
            const menu = document.getElementById(`menu-${userId}`);
            menu.classList.toggle('hidden');

            // Fechar outros menus
            document.querySelectorAll('.post-menu').forEach(otherMenu => {
                if (otherMenu.id !== `menu-${userId}`) {
                    otherMenu.classList.add('hidden');
                }
            });
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

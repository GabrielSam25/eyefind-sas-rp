<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$usuario = getUsuarioAtual($pdo);

// Sites onde o usuário é colaborador
$stmt = $pdo->prepare("
    SELECT w.*, sc.nivel 
    FROM websites w
    JOIN site_colaboradores sc ON w.id = sc.website_id
    WHERE sc.usuario_id = ?
    ORDER BY w.nome
");
$stmt->execute([$usuario['id']]);
$sites_colaborador = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sites onde o usuário é dono
$stmt = $pdo->prepare("SELECT * FROM websites WHERE usuario_id = ?");
$stmt->execute([$usuario['id']]);
$sites_dono = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Eyefind.info</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h1 class="text-2xl font-bold text-blue-600 mb-4">Meu Perfil</h1>
            
            <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                <div class="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center text-white text-3xl font-bold">
                    <?php echo strtoupper(substr($usuario['nome'], 0, 1)); ?>
                </div>
                <div>
                    <h2 class="text-xl font-bold"><?php echo htmlspecialchars($usuario['nome']); ?></h2>
                    <p class="text-gray-600"><?php echo htmlspecialchars($usuario['email']); ?></p>
                </div>
            </div>
        </div>

        <!-- Sites que sou dono -->
        <?php if (!empty($sites_dono)): ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-bold mb-4">Meus Sites (Dono)</h2>
            <div class="space-y-3">
                <?php foreach ($sites_dono as $site): ?>
                <div class="flex items-center justify-between p-3 border rounded-lg">
                    <div>
                        <h3 class="font-bold"><?php echo htmlspecialchars($site['nome']); ?></h3>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($site['descricao']); ?></p>
                    </div>
                    <div class="flex gap-2">
                        <a href="gerenciar_colaboradores.php?website_id=<?php echo $site['id']; ?>" 
                           class="bg-purple-100 text-purple-700 px-3 py-1 rounded text-sm hover:bg-purple-200">
                            <i class="fas fa-users mr-1"></i>Colaboradores
                        </a>
                        <a href="edit_blog.php?id=<?php echo $site['id']; ?>" 
                           class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-sm hover:bg-blue-200">
                            <i class="fas fa-edit mr-1"></i>Editar
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sites onde sou colaborador -->
        <?php if (!empty($sites_colaborador)): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4">Sites que Colaboro</h2>
            <div class="space-y-3">
                <?php foreach ($sites_colaborador as $site): ?>
                <div class="flex items-center justify-between p-3 border rounded-lg">
                    <div>
                        <h3 class="font-bold"><?php echo htmlspecialchars($site['nome']); ?></h3>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($site['descricao']); ?></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="bg-<?php 
                            echo $site['nivel'] == 'admin' ? 'red' : 
                                ($site['nivel'] == 'editor' ? 'purple' : 
                                ($site['nivel'] == 'revisor' ? 'orange' : 'green')); 
                        ?>-100 text-<?php 
                            echo $site['nivel'] == 'admin' ? 'red' : 
                                ($site['nivel'] == 'editor' ? 'purple' : 
                                ($site['nivel'] == 'revisor' ? 'orange' : 'green')); 
                        ?>-700 px-3 py-1 rounded-full text-xs">
                            <?php echo ucfirst($site['nivel']); ?>
                        </span>
                        <a href="edit_blog.php?id=<?php echo $site['id']; ?>" 
                           class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-sm hover:bg-blue-200">
                            <i class="fas fa-edit mr-1"></i>Acessar
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
require_once 'config.php';

$website_id = intval($_GET['website_id'] ?? 0);
$anuncio_id = intval($_GET['anuncio_id'] ?? 0);

if (!$website_id || !$anuncio_id) {
    header('Location: index.php');
    exit;
}

// Obter anúncio
$stmt = $pdo->prepare("SELECT a.*, w.nome as site_nome, w.tipo FROM classificados_anuncios a JOIN websites w ON a.website_id = w.id WHERE a.id = ? AND a.website_id = ? AND a.status = 'ativo'");
$stmt->execute([$anuncio_id, $website_id]);
$anuncio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$anuncio) {
    header('Location: index.php');
    exit;
}

// Incrementar views
$stmt = $pdo->prepare("UPDATE classificados_anuncios SET views = views + 1 WHERE id = ?");
$stmt->execute([$anuncio_id]);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($anuncio['titulo']); ?> - <?php echo htmlspecialchars($anuncio['site_nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <a href="website.php?id=<?php echo $website_id; ?>" class="inline-block mb-4 text-purple-600 hover:underline">
            <i class="fas fa-arrow-left mr-2"></i>Voltar para os classificados
        </a>
        
        <div class="bg-white p-8 rounded-lg shadow-md">
            <div class="grid md:grid-cols-2 gap-8">
                <?php if ($anuncio['imagem']): ?>
                    <div>
                        <img src="<?php echo htmlspecialchars($anuncio['imagem']); ?>" alt="<?php echo htmlspecialchars($anuncio['titulo']); ?>" class="w-full rounded-lg shadow-md">
                    </div>
                <?php endif; ?>
                
                <div>
                    <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($anuncio['titulo']); ?></h1>
                    
                    <?php if ($anuncio['preco']): ?>
                        <p class="text-4xl font-bold text-green-600 mb-4">
                            R$ <?php echo number_format($anuncio['preco'], 2, ',', '.'); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="space-y-3 mb-6">
                        <?php if ($anuncio['categoria']): ?>
                            <p><span class="font-bold">Categoria:</span> <?php echo htmlspecialchars($anuncio['categoria']); ?></p>
                        <?php endif; ?>
                        <?php if ($anuncio['contato']): ?>
                            <p><span class="font-bold">Contato:</span> <?php echo htmlspecialchars($anuncio['contato']); ?></p>
                        <?php endif; ?>
                        <?php if ($anuncio['telefone']): ?>
                            <p><span class="font-bold">Telefone:</span> <?php echo htmlspecialchars($anuncio['telefone']); ?></p>
                        <?php endif; ?>
                        <?php if ($anuncio['email']): ?>
                            <p><span class="font-bold">Email:</span> <a href="mailto:<?php echo htmlspecialchars($anuncio['email']); ?>" class="text-purple-600 hover:underline"><?php echo htmlspecialchars($anuncio['email']); ?></a></p>
                        <?php endif; ?>
                        <?php if ($anuncio['data_validade']): ?>
                            <p><span class="font-bold">Validade:</span> <?php echo date('d/m/Y', strtotime($anuncio['data_validade'])); ?></p>
                        <?php endif; ?>
                        <p><span class="font-bold">Anunciado em:</span> <?php echo date('d/m/Y', strtotime($anuncio['data_criacao'])); ?></p>
                        <p><span class="font-bold">Visualizações:</span> <?php echo $anuncio['views']; ?></p>
                    </div>
                    
                    <div class="border-t pt-6">
                        <h2 class="text-xl font-bold mb-4">Descrição</h2>
                        <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($anuncio['descricao'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
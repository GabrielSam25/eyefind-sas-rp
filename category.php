<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$categoria_id = $_GET['id'];

// Obter dados dinâmicos do banco
$categorias = getCategorias($pdo);
$categoriaAtual = getCategoriaById($pdo, $categoria_id);
$websitesDaCategoria = getWebsitesByCategoria($pdo, $categoria_id);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eyefind.info - <?php echo $categoriaAtual['nome']; ?></title>
    <link rel="icon" type="image/png" sizes="192x192" href="icon/android-chrome-192x192.png">

    <link rel="icon" type="image/png" sizes="512x512" href="icon/android-chrome-512x512.png">

    <link rel="apple-touch-icon" sizes="180x180" href="icon/apple-touch-icon.png">

    <link rel="icon" type="image/png" sizes="16x16" href="icon/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icon/favicon-32x32.png">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'eyefind-blue': '#067191',
                        'eyefind-light': '#E8F4F8',
                        'eyefind-dark': '#02404F'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap');

        body {
            font-family: 'Roboto Condensed', sans-serif;
        }
    </style>
</head>

<body class="bg-eyefind-light">
    <!-- Header Section -->
    <section class="bg-[#488BC2] shadow-md">
        <div class="max-w-6xl mx-auto p-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex flex-col md:flex-row items-center gap-6">
                    <div class="w-64">
                        <img src="imagens/eyefind-logo.png" alt="Eyefind.info Logo" class="w-full">
                    </div>
                    <div class="w-full md:w-96">
                        <form action="search.php" method="GET">
                            <div class="relative">
                                <input type="text" name="q"
                                    class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue"
                                    placeholder="Procurar no Eyefind">
                                <button type="submit" class="absolute right-3 top-3 text-eyefind-blue">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="flex items-center gap-6 mt-4 md:mt-0">

                    <!-- Voltar -->
                    <div class="relative group">
                        <a href="index.php" class="p-3 hover:scale-110 transition duration-200">
                        </a>

                        <div class="absolute -bottom-10 left-1/2 -translate-x-1/2 
                                    bg-black text-white text-xs px-3 py-1 rounded
                                    opacity-0 group-hover:opacity-100 transition
                                    pointer-events-none whitespace-nowrap">
                            Voltar
                        </div>
                    </div>

                <div class="flex items-center gap-6 mt-4 md:mt-0">

                    <!-- Voltar (mantém botão original) -->
                    <a href="index.php" 
                    class="bg-gray-600 text-white px-4 py-2 rounded font-bold hover:bg-gray-700 transition">
                        Voltar
                    </a>

                <?php if (!isLogado()): ?>

                    <!-- Login (ícone) -->
                    <div class="relative group">
                        <a href="login.php" class="p-3 hover:scale-110 transition duration-200">
                            <img src="icon/login.png" class="w-8 h-8" alt="Login">
                        </a>

                        <div class="absolute -bottom-10 left-1/2 -translate-x-1/2 
                                    bg-black text-white text-xs px-3 py-1 rounded
                                    opacity-0 group-hover:opacity-100 transition
                                    pointer-events-none whitespace-nowrap">
                            Login
                        </div>
                    </div>

                <?php else: ?>

                    <!-- Criar Blog (ícone) -->
                    <div class="relative group">
                        <a href="new_blog.php" class="p-3 hover:scale-110 transition duration-200">
                            <img src="icon/blog.png" class="w-8 h-8" alt="Criar Blog">
                        </a>

                        <div class="absolute -bottom-10 left-1/2 -translate-x-1/2 
                                    bg-black text-white text-xs px-3 py-1 rounded
                                    opacity-0 group-hover:opacity-100 transition
                                    pointer-events-none whitespace-nowrap">
                            Criar Blog
                        </div>
                    </div>

                    <!-- Gerenciar Blogs (ícone) -->
                    <div class="relative group">
                        <a href="manage_blogs.php" class="p-3 hover:scale-110 transition duration-200">
                            <img src="icon/gerenciarblog.png" class="w-8 h-8" alt="Gerenciar Blogs">
                        </a>

                        <div class="absolute -bottom-10 left-1/2 -translate-x-1/2 
                                    bg-black text-white text-xs px-3 py-1 rounded
                                    opacity-0 group-hover:opacity-100 transition
                                    pointer-events-none whitespace-nowrap">
                            Gerenciar Blogs
                        </div>
                    </div>

                    <!-- Logout (mantém botão original) -->
                    <a href="logout.php" 
                    class="bg-red-600 text-white px-4 py-2 rounded font-bold hover:bg-red-700 transition">
                        Logout
                    </a>

                <?php endif; ?>

                </div>
            </div>
        </div>
    </section>

    <div class="w-full h-2 bg-yellow-400"></div>

    <div class="w-full bg-white shadow-md">
        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-2 md:grid-cols-5 divide-x">
                <?php foreach ($categorias as $categoria): ?>
                    <a href="category.php?id=<?php echo $categoria['id']; ?>"
                        class="flex items-center text-left p-3 hover:bg-eyefind-light cursor-pointer transition group space-x-3">
                        <i class="<?php echo $categoria['icone']; ?> text-2xl text-eyefind-blue group-hover:scale-110 transition"></i>
                        <p class="font-bold text-eyefind-dark"><?php echo $categoria['nome']; ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto">
        <div class="p-3 mt-1">
            <h3 class="text-lg font-bold text-eyefind-blue mb-4">Resultados para: <?php echo $categoriaAtual['nome']; ?></h3>
            <div class="grid grid-cols-1 gap-4">
                <?php foreach ($websitesDaCategoria as $website): ?>
                    <div class="bg-eyefind-light p-4 rounded-lg shadow-sm">
                        <div class="grid grid-cols-1 md:grid-cols-[auto_1fr] gap-4">
                            <img src="<?php echo $website['imagem']; ?>" alt="<?php echo $website['nome']; ?>" class="w-full md:w-64 h-40 object-cover rounded-lg">
                            <div>
                                <h4 class="text-xl font-bold text-eyefind-blue"><?php echo $website['nome']; ?></h4>
                                <p class="text-eyefind-dark"><?php echo $website['descricao']; ?></p>
                                <a href="website.php?id=<?php echo $website['id']; ?>" class="mt-2 inline-block text-eyefind-blue hover:text-eyefind-dark font-bold transition">
                                    Visitar Website →
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>

</html>

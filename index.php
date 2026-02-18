<?php
require_once "config.php";

$categorias = getCategorias($pdo);
$websiteDoMinuto = getWebsiteDoMinuto($pdo);
$noticiaDestaque = getNoticiaDestaque($pdo);
$bleets = getBleets($pdo);
$publicidade = getPublicidadeAtiva($pdo);
$websitesSugeridos = getWebsites($pdo, null, 4);

$stmt = $pdo->prepare("
    SELECT 
        n.*,
        w.nome as site_nome,
        w.id as site_id,
        u.nome as autor_nome
    FROM noticias_artigos n
    JOIN websites w ON n.website_id = w.id
    LEFT JOIN usuarios u ON n.autor_id = u.id
    WHERE n.status = 'publicado' 
    AND w.status = 'approved'
    ORDER BY n.data_publicacao DESC 
    LIMIT 1
");
$stmt->execute();
$ultimaNoticia = $stmt->fetch(PDO::FETCH_ASSOC);

function getWeatherData()
{
    $apiKey = '4137179bbfe371cdf0cf5abda9888dda';
    $city = 'Los+Angeles';
    $url = "http://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$apiKey}&units=metric";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    return json_decode($response, true);
}

$weatherData = getWeatherData();

date_default_timezone_set('America/Sao_Paulo');

$temp = isset($weatherData['main']['temp']) ? round($weatherData['main']['temp']) : 25;
$weatherId = isset($weatherData['weather'][0]['id']) ? $weatherData['weather'][0]['id'] : 800;
$weatherDescription = isset($weatherData['weather'][0]['description']) ? ucfirst($weatherData['weather'][0]['description']) : 'Clear sky';

$iconClass = 'fas fa-sun';
$iconColor = '#FFD700';

if ($weatherId !== null) {
    if ($weatherId >= 200 && $weatherId < 300) {
        $iconClass = 'fas fa-bolt';
        $iconColor = '#FFD700'; // Amarelo mais vibrante para raios
    } elseif ($weatherId >= 300 && $weatherId < 500) {
        $iconClass = 'fas fa-cloud-rain';
        $iconColor = '#00BFFF'; // Azul claro mais vibrante
    } elseif ($weatherId >= 500 && $weatherId < 600) {
        $iconClass = 'fas fa-cloud-showers-heavy';
        $iconColor = '#4169E1'; // Azul royal
    } elseif ($weatherId >= 600 && $weatherId < 700) {
        $iconClass = 'fas fa-snowflake';
        $iconColor = '#E0FFFF'; // Azul gelo claro
    } elseif ($weatherId >= 700 && $weatherId < 800) {
        $iconClass = 'fas fa-smog';
        $iconColor = '#B8B8B8'; // Cinza mais suave
    } elseif ($weatherId == 800) {
        $iconClass = 'fas fa-sun';
        $iconColor = '#FFA500'; // Laranja para o sol
    } elseif ($weatherId > 800) {
        $iconClass = 'fas fa-cloud';
        $iconColor = '#E8E8E8'; // Cinza claro mais suave
    }
}

$hora = (int)date('H');
$isDayTime = ($hora >= 6 && $hora < 18);

if ($isDayTime) {
    if ($weatherId >= 200 && $weatherId < 300) { // Trovoada durante o dia
        $bgGradient = 'from-[#1a2a6c] via-[#b21f1f] to-[#fdbb2d]';
        $bgOverlay = 'bg-[linear-gradient(45deg,rgba(255,255,255,0.1) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.1) 50%,rgba(255,255,255,0.1) 75%,transparent 75%,transparent)]';
    } elseif ($weatherId >= 300 && $weatherId < 600) { // Chuva durante o dia
        $bgGradient = 'from-[#00416A] via-[#799F0C] to-[#FFE000]';
        $bgOverlay = 'bg-[repeating-linear-gradient(45deg,rgba(255,255,255,0.05) 0px,rgba(255,255,255,0.05) 2px,transparent 2px,transparent 4px)]';
    } elseif ($weatherId >= 600 && $weatherId < 700) { // Neve durante o dia
        $bgGradient = 'from-[#E3FDF5] via-[#FFE6FA] to-[#E3FDF5]';
        $bgOverlay = 'bg-[radial-gradient(circle,rgba(255,255,255,0.2) 1px,transparent 1px)] bg-size-[20px_20px]';
    } elseif ($weatherId >= 700 && $weatherId < 800) { // Neblina durante o dia
        $bgGradient = 'from-[#7F7FD5] via-[#86A8E7] to-[#91EAE4]';
        $bgOverlay = 'bg-[linear-gradient(90deg,rgba(255,255,255,0.07) 0px,transparent 1px)] bg-size-[10px_10px]';
    } elseif ($weatherId == 800) { // Céu limpo durante o dia
        $bgGradient = 'from-[#2193b0] via-[#6dd5ed] to-[#2193b0]';
        $bgOverlay = 'bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.1) 0%,transparent 70%)]';
    } else { // Nublado durante o dia
        $bgGradient = 'from-[#4B79A1] via-[#283E51] to-[#4B79A1]';
        $bgOverlay = 'bg-[linear-gradient(135deg,rgba(255,255,255,0.05) 25%,transparent 25%)]';
    }
} else {
    if ($weatherId >= 200 && $weatherId < 300) { // Trovoada à noite
        $bgGradient = 'from-[#0f0c29] via-[#302b63] to-[#24243e]';
        $bgOverlay = 'bg-[linear-gradient(45deg,rgba(255,255,255,0.03) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.03) 50%)]';
    } elseif ($weatherId >= 300 && $weatherId < 600) { // Chuva à noite
        $bgGradient = 'from-[#000046] via-[#1CB5E0] to-[#000046]';
        $bgOverlay = 'bg-[repeating-linear-gradient(45deg,rgba(0,0,0,0.1) 0px,rgba(0,0,0,0.1) 2px,transparent 2px,transparent 4px)]';
    } elseif ($weatherId >= 600 && $weatherId < 700) { // Neve à noite
        $bgGradient = 'from-[#141E30] via-[#243B55] to-[#141E30]';
        $bgOverlay = 'bg-[radial-gradient(circle,rgba(255,255,255,0.1) 1px,transparent 1px)] bg-size-[15px_15px]';
    } elseif ($weatherId >= 700 && $weatherId < 800) { // Neblina à noite
        $bgGradient = 'from-[#0F2027] via-[#203A43] to-[#2C5364]';
        $bgOverlay = 'bg-[linear-gradient(90deg,rgba(255,255,255,0.03) 0px,transparent 1px)] bg-size-[8px_8px]';
    } elseif ($weatherId == 800) { // Céu limpo à noite
        $bgGradient = 'from-[#000428] via-[#004e92] to-[#000428]';
        $bgOverlay = 'bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.1) 0%,transparent 60%)]';
    } else { // Nublado à noite
        $bgGradient = 'from-[#0f2027] via-[#203a43] to-[#2c5364]';
        $bgOverlay = 'bg-[linear-gradient(135deg,rgba(255,255,255,0.02) 25%,transparent 25%)]';
    }
}

// Adicionar efeito de animação para condições específicas
if ($weatherId >= 200 && $weatherId < 300) { // Trovoada
    $animationClass = 'animate-pulse';
} elseif ($weatherId >= 300 && $weatherId < 700) { // Chuva e neve
    $animationClass = 'animate-falling';
} else {
    $animationClass = '';
}

// Adicionar classe de intensidade baseada na condição
if ($weatherId >= 500 && $weatherId < 600) { // Chuva forte
    $intensityClass = 'opacity-90';
} elseif ($weatherId >= 300 && $weatherId < 500) { // Chuva fraca
    $intensityClass = 'opacity-70';
} else {
    $intensityClass = 'opacity-100';
}

// Adicionar efeito de brilho para dias ensolarados
if ($weatherId == 800 && $isDayTime) {
    $glowEffect = 'shadow-lg shadow-yellow-500/50';
} else {
    $glowEffect = '';
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eyefind.info</title>
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
                    'eyefind-light': '#F8FAFC',
                    'eyefind-dark': '#02343F',
                    'eyefind-container': '#DCE7EB'
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
                                <input type="text" name="q" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue" placeholder="Procurar no Eyefind">
                                <button type="submit" class="absolute right-3 top-3 text-eyefind-blue">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="flex items-center gap-6 mt-4 md:mt-0">

                <?php if (!isLogado()): ?>
                    <a href="login.php" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700 transition">
                        Entrar
                    </a>
                <?php else: ?>
                    <?php
                    $usuario = getUsuarioAtual($pdo);
                    $is_admin = isset($usuario['is_admin']) && $usuario['is_admin'] == 1;
                    ?>

                    <!-- Criar Blog -->
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

                    <!-- Gerenciar Blogs -->
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

                    <div class="relative" id="dropdown-container">

                        <button class="p-3 hover:scale-110 transition duration-200" id="dropdown-button">
                            <img src="icon/maisopcoes.png" class="w-8 h-8" alt="Mais opções">
                        </button>

                        <!-- Dropdown -->
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded shadow-lg hidden z-50"
                            id="dropdown-menu">
                            
                            <?php if ($is_admin): ?>
                                <a href="admin.php" 
                                class="block px-4 py-2 text-gray-800 hover:bg-purple-100 font-bold">
                                    Admin
                                </a>
                            <?php endif; ?>

                            <a href="logout.php" 
                            class="block px-4 py-2 text-gray-800 hover:bg-red-100 font-bold">
                                Logout
                            </a>
                        </div>
                    </div>



                <?php endif; ?>
                </div>
            </div>
        </div>
    </section>


    <div class="w-full h-2 bg-yellow-400"></div>

    <div class="w-full bg-white shadow-md">
        <div class="max-w-6xl mx-auto px-16">
            <div class="flex overflow-x-auto scrollbar-hide whitespace-nowrap">
                
                <?php foreach ($categorias as $categoria): ?>
                    <a href="category.php?id=<?php echo $categoria["id"]; ?>" 
                    class="inline-flex items-center px-4 py-4 hover:bg-eyefind-light cursor-pointer transition group space-x-3 border-r">
                        
                        <i class="<?php echo $categoria["icone"]; ?> text-2xl text-eyefind-blue group-hover:scale-110 transition"></i>
                        
                        <p class="font-bold text-eyefind-dark">
                            <?php echo $categoria["nome"]; ?>
                        </p>
                    </a>
                <?php endforeach; ?>

            </div>
        </div>
    </div>





<div class="max-w-6xl mx-auto">
    <div class="grid md:grid-cols-3 gap-1 mt-1">
        <div class="col-span-2 bg-gray-100 p-3 shadow-md">
            <?php if ($ultimaNoticia): ?>
                <div class="border-b-2 border-eyefind-blue pb-4 mb-4">
                    <div class="flex items-center gap-4 mb-4">
                        <h2 class="text-xl font-bold text-eyefind-blue">ÚLTIMAS NOTÍCIAS</h2>
                    </div>
                    
                    <!-- Cabeçalho com site e autor -->
                    <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                        <span class="bg-eyefind-blue text-white px-2 py-1 rounded-full text-xs font-bold">
                            <?php echo htmlspecialchars($ultimaNoticia['site_nome']); ?>
                        </span>
                        <span>•</span>
                        <span>
                            <i class="fas fa-user mr-1"></i>
                            <?php echo htmlspecialchars($ultimaNoticia['autor_nome'] ?? 'Redação'); ?>
                        </span>
                        <?php if ($ultimaNoticia['categoria']): ?>
                            <span>•</span>
                            <span class="text-eyefind-blue">
                                <i class="far fa-folder mr-1"></i>
                                <?php echo htmlspecialchars($ultimaNoticia['categoria']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Título -->
                    <h3 class="text-lg font-bold text-eyefind-dark mb-3">
                        <a href="ver_noticia.php?website_id=<?php echo $ultimaNoticia['site_id']; ?>&noticia_id=<?php echo $ultimaNoticia['id']; ?>" 
                           class="hover:text-eyefind-blue transition">
                            <?php echo htmlspecialchars($ultimaNoticia['titulo']); ?>
                        </a>
                    </h3>
                    
                    <!-- Resumo -->
                    <p class="text-gray-700 mb-3">
                        <?php echo htmlspecialchars($ultimaNoticia['resumo'] ?? substr(strip_tags($ultimaNoticia['conteudo']), 0, 150) . '...'); ?>
                    </p>
                    
                    <!-- Link para ler mais -->
                    <a href="ver_noticia.php?website_id=<?php echo $ultimaNoticia['site_id']; ?>&noticia_id=<?php echo $ultimaNoticia['id']; ?>" 
                       class="inline-flex items-center text-eyefind-blue hover:text-eyefind-dark font-bold transition text-sm">
                        LEIA O ARTIGO COMPLETO →
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-newspaper text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">Nenhuma notícia publicada ainda.</p>
                </div>
            <?php endif; ?>

            <?php if ($websiteDoMinuto): ?>
                <div>
                    <h3 class="text-lg font-bold text-eyefind-blue mb-4">WEBSITE DO MINUTO</h3>
                    <a href="website.php?id=<?php echo $websiteDoMinuto['id']; ?>" class="block">
                        <div class="flex flex-col md:flex-row items-center gap-4 bg-gray-200 p-4 rounded cursor-pointer hover:bg-gray-300 transition">
                            <div class="w-full md:w-64 aspect-video flex-shrink-0">
                                <img
                                    src="<?php echo $websiteDoMinuto['imagem']; ?>"
                                    alt="<?php echo $websiteDoMinuto['nome']; ?>"
                                    class="w-full h-full object-cover rounded">
                            </div>
                            <div class="flex-1">
                                <p class="text-eyefind-blue font-bold text-xl">
                                    <?php echo $websiteDoMinuto['nome']; ?>
                                </p>
                                <p class="text-eyefind-dark">
                                    <?php echo $websiteDoMinuto['descricao']; ?>
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
        </div>

            <div class="space-y-1">
                <?php if ($publicidade): ?>
                    <div class="bg-gray-100 p-3 shadow-md">
                        <p class="text-sm text-gray-500 mb-2">Publicidade patrocinada</p>
                        <div class="relative w-full aspect-[16/9]">
                            <a href="<?php echo $publicidade["url"]; ?>" target="_blank">
                                <img
                                    src="<?php echo $publicidade["imagem"]; ?>"
                                    alt="<?php echo $publicidade["nome"]; ?>"
                                    class="absolute w-full h-full object-cover">
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="relative overflow-hidden bg-gradient-to-br <?php echo $bgGradient; ?> p-8 shadow-md rounded-lg transition-all duration-500">
                    <div class="absolute inset-0 <?php echo $bgOverlay; ?> opacity-40"></div>

                    <?php if ($isDayTime && $weatherId == 800): ?>
                        <div class="absolute -top-20 -right-20 w-40 h-40 bg-yellow-500 rounded-full blur-3xl opacity-20"></div>
                    <?php endif; ?>

                    <?php if (!$isDayTime && $weatherId == 800): ?>
                        <div class="absolute inset-0 opacity-30">
                            <div class="absolute h-1 w-1 bg-white rounded-full" style="top: 10%; left: 15%"></div>
                            <div class="absolute h-1 w-1 bg-white rounded-full" style="top: 15%; left: 45%"></div>
                            <div class="absolute h-1 w-1 bg-white rounded-full" style="top: 20%; left: 75%"></div>
                            <div class="absolute h-1 w-1 bg-white rounded-full" style="top: 35%; left: 25%"></div>
                            <div class="absolute h-1 w-1 bg-white rounded-full" style="top: 40%; left: 85%"></div>
                        </div>
                    <?php endif; ?>

                    <div class="absolute top-3 right-3 opacity-75">
                        <?php if (!$isDayTime): ?>
                            <i class="fas fa-moon text-yellow-100 text-xl"></i>
                        <?php else: ?>
                            <i class="fas fa-sun text-yellow-300 text-xl"></i>
                        <?php endif; ?>
                    </div>

                    <!-- Conteúdo principal -->
                    <div class="relative z-10">
                        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                            <div class="text-center md:text-left">
                                <h3 class="text-2xl font-bold text-white mb-2">LOS SANTOS</h3>
                                <p class="text-lg text-white/90">
                                    <?php echo date("d/m/Y"); ?>
                                </p>
                                <p class="text-lg text-white/90">
                                    <?php echo date("l"); ?>
                                </p>
                                <p class="text-2xl font-bold text-white mt-2" id="current-time">
                                    <?php echo date("H:i"); ?>
                                </p>
                            </div>

                            <div class="h-px md:h-32 w-full md:w-px bg-white/20"></div>

                            <div class="text-center">
                                <i class="<?php echo $iconClass; ?> text-5xl mb-3" style="color: <?php echo $iconColor; ?>;"></i>
                                <div class="flex items-end justify-center">
                                    <span class="text-4xl font-bold text-white"><?php echo $temp; ?></span>
                                    <span class="text-xl text-white">°C</span>
                                </div>
                                <p class="text-white/90 mt-2"><?php echo $weatherDescription; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="mt-1 bg-gray-100 p-3 shadow-md">
            <h3 class="text-lg font-bold text-eyefind-blue mb-4">WEBSITES SUGERIDOS PELO EYEFIND</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($websitesSugeridos as $website): ?>
                    <a href="website.php?id=<?php echo $website["id"]; ?>" class="block">
                        <img src="<?php echo $website["imagem"]; ?>" alt="<?php echo $website["nome"]; ?>" class="w-full h-40 object-cover hover:opacity-80 transition cursor-pointer rounded-lg shadow-md">
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <script>
        function updateTime() {
            const now = new Date();
            const timeElement = document.getElementById('current-time');

            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('pt-BR', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }
        }

        // Atualiza imediatamente ao carregar
        updateTime();

        // Atualiza a cada 1 segundo
        setInterval(updateTime, 1000);


        const dropdownContainer = document.getElementById('dropdown-container');
        const dropdownMenu = document.getElementById('dropdown-menu');
        let timeoutId;

        dropdownContainer.addEventListener('mouseenter', () => {
            clearTimeout(timeoutId);
            dropdownMenu.classList.remove('hidden');
        });


        dropdownContainer.addEventListener('mouseleave', () => {
            timeoutId = setTimeout(() => {
                dropdownMenu.classList.add('hidden');
            }, 300);
        });

        dropdownMenu.addEventListener('mouseenter', () => {
            clearTimeout(timeoutId);
        });

        dropdownMenu.addEventListener('mouseleave', () => {
            timeoutId = setTimeout(() => {
                dropdownMenu.classList.add('hidden');
            }, 300);
        });

    document.addEventListener("DOMContentLoaded", function () {
        const button = document.getElementById("dropdown-button");
        const menu = document.getElementById("dropdown-menu");
        const container = document.getElementById("dropdown-container");

        // Abrir / fechar ao clicar no botão
        button.addEventListener("click", function (e) {
            e.stopPropagation();
            menu.classList.toggle("hidden");
        });

        // Fechar se clicar fora
        document.addEventListener("click", function (e) {
            if (!container.contains(e.target)) {
                menu.classList.add("hidden");
            }
        });
    });

    </script>
</body>

</html>

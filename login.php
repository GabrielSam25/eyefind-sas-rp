<?php
require_once 'config.php';
$erro = "";

if (isLogado()) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    if (empty($email) || empty($senha)) {
        $erro = "Preencha todos os campos.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];

            $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
            $stmt->execute([$usuario['id']]);
            
            header("Location: index.php");
            exit;
        } else {
            $erro = "Email ou senha incorretos.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Eyefind.info</title>
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
    <section class="bg-[#488BC2] shadow-md">
        <div class="max-w-6xl mx-auto p-4">
            <div class="flex justify-center items-center">
                <div class="w-64">
                    <img src="imagens/eyefind-logo.png" alt="Eyefind.info Logo" class="w-full">
                </div>
            </div>
        </div>
    </section>

    <div class="w-full h-2 bg-yellow-400"></div>

    <section class="mt-8 max-w-md mx-auto bg-white p-8 shadow-md rounded">
        <h2 class="text-2xl font-bold text-eyefind-blue mb-6 text-center">Login</h2>

        <?php if (!empty($erro)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?php echo $erro; ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-4">
                <label for="email" class="block text-eyefind-dark font-bold mb-2">Email</label>
                <input type="email" id="email" name="email" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue">
            </div>

            <div class="mb-6">
                <label for="senha" class="block text-eyefind-dark font-bold mb-2">Senha</label>
                <input type="password" id="senha" name="senha" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue">
            </div>

            <div class="flex justify-between items-center">
                <button type="submit" class="bg-eyefind-blue text-white px-6 py-2 rounded font-bold hover:bg-eyefind-dark transition">
                    Entrar
                </button>
                <a href="register.php" class="text-eyefind-blue hover:text-eyefind-dark transition">
                    Não tem conta? Registre-se
                </a>
            </div>
        </form>
    </section>
    </div>
</body>

</html>

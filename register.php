<?php
require_once 'config.php';
$erro = "";
$sucesso = "";

if (isLogado()) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    $confirma_senha = $_POST['confirma_senha'];

    if (empty($nome) || empty($email) || empty($senha) || empty($confirma_senha)) {
        $erro = "Preencha todos os campos.";
    } elseif ($senha != $confirma_senha) {
        $erro = "As senhas não coincidem.";
    } elseif (strlen($senha) < 6) {
        $erro = "A senha deve ter pelo menos 6 caracteres.";
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $emailExiste = $stmt->fetchColumn();

        if ($emailExiste) {
            $erro = "Este email já está em uso.";
        } else {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
            if ($stmt->execute([$nome, $email, $senhaHash])) {
                $sucesso = "Conta criada com sucesso! Agora você pode fazer login.";
            } else {
                $erro = "Erro ao criar conta. Tente novamente.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Eyefind.info</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" sizes="192x192" href="icon/android-chrome-192x192.png">

    <link rel="icon" type="image/png" sizes="512x512" href="icon/android-chrome-512x512.png">

    <link rel="apple-touch-icon" sizes="180x180" href="icon/apple-touch-icon.png">

    <link rel="icon" type="image/png" sizes="16x16" href="icon/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icon/favicon-32x32.png">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
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
        <h2 class="text-2xl font-bold text-eyefind-blue mb-6 text-center">Criar Conta</h2>

        <?php if (!empty($erro)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?php echo $erro; ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($sucesso)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <p><?php echo $sucesso; ?></p>
                <p class="mt-2">
                    <a href="login.php" class="font-bold underline">Ir para o login</a>
                </p>
            </div>
        <?php else: ?>
            <form method="POST" action="register.php">
                <div class="mb-4">
                    <label for="nome" class="block text-eyefind-dark font-bold mb-2">Nome</label>
                    <input type="text" id="nome" name="nome" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue">
                </div>

                <div class="mb-4">
                    <label for="email" class="block text-eyefind-dark font-bold mb-2">
                        Email
                    </label>
                    
                    <input 
                        type="email" 
                        id="email" 
                        name="email"
                        required
                        pattern="^[a-zA-Z0-9._%+-]+@eyefind\.mail$"
                        title="Use um email válido do domínio @eyefind.mail"
                        class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue"
                    >
                </div>

                <div class="mb-4">
                    <label for="senha" class="block text-eyefind-dark font-bold mb-2">Senha</label>
                    <input type="password" id="senha" name="senha" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue">
                </div>

                <div class="mb-6">
                    <label for="confirma_senha" class="block text-eyefind-dark font-bold mb-2">Confirmar Senha</label>
                    <input type="password" id="confirma_senha" name="confirma_senha" class="w-full px-4 py-2 bg-eyefind-light border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue">
                </div>

                <div class="flex justify-between items-center">
                    <button type="submit" class="bg-eyefind-blue text-white px-6 py-2 rounded font-bold hover:bg-eyefind-dark transition">
                        Registrar
                    </button>
                    <a href="login.php" class="text-eyefind-blue hover:text-eyefind-dark transition">
                        Já tem conta? Fazer login
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </section>
    </div>
</body>

</html>

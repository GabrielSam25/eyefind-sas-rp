<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Eyefind.mail</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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

<body class="bg-eyefind-light h-screen flex flex-col">

    <!-- ================= HEADER ================= -->
    <section class="bg-[#488BC2] shadow-md">
        <div class="max-w-7xl mx-auto p-4">
            <div class="flex flex-col md:flex-row justify-between items-center">

                <!-- Logo + Busca -->
                <div class="flex flex-col md:flex-row items-center gap-6 w-full">
                    
                    <div class="w-64">
                        <img src="" alt="Eyefind Logo" class="w-full">
                    </div>

                    <div class="w-full md:w-96">
                        <form action="search.php" method="GET">
                            <div class="relative">
                                <input type="text" name="q"
                                    class="w-full px-4 py-2 bg-white border-2 border-eyefind-blue rounded focus:outline-none focus:ring-2 focus:ring-eyefind-blue"
                                    placeholder="Procurar no Eyefind">
                                <button type="submit"
                                    class="absolute right-3 top-3 text-eyefind-blue">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="w-full h-2 bg-yellow-400"></div>


    <!-- ================= CONTEÚDO ================= -->
    <div class="flex flex-1 overflow-hidden">

        <!-- ===== SIDEBAR ===== -->
        <aside class="w-64 bg-white border-r border-gray-300 p-6">

            <!-- Escrever -->
            <button class="w-full  text-black py-3 rounded font-bold mb-6 flex items-center justify-center gap-2">
                <i class="fas fa-pen"></i> Escrever
            </button>

            <!-- Menu -->
            <nav class="space-y-4 text-gray-700 font-medium">

                <a href="#" class="flex items-center gap-3 hover:text-eyefind-blue">
                    <i class="far fa-envelope"></i> Caixa de entrada
                </a>

                <a href="#" class="flex items-center gap-3 hover:text-eyefind-blue">
                    <i class="far fa-star"></i> Com estrela
                </a>

                <a href="#" class="flex items-center gap-3 hover:text-eyefind-blue">
                    <i class="far fa-paper-plane"></i> Enviados
                </a>

                <a href="#" class="flex items-center gap-3 hover:text-eyefind-blue">
                    <i class="far fa-trash-alt"></i> Lixeira
                </a>

            </nav>

        </aside>


        <!-- ===== LISTA DE EMAILS ===== -->
        <main class="flex-1 bg-white overflow-y-auto">

            <!-- Barra superior da lista -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-bold text-gray-700">Caixa de Entrada</h2>
                <span class="text-sm text-gray-500">2 mensagens</span>
            </div>

            <!-- Emails -->
            <div class="divide-y divide-gray-200">

                <!-- Email Item -->
                <div class="px-6 py-4 hover:bg-gray-100 cursor-pointer flex items-center gap-4">

                    <i class="far fa-star text-gray-400 hover:text-yellow-400"></i>

                    <div class="w-48 font-semibold text-gray-800">
                        Timothy
                    </div>

                    <div class="flex-1 text-gray-600 truncate">
                        Recolher o gado cambara, tá chovendo
                    </div>

                    <div class="text-sm text-gray-400">
                        09:32
                    </div>

                </div>

                <!-- Email Item -->
                <div class="px-6 py-4 hover:bg-gray-100 cursor-pointer flex items-center gap-4">

                    <i class="far fa-star text-gray-400 hover:text-yellow-400"></i>

                    <div class="w-48 font-semibold text-gray-800">
                        Marcelo
                    </div>

                    <div class="flex-1 text-gray-600 truncate">
                        Bom dia
                    </div>

                    <div class="text-sm text-gray-400">
                        08:11
                    </div>

                </div>

            </div>

        </main>

    </div>

</body>
</html>
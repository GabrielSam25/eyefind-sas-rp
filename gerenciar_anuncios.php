<?php
require_once 'config.php';

if (!isLogado()) {
    header('Location: login.php');
    exit;
}

$website_id = intval($_GET['website_id'] ?? 0);
if (!$website_id) {
    header('Location: manage_blogs.php');
    exit;
}

// Verificar permissão
$usuario = getUsuarioAtual($pdo);
$stmt = $pdo->prepare("SELECT * FROM websites WHERE id = ? AND usuario_id = ?");
$stmt->execute([$website_id, $usuario['id']]);
$website = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$website) {
    header('Location: manage_blogs.php');
    exit;
}

// Verificar se é do tipo classificados
if ($website['tipo'] != 'classificados') {
    header('Location: manage_blogs.php');
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao'])) {
        if ($_POST['acao'] == 'salvar') {
            $titulo = $_POST['titulo'];
            $descricao = $_POST['descricao'];
            $preco = !empty($_POST['preco']) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['preco']) : null;
            $imagem = $_POST['imagem'] ?? '';
            $contato = $_POST['contato'] ?? '';
            $telefone = $_POST['telefone'] ?? '';
            $email = $_POST['email'] ?? '';
            $categoria = $_POST['categoria'] ?? '';
            $data_validade = !empty($_POST['data_validade']) ? $_POST['data_validade'] : null;
            $status = $_POST['status'] ?? 'ativo';
            
            if (!empty($_POST['id'])) {
                $stmt = $pdo->prepare("UPDATE classificados_anuncios SET titulo = ?, descricao = ?, preco = ?, imagem = ?, contato = ?, telefone = ?, email = ?, categoria = ?, data_validade = ?, status = ? WHERE id = ? AND website_id = ?");
                $stmt->execute([$titulo, $descricao, $preco, $imagem, $contato, $telefone, $email, $categoria, $data_validade, $status, $_POST['id'], $website_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO classificados_anuncios (website_id, titulo, descricao, preco, imagem, contato, telefone, email, categoria, data_validade, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$website_id, $titulo, $descricao, $preco, $imagem, $contato, $telefone, $email, $categoria, $data_validade, $status]);
            }
            
            header('Location: gerenciar_anuncios.php?website_id=' . $website_id . '&msg=salvo');
            exit;
        }
        
        if ($_POST['acao'] == 'excluir' && isset($_POST['id'])) {
            $stmt = $pdo->prepare("DELETE FROM classificados_anuncios WHERE id = ? AND website_id = ?");
            $stmt->execute([$_POST['id'], $website_id]);
            header('Location: gerenciar_anuncios.php?website_id=' . $website_id . '&msg=excluido');
            exit;
        }
    }
}

// Obter anúncios
$stmt = $pdo->prepare("SELECT * FROM classificados_anuncios WHERE website_id = ? ORDER BY data_criacao DESC");
$stmt->execute([$website_id]);
$anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter categorias
$stmt = $pdo->prepare("SELECT DISTINCT categoria FROM classificados_anuncios WHERE website_id = ? AND categoria IS NOT NULL");
$stmt->execute([$website_id]);
$categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Anúncios - <?php echo htmlspecialchars($website['nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-6xl mx-auto py-8 px-4">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-purple-600">Gerenciar Anúncios Classificados</h1>
                    <p class="text-gray-600">Site: <?php echo htmlspecialchars($website['nome']); ?></p>
                </div>
                <a href="manage_blogs.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php 
                    if ($_GET['msg'] == 'salvo') echo "Anúncio salvo com sucesso!";
                    if ($_GET['msg'] == 'excluido') echo "Anúncio excluído com sucesso!";
                    ?>
                </div>
            <?php endif; ?>

            <button onclick="abrirModal()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition mb-6">
                <i class="fas fa-plus mr-2"></i>Novo Anúncio
            </button>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-4 border">ID</th>
                            <th class="py-2 px-4 border">Título</th>
                            <th class="py-2 px-4 border">Preço</th>
                            <th class="py-2 px-4 border">Categoria</th>
                            <th class="py-2 px-4 border">Contato</th>
                            <th class="py-2 px-4 border">Status</th>
                            <th class="py-2 px-4 border">Data</th>
                            <th class="py-2 px-4 border">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($anuncios as $anuncio): ?>
                        <tr>
                            <td class="py-2 px-4 border"><?php echo $anuncio['id']; ?></td>
                            <td class="py-2 px-4 border"><?php echo htmlspecialchars($anuncio['titulo']); ?></td>
                            <td class="py-2 px-4 border">
                                <?php if ($anuncio['preco']): ?>
                                    R$ <?php echo number_format($anuncio['preco'], 2, ',', '.'); ?>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4 border"><?php echo $anuncio['categoria'] ?? '-'; ?></td>
                            <td class="py-2 px-4 border"><?php echo $anuncio['contato'] ?? '-'; ?></td>
                            <td class="py-2 px-4 border">
                                <?php if ($anuncio['status'] == 'ativo'): ?>
                                    <span class="text-green-600">Ativo</span>
                                <?php else: ?>
                                    <span class="text-red-600">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-4 border"><?php echo date('d/m/Y', strtotime($anuncio['data_criacao'])); ?></td>
                            <td class="py-2 px-4 border">
                                <button onclick="editarAnuncio(<?php echo htmlspecialchars(json_encode($anuncio)); ?>)" class="text-blue-600 hover:text-blue-800 mr-2">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Excluir este anúncio?');">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?php echo $anuncio['id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modalAnuncio" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg w-11/12 max-w-4xl max-h-[90vh] overflow-y-auto p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold" id="modalTitulo">Novo Anúncio</h2>
                <button onclick="fecharModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <form method="POST" id="formAnuncio">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" id="anuncio_id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block font-bold mb-2">Título *</label>
                        <input type="text" name="titulo" id="anuncio_titulo" required class="w-full px-3 py-2 border rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block font-bold mb-2">Preço</label>
                        <input type="text" name="preco" id="anuncio_preco" class="w-full px-3 py-2 border rounded" placeholder="R$ 0,00">
                    </div>

                    <div class="mb-4">
                        <label class="block font-bold mb-2">Imagem (URL)</label>
                        <input type="url" name="imagem" id="anuncio_imagem" class="w-full px-3 py-2 border rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block font-bold mb-2">Categoria</label>
                        <input type="text" name="categoria" id="anuncio_categoria" class="w-full px-3 py-2 border rounded" list="categorias">
                        <datalist id="categorias">
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="mb-4">
                        <label class="block font-bold mb-2">Contato</label>
                        <input type="text" name="contato" id="anuncio_contato" class="w-full px-3 py-2 border rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block font-bold mb-2">Telefone</label>
                        <input type="text" name="telefone" id="anuncio_telefone" class="w-full px-3 py-2 border rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block font-bold mb-2">Email</label>
                        <input type="email" name="email" id="anuncio_email" class="w-full px-3 py-2 border rounded">
                    </div>

                    <div class="mb-4">
                        <label class="block font-bold mb-2">Data de Validade</label>
                        <input type="date" name="data_validade" id="anuncio_data_validade" class="w-full px-3 py-2 border rounded">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Descrição *</label>
                    <textarea name="descricao" id="anuncio_descricao" required class="w-full px-3 py-2 border rounded" rows="5"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-2">Status</label>
                    <select name="status" id="anuncio_status" class="w-full px-3 py-2 border rounded">
                        <option value="ativo">Ativo</option>
                        <option value="inativo">Inativo</option>
                    </select>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="fecharModal()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() {
            document.getElementById('modalAnuncio').classList.remove('hidden');
            document.getElementById('modalAnuncio').classList.add('flex');
            document.getElementById('modalTitulo').textContent = 'Novo Anúncio';
            document.getElementById('formAnuncio').reset();
            document.getElementById('anuncio_id').value = '';
        }

        function editarAnuncio(anuncio) {
            document.getElementById('modalAnuncio').classList.remove('hidden');
            document.getElementById('modalAnuncio').classList.add('flex');
            document.getElementById('modalTitulo').textContent = 'Editar Anúncio';
            
            document.getElementById('anuncio_id').value = anuncio.id;
            document.getElementById('anuncio_titulo').value = anuncio.titulo;
            document.getElementById('anuncio_preco').value = anuncio.preco ? 'R$ ' + anuncio.preco.toFixed(2).replace('.', ',') : '';
            document.getElementById('anuncio_imagem').value = anuncio.imagem || '';
            document.getElementById('anuncio_categoria').value = anuncio.categoria || '';
            document.getElementById('anuncio_contato').value = anuncio.contato || '';
            document.getElementById('anuncio_telefone').value = anuncio.telefone || '';
            document.getElementById('anuncio_email').value = anuncio.email || '';
            document.getElementById('anuncio_data_validade').value = anuncio.data_validade || '';
            document.getElementById('anuncio_descricao').value = anuncio.descricao;
            document.getElementById('anuncio_status').value = anuncio.status;
        }

        function fecharModal() {
            document.getElementById('modalAnuncio').classList.add('hidden');
            document.getElementById('modalAnuncio').classList.remove('flex');
        }
    </script>
</body>
</html>
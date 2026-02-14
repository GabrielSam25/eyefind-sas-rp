<?php
$products = $data['products'] ?? [];
?>
<div class="product-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 p-4">
    <?php foreach ($products as $product): ?>
        <div class="product-card bg-white rounded-lg shadow-lg overflow-hidden">
            <?php if (!empty($product['image'])): ?>
                <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                     class="w-full h-48 object-cover">
            <?php endif; ?>
            <div class="p-4">
                <h3 class="text-lg font-bold mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                <p class="text-2xl font-bold text-green-600 mb-2">
                    R$ <?php echo number_format($product['price'], 2, ',', '.'); ?>
                </p>
                <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($product['description']); ?></p>
                <button class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600 transition">
                    Adicionar ao Carrinho
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>
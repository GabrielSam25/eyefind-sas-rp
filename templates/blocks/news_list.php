<?php
$items = $data['items'] ?? [];
?>
<div class="news-list grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-4">
    <?php foreach ($items as $item): ?>
        <div class="news-item bg-white rounded-lg shadow-lg overflow-hidden">
            <?php if (!empty($item['image'])): ?>
                <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                     alt="<?php echo htmlspecialchars($item['title']); ?>" 
                     class="w-full h-48 object-cover">
            <?php endif; ?>
            <div class="p-4">
                <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($item['title']); ?></h3>
                <?php if (!empty($item['date'])): ?>
                    <p class="text-sm text-gray-500 mb-2"><?php echo date('d/m/Y', strtotime($item['date'])); ?></p>
                <?php endif; ?>
                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($item['content'])); ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
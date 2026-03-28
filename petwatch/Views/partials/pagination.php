<?php
declare(strict_types=1);

function renderPagination(int $totalPages, int $page, string $searchQuery): string {
    $searchParam = $searchQuery !== '' ? '&search=' . rawurlencode($searchQuery) : '';
    ob_start();
    ?>
    <?php if ($totalPages > 1): ?>
        <nav class="mt-4" aria-label="Pet list pagination">
            <ul class="pagination justify-content-center flex-wrap">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="index.php?page=<?php echo (int) ($page - 1); ?><?php echo $searchParam; ?>">Previous</a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled"><span class="page-link">Previous</span></li>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>">
                        <?php if ($p === $page): ?>
                            <span class="page-link"><?php echo (int) $p; ?></span>
                        <?php else: ?>
                            <a class="page-link" href="index.php?page=<?php echo (int) $p; ?><?php echo $searchParam; ?>"><?php echo (int) $p; ?></a>
                        <?php endif; ?>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="index.php?page=<?php echo (int) ($page + 1); ?><?php echo $searchParam; ?>">Next</a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled"><span class="page-link">Next</span></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
    <?php
    return (string) ob_get_clean();
}

?>


<?php
// /crm/modules/trash/get_stats.php - API для получения статистики корзины
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

// Функция получения статистики корзины
function getTrashStats($conn) {
    $stats = [
        'total' => 0,
        'documents' => 0,
        'references' => 0,
        'by_type' => []
    ];
    
    $query = "SELECT 
        item_type,
        document_type,
        COUNT(*) as count 
    FROM PCRM_TrashItems 
    GROUP BY item_type, document_type 
    ORDER BY item_type, document_type";
    
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats['total'] += $row['count'];
            if ($row['item_type'] === 'document') {
                $stats['documents'] += $row['count'];
            } else {
                $stats['references'] += $row['count'];
            }
            $stats['by_type'][] = $row;
        }
    }
    
    return $stats;
}

try {
    $stats = getTrashStats($conn);
    echo json_encode(['success' => true, 'stats' => $stats]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 
<?php
// /crm/modules/trash/TrashManager.php - Универсальный класс для управления корзиной

class TrashManager {
    private $conn;
    private $userId;
    
    // Конфигурация типов документов и справочников
    private $documentTypes = [
        'customer_order' => [
            'name' => 'Заказы клиентов',
            'table' => 'PCRM_Order',
            'related_tables' => ['PCRM_OrderItem'],
            'display_field' => 'order_number',
            'icon' => '📋'
        ],
        'purchase_order' => [
            'name' => 'Заказы поставщикам',
            'table' => 'PCRM_PurchaseOrder',
            'related_tables' => ['PCRM_PurchaseOrderItem'],
            'display_field' => 'purchase_order_number',
            'icon' => '📝'
        ],
        'transaction' => [
            'name' => 'Финансовые операции',
            'table' => 'PCRM_FinancialTransaction',
            'related_tables' => ['PCRM_PaymentMethodDetails'],
            'display_field' => 'description',
            'icon' => '💰'
        ],
        'shipment' => [
            'name' => 'Отгрузки',
            'table' => 'PCRM_ShipmentHeader',
            'related_tables' => ['PCRM_ShipmentItem'],
            'display_field' => 'shipment_number',
            'icon' => '🚚'
        ],
        'receipt' => [
            'name' => 'Приемки',
            'table' => 'PCRM_ReceiptHeader',
            'related_tables' => ['PCRM_ReceiptItem'],
            'display_field' => 'receipt_number',
            'icon' => '📦'
        ],
        'sales_return' => [
            'name' => 'Возвраты клиентов',
            'table' => 'PCRM_ReturnHeader',
            'related_tables' => ['PCRM_ReturnItem'],
            'display_field' => 'return_number',
            'icon' => '↩️'
        ],
        'supplier_return' => [
            'name' => 'Возвраты поставщикам',
            'table' => 'PCRM_SupplierReturnHeader',
            'related_tables' => ['PCRM_SupplierReturnItem'],
            'display_field' => 'return_number',
            'icon' => '🔄'
        ],
        'production_order' => [
            'name' => 'Производственные заказы',
            'table' => 'PCRM_ProductionOrder',
            'related_tables' => [],
            'display_field' => 'order_number',
            'icon' => '🏭'
        ],
        'production_operation' => [
            'name' => 'Производственные операции',
            'table' => 'PCRM_ProductionOperation',
            'related_tables' => ['PCRM_ProductionOperationItem'],
            'display_field' => 'operation_number',
            'icon' => '⚙️'
        ]
    ];
    
    private $referenceTypes = [
        'counterparty' => [
            'name' => 'Контрагенты',
            'table' => 'PCRM_Counterparty',
            'related_tables' => [],
            'display_field' => 'name',
            'icon' => '👥'
        ],
        'product' => [
            'name' => 'Товары',
            'table' => 'PCRM_Product',
            'related_tables' => ['PCRM_ProductImages'],
            'display_field' => 'name',
            'icon' => '📦'
        ],
        'category' => [
            'name' => 'Категории',
            'table' => 'PCRM_Categories',
            'related_tables' => [],
            'display_field' => 'name',
            'icon' => '📂'
        ],
        'warehouse' => [
            'name' => 'Склады',
            'table' => 'PCRM_Warehouse',
            'related_tables' => [],
            'display_field' => 'name',
            'icon' => '🏢'
        ],
        'driver' => [
            'name' => 'Водители',
            'table' => 'PCRM_Drivers',
            'related_tables' => [],
            'display_field' => 'name',
            'icon' => '🚗'
        ],
        'loader' => [
            'name' => 'Грузчики',
            'table' => 'PCRM_Loaders',
            'related_tables' => [],
            'display_field' => 'name',
            'icon' => '��'
        ]
    ];
    
    public function __construct($conn, $userId) {
        $this->conn = $conn;
        $this->userId = $userId;
    }
    
    /**
     * Перемещение элемента в корзину (soft delete)
     */
    public function moveToTrash($itemType, $documentType, $documentId, $reason = null) {
        try {
            $this->conn->begin_transaction();
            
            // Определяем тип элемента (document или reference)
            $isDocument = isset($this->documentTypes[$documentType]);
            $isReference = isset($this->referenceTypes[$documentType]);
            
            if (!$isDocument && !$isReference) {
                throw new Exception("Неизвестный тип документа: $documentType");
            }
            
            $itemTypeEnum = $isDocument ? 'document' : 'reference';
            $config = $isDocument ? $this->documentTypes[$documentType] : $this->referenceTypes[$documentType];
            
            // Получаем данные основной записи
            $mainData = $this->getRecordData($config['table'], $documentId);
            if (!$mainData) {
                throw new Exception("Запись не найдена: $documentType ID $documentId");
            }
            
            // Получаем связанные данные
            $relatedData = [];
            foreach ($config['related_tables'] as $relatedTable) {
                $relatedData[$relatedTable] = $this->getRelatedData($relatedTable, $documentType, $documentId);
            }
            
            // Формируем название для отображения
            $displayField = $config['display_field'];
            $originalName = $mainData[$displayField] ?? "ID: $documentId";
            
            // Вычисляем дату автоудаления (через месяц)
            $autoDeleteAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            // Добавляем запись в корзину
            $trashQuery = "INSERT INTO PCRM_TrashItems 
                (item_type, document_type, document_id, document_data, related_data, 
                 original_name, deleted_by, reason, auto_delete_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $trashStmt = $this->conn->prepare($trashQuery);
            $trashStmt->bind_param('ssisssiss', 
                $itemTypeEnum, 
                $documentType, 
                $documentId,
                json_encode($mainData),
                json_encode($relatedData),
                $originalName,
                $this->userId,
                $reason,
                $autoDeleteAt
            );
            
            if (!$trashStmt->execute()) {
                throw new Exception("Ошибка добавления в корзину: " . $trashStmt->error);
            }
            
            $trashItemId = $this->conn->insert_id;
            
            // Помечаем основную запись как удаленную
            $deleteQuery = "UPDATE {$config['table']} SET deleted = 1 WHERE id = ?";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteStmt->bind_param('i', $documentId);
            
            if (!$deleteStmt->execute()) {
                throw new Exception("Ошибка пометки записи как удаленной: " . $deleteStmt->error);
            }
            
            // Логируем операцию
            $this->logTrashOperation($trashItemId, 'moved_to_trash', $documentType, $documentId);
            
            $this->conn->commit();
            return ['success' => true, 'trash_id' => $trashItemId];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Восстановление элемента из корзины
     */
    public function restore($trashId) {
        try {
            $this->conn->begin_transaction();
            
            // Получаем данные из корзины
            $trashQuery = "SELECT * FROM PCRM_TrashItems WHERE id = ? AND can_restore = 1";
            $trashStmt = $this->conn->prepare($trashQuery);
            $trashStmt->bind_param('i', $trashId);
            $trashStmt->execute();
            $trashResult = $trashStmt->get_result();
            $trashItem = $trashResult->fetch_assoc();
            
            if (!$trashItem) {
                throw new Exception("Элемент корзины не найден или не может быть восстановлен");
            }
            
            $documentType = $trashItem['document_type'];
            $documentId = $trashItem['document_id'];
            $itemType = $trashItem['item_type'];
            
            // Получаем конфигурацию
            $config = $itemType === 'document' ? 
                $this->documentTypes[$documentType] : 
                $this->referenceTypes[$documentType];
            
            // Восстанавливаем основную запись
            $restoreQuery = "UPDATE {$config['table']} SET deleted = 0 WHERE id = ?";
            $restoreStmt = $this->conn->prepare($restoreQuery);
            $restoreStmt->bind_param('i', $documentId);
            
            if (!$restoreStmt->execute()) {
                throw new Exception("Ошибка восстановления записи: " . $restoreStmt->error);
            }
            
            // Логируем операцию
            $this->logTrashOperation($trashId, 'restored', $documentType, $documentId);
            
            // Удаляем из корзины
            $deleteTrashQuery = "DELETE FROM PCRM_TrashItems WHERE id = ?";
            $deleteTrashStmt = $this->conn->prepare($deleteTrashQuery);
            $deleteTrashStmt->bind_param('i', $trashId);
            $deleteTrashStmt->execute();
            
            $this->conn->commit();
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Окончательное удаление элемента из корзины
     */
    public function permanentDelete($trashId) {
        try {
            $this->conn->begin_transaction();
            
            // Получаем данные из корзины
            $trashQuery = "SELECT * FROM PCRM_TrashItems WHERE id = ?";
            $trashStmt = $this->conn->prepare($trashQuery);
            $trashStmt->bind_param('i', $trashId);
            $trashStmt->execute();
            $trashResult = $trashStmt->get_result();
            $trashItem = $trashResult->fetch_assoc();
            
            if (!$trashItem) {
                throw new Exception("Элемент корзины не найден");
            }
            
            $documentType = $trashItem['document_type'];
            $documentId = $trashItem['document_id'];
            $itemType = $trashItem['item_type'];
            
            // Получаем конфигурацию
            $config = $itemType === 'document' ? 
                $this->documentTypes[$documentType] : 
                $this->referenceTypes[$documentType];
            
            // Удаляем связанные данные
            foreach ($config['related_tables'] as $relatedTable) {
                $this->deleteRelatedData($relatedTable, $documentType, $documentId);
            }
            
            // Удаляем основную запись
            $deleteQuery = "DELETE FROM {$config['table']} WHERE id = ?";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteStmt->bind_param('i', $documentId);
            
            if (!$deleteStmt->execute()) {
                throw new Exception("Ошибка удаления записи: " . $deleteStmt->error);
            }
            
            // Логируем операцию
            $this->logTrashOperation($trashId, 'permanently_deleted', $documentType, $documentId);
            
            // Удаляем из корзины
            $deleteTrashQuery = "DELETE FROM PCRM_TrashItems WHERE id = ?";
            $deleteTrashStmt = $this->conn->prepare($deleteTrashQuery);
            $deleteTrashStmt->bind_param('i', $trashId);
            $deleteTrashStmt->execute();
            
            $this->conn->commit();
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Получение списка элементов корзины
     */
    public function getTrashItems($itemType = null, $search = '', $limit = 100, $offset = 0) {
        $whereConditions = [];
        $params = [];
        $types = '';
        
        if ($itemType) {
            $whereConditions[] = "item_type = ?";
            $params[] = $itemType;
            $types .= 's';
        }
        
        if ($search) {
            $whereConditions[] = "(original_name LIKE ? OR document_type LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= 'ss';
        }
        
        $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "SELECT 
            t.*, 
            u.username as deleted_by_username,
            DATEDIFF(t.auto_delete_at, NOW()) as days_until_auto_delete
        FROM PCRM_TrashItems t 
        LEFT JOIN PCRM_User u ON t.deleted_by = u.id 
        $whereClause 
        ORDER BY t.deleted_at DESC 
        LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($query);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $config = $row['item_type'] === 'document' ? 
                $this->documentTypes[$row['document_type']] : 
                $this->referenceTypes[$row['document_type']];
            
            $row['type_name'] = $config['name'];
            $row['icon'] = $config['icon'];
            $items[] = $row;
        }
        
        return $items;
    }
    
    /**
     * Автоочистка корзины (удаление старых элементов)
     */
    public function autoCleanup() {
        try {
            $this->conn->begin_transaction();
            
            // Получаем элементы для автоудаления
            $query = "SELECT * FROM PCRM_TrashItems WHERE auto_delete_at <= NOW()";
            $result = $this->conn->query($query);
            
            $deletedCount = 0;
            while ($item = $result->fetch_assoc()) {
                $deleteResult = $this->permanentDelete($item['id']);
                if ($deleteResult['success']) {
                    $deletedCount++;
                }
            }
            
            $this->conn->commit();
            return ['success' => true, 'deleted_count' => $deletedCount];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Вспомогательные методы
    
    private function getRecordData($table, $id) {
        $query = "SELECT * FROM $table WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    private function getRelatedData($table, $documentType, $documentId) {
        // Определяем поле связи на основе типа документа
        $linkFields = [
            'PCRM_OrderItem' => 'order_id',
            'PCRM_PurchaseOrderItem' => 'purchase_order_id',
            'PCRM_PaymentMethodDetails' => 'transaction_id',
            'PCRM_ShipmentItem' => 'shipment_header_id',
            'PCRM_ReceiptItem' => 'receipt_header_id',
            'PCRM_ReturnItem' => 'return_id',
            'PCRM_SupplierReturnItem' => 'return_id',
            'PCRM_ProductionOperationItem' => 'operation_id',
            'PCRM_ProductImages' => 'product_id'
        ];
        
        $linkField = $linkFields[$table] ?? null;
        if (!$linkField) {
            return [];
        }
        
        $query = "SELECT * FROM $table WHERE $linkField = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $documentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    private function deleteRelatedData($table, $documentType, $documentId) {
        $linkFields = [
            'PCRM_OrderItem' => 'order_id',
            'PCRM_PurchaseOrderItem' => 'purchase_order_id',
            'PCRM_PaymentMethodDetails' => 'transaction_id',
            'PCRM_ShipmentItem' => 'shipment_header_id',
            'PCRM_ReceiptItem' => 'receipt_header_id',
            'PCRM_ReturnItem' => 'return_id',
            'PCRM_SupplierReturnItem' => 'return_id',
            'PCRM_ProductionOperationItem' => 'operation_id',
            'PCRM_ProductImages' => 'product_id'
        ];
        
        $linkField = $linkFields[$table] ?? null;
        if (!$linkField) {
            return;
        }
        
        $query = "DELETE FROM $table WHERE $linkField = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $documentId);
        $stmt->execute();
    }
    
    private function logTrashOperation($trashItemId, $action, $documentType, $documentId, $details = null) {
        $query = "INSERT INTO PCRM_TrashLog 
            (trash_item_id, action, document_type, document_id, user_id, details) 
            VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        $detailsJson = $details ? json_encode($details) : null;
        $stmt->bind_param('isssis', $trashItemId, $action, $documentType, $documentId, $this->userId, $detailsJson);
        $stmt->execute();
    }
    
    public function getDocumentTypes() {
        return $this->documentTypes;
    }
    
    public function getReferenceTypes() {
        return $this->referenceTypes;
    }
}
?> 
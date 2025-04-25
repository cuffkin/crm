<?php
// /crm/includes/related_documents.php

/**
 * Отображает связанные документы для указанного документа
 * 
 * @param mysqli $conn             Соединение с базой данных
 * @param string $source_type      Тип текущего документа (order, shipment, finance, return)
 * @param int    $source_id        ID текущего документа
 */
function showRelatedDocuments($conn, $source_type, $source_id) {
    // Если ID документа не указан, ничего не делаем
    if (empty($source_id)) {
        return;
    }
    
    // Проверяем существование таблицы PCRM_RelatedDocuments
    $relatedDocsTableExists = false;
    $relatedDocs = [];
    
    try {
        $tableCheckResult = $conn->query("SHOW TABLES LIKE 'PCRM_RelatedDocuments'");
        if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
            $relatedDocsTableExists = true;
            
            // Запрос для получения связанных документов
            $sql = "
            /* Документы, на основе которых создан текущий */
            (SELECT 
                rd.source_type AS doc_type,
                rd.source_id AS doc_id,
                CASE 
                    WHEN rd.source_type = 'order' THEN o.order_number
                    WHEN rd.source_type = 'shipment' THEN sh.shipment_number
                    WHEN rd.source_type = 'finance' THEN ft.transaction_number
                    WHEN rd.source_type = 'return' THEN r.return_number
                    ELSE CONCAT('Unknown #', rd.source_id)
                END AS doc_number,
                CASE 
                    WHEN rd.source_type = 'order' THEN o.order_date
                    WHEN rd.source_type = 'shipment' THEN sh.shipment_date
                    WHEN rd.source_type = 'finance' THEN ft.transaction_date
                    WHEN rd.source_type = 'return' THEN r.return_date
                    ELSE rd.created_at
                END AS doc_date,
                CASE
                    WHEN rd.source_type = 'finance' THEN ft.transaction_type
                    ELSE NULL
                END AS transaction_type,
                'Создан на основании' AS relation
            FROM PCRM_RelatedDocuments rd
            LEFT JOIN PCRM_Order o ON rd.source_type = 'order' AND rd.source_id = o.id
            LEFT JOIN PCRM_ShipmentHeader sh ON rd.source_type = 'shipment' AND rd.source_id = sh.id
            LEFT JOIN PCRM_FinancialTransaction ft ON rd.source_type = 'finance' AND rd.source_id = ft.id
            LEFT JOIN PCRM_ReturnHeader r ON rd.source_type = 'return' AND rd.source_id = r.id
            WHERE rd.related_type = ? AND rd.related_id = ?)
            
            UNION
            
            /* Документы, созданные на основе текущего */
            (SELECT 
                rd.related_type AS doc_type,
                rd.related_id AS doc_id,
                CASE 
                    WHEN rd.related_type = 'order' THEN o.order_number
                    WHEN rd.related_type = 'shipment' THEN sh.shipment_number
                    WHEN rd.related_type = 'finance' THEN ft.transaction_number
                    WHEN rd.related_type = 'return' THEN r.return_number
                    ELSE CONCAT('Unknown #', rd.related_id)
                END AS doc_number,
                CASE 
                    WHEN rd.related_type = 'order' THEN o.order_date
                    WHEN rd.related_type = 'shipment' THEN sh.shipment_date
                    WHEN rd.related_type = 'finance' THEN ft.transaction_date
                    WHEN rd.related_type = 'return' THEN r.return_date
                    ELSE rd.created_at
                END AS doc_date,
                CASE
                    WHEN rd.related_type = 'finance' THEN ft.transaction_type
                    ELSE NULL
                END AS transaction_type,
                'На основании создан' AS relation
            FROM PCRM_RelatedDocuments rd
            LEFT JOIN PCRM_Order o ON rd.related_type = 'order' AND rd.related_id = o.id
            LEFT JOIN PCRM_ShipmentHeader sh ON rd.related_type = 'shipment' AND rd.related_id = sh.id
            LEFT JOIN PCRM_FinancialTransaction ft ON rd.related_type = 'finance' AND rd.related_id = ft.id
            LEFT JOIN PCRM_ReturnHeader r ON rd.related_type = 'return' AND rd.related_id = r.id
            WHERE rd.source_type = ? AND rd.source_id = ?)
            
            ORDER BY doc_date DESC
            ";
            
            // Подготавливаем и выполняем запрос с обработкой ошибок
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Ошибка подготовки запроса: " . $conn->error);
            }
            
            $stmt->bind_param("sisi", $source_type, $source_id, $source_type, $source_id);
            $stmt->execute();
            
            if ($stmt->error) {
                throw new Exception("Ошибка выполнения запроса: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            // Собираем данные связанных документов
            $relatedDocs = $result->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Ошибка в функции showRelatedDocuments: " . $e->getMessage());
        // Продолжаем выполнение с пустым массивом документов
    }
    
    // Отображаем связанные документы (даже если их нет)
    ?>
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Связанные документы</h5>
        </div>
        <div class="card-body">
            <?php if (!$relatedDocsTableExists): ?>
                <div class="alert alert-info">
                    Функция связанных документов не настроена в системе.
                </div>
            <?php elseif (empty($relatedDocs)): ?>
                <div class="alert alert-info">
                    Для данного документа связанных документов нет.
                </div>
            <?php else: ?>
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Тип документа</th>
                            <th>Номер</th>
                            <th>Дата</th>
                            <th>Связь</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($relatedDocs as $doc): 
                            // Определяем тип документа для отображения
                            $docTypeText = '';
                            $openFunction = '';
                            $additionalParams = '';
                            
                            switch ($doc['doc_type']) {
                                case 'order':
                                    $docTypeText = 'Заказ';
                                    $openFunction = 'openOrderEditTab';
                                    break;
                                case 'shipment':
                                    $docTypeText = 'Отгрузка';
                                    $openFunction = 'openShipmentEditTab';
                                    break;
                                case 'finance':
                                    // Определяем тип финансовой операции
                                    $transactionType = $doc['transaction_type'] ?? '';
                                    if ($transactionType === 'income') {
                                        $docTypeText = 'Приходная КО';
                                    } elseif ($transactionType === 'expense') {
                                        $docTypeText = 'Расходная КО';
                                    } else {
                                        $docTypeText = 'Финансовая операция';
                                    }
                                    $openFunction = 'openFinanceEditTab';
                                    // Добавляем тип операции (income/expense) как дополнительный параметр
                                    $additionalParams = ", '" . $transactionType . "'";
                                    break;
                                case 'return':
                                    $docTypeText = 'Возврат';
                                    $openFunction = 'openReturnEditTab';
                                    break;
                                default:
                                    $docTypeText = ucfirst($doc['doc_type']);
                                    $openFunction = '';
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($docTypeText) ?></td>
                            <td><?= htmlspecialchars($doc['doc_number']) ?></td>
                            <td><?= $doc['doc_date'] ?></td>
                            <td><?= htmlspecialchars($doc['relation']) ?></td>
                            <td>
                                <?php if ($openFunction): ?>
                                <button class="btn btn-sm btn-info" onclick="<?= $openFunction ?>(<?= $doc['doc_id'] . $additionalParams ?>)">Открыть</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Регистрирует связь между документами
 * 
 * @param mysqli $conn           Соединение с базой данных
 * @param string $source_type    Тип исходного документа (order, shipment, finance, return)
 * @param int    $source_id      ID исходного документа
 * @param string $related_type   Тип связанного документа
 * @param int    $related_id     ID связанного документа
 * @return bool                  Успешность операции
 */
function registerRelatedDocument($conn, $source_type, $source_id, $related_type, $related_id) {
    try {
        // Проверяем существование таблицы PCRM_RelatedDocuments
        $tableCheckResult = $conn->query("SHOW TABLES LIKE 'PCRM_RelatedDocuments'");
        if (!$tableCheckResult || $tableCheckResult->num_rows === 0) {
            // Таблица не существует, создаем её
            $createTableSql = "
            CREATE TABLE IF NOT EXISTS `PCRM_RelatedDocuments` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `source_type` varchar(50) NOT NULL,
              `source_id` int(11) NOT NULL,
              `related_type` varchar(50) NOT NULL,
              `related_id` int(11) NOT NULL,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `source_index` (`source_type`, `source_id`),
              KEY `related_index` (`related_type`, `related_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ";
            
            if (!$conn->query($createTableSql)) {
                throw new Exception("Не удалось создать таблицу PCRM_RelatedDocuments: " . $conn->error);
            }
        }
        
        // Проверяем, нет ли уже такой связи
        $checkSql = "SELECT id FROM PCRM_RelatedDocuments 
                    WHERE source_type = ? AND source_id = ? 
                    AND related_type = ? AND related_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        if (!$checkStmt) {
            throw new Exception("Ошибка подготовки запроса проверки: " . $conn->error);
        }
        
        $checkStmt->bind_param("sisi", $source_type, $source_id, $related_type, $related_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Связь уже существует
            return true;
        }
        
        // Добавляем новую связь
        $insertSql = "INSERT INTO PCRM_RelatedDocuments 
                      (source_type, source_id, related_type, related_id) 
                      VALUES (?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        if (!$insertStmt) {
            throw new Exception("Ошибка подготовки запроса вставки: " . $conn->error);
        }
        
        $insertStmt->bind_param("sisi", $source_type, $source_id, $related_type, $related_id);
        $result = $insertStmt->execute();
        
        if (!$result) {
            throw new Exception("Ошибка при вставке связи: " . $insertStmt->error);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Ошибка в функции registerRelatedDocument: " . $e->getMessage());
        return false;
    }
}
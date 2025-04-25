// /crm/js/app.js

$(function() {
  $('[data-module]').on('click', function(e) {
    e.preventDefault();
    let modPath = $(this).data('module') || '';
    modPath = modPath.trim();
    openModuleTab(modPath);
  });

  // Запускаем автосохранение форм каждые 15 секунд (снизили интервал для надежности)
  setInterval(function() {
    autoSaveAllForms();
  }, 15000);
  
  // Запускаем синхронизацию с сервером каждые 45 секунд
  setInterval(function() {
    syncFormsWithServer();
  }, 45000);
  
  // Проверяем наличие сохраненной сессии после загрузки страницы
  $(document).ready(function() {
    // Небольшая задержка для полной загрузки DOM
    setTimeout(restoreUserSession, 1000);
  });
  
  // Добавляем обработчик для события перед закрытием страницы
  $(window).on('beforeunload', function() {
    // Принудительно сохраняем все формы и синхронизируем с сервером
    autoSaveAllForms();
    syncFormsWithServer(true); // true = синхронный запрос
    return undefined; // Убираем стандартное диалоговое окно 
  });
});

// Функция для открытия модуля в новой вкладке
function openModuleTab(modulePath) {
  let safePath = modulePath.replace(/\//g, '-');

  let tabId = 'tab-' + safePath;
  let tabContentId = 'content-' + safePath;

  if ($('#' + tabId).length > 0) {
    $('#' + tabId).tab('show');
    return;
  }

  let title = getModuleTitle(modulePath);

  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-module="${modulePath}">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
    
    // Удаляем информацию о закрытой вкладке
    openOrderTabs.delete(tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}">
      <p>Загрузка...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  $.ajax({
    url: '/crm/modules/' + modulePath + '_partial.php',
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Сохраняем текущую вкладку
      saveTabsState();
    },
    error: function(xhr) {
      if (xhr.status === 404) {
        tabPane.html('<div class="text-danger">Файл не найден (404). Проверьте структуру папок.</div>');
      } else if (xhr.status === 500) {
        tabPane.html('<div class="text-danger">Ошибка 500 на сервере. Проверьте PHP-код.</div>');
      } else {
        tabPane.html('<div class="text-danger">Ошибка загрузки ('+xhr.status+')</div>');
      }
    }
  });
}

// Функция для закрытия вкладки по её идентификатору контента
function closeOrderTab(contentId) {
  if (openOrderTabs.has(contentId)) {
    const { tabId } = openOrderTabs.get(contentId);
    closeModuleTab(tabId, contentId);
    openOrderTabs.delete(contentId);
    return true;
  }
  return false;
}

// Функция для закрытия вкладки
function closeModuleTab(tabId, tabContentId) {
  // Проверяем, есть ли несохраненные изменения
  if (hasUnsavedChanges(tabContentId)) {
    if (!confirm('Есть несохраненные изменения. Вы уверены, что хотите закрыть вкладку?')) {
      return; // Отменяем закрытие, если пользователь нажал "Отмена"
    }
  }
  
  // Проверяем, существуют ли элементы
  if (!$('#' + tabId).length || !$('#' + tabContentId).length) {
    return;
  }
  
  // Удаляем сохраненное состояние формы для этой вкладки
  removeTabFormState(tabContentId);
  
  let isActive = $('#' + tabId).hasClass('active');
  $('#' + tabId).closest('li').remove();
  $('#' + tabContentId).remove();
  
  // Удаляем информацию о закрытой вкладке из карты
  openOrderTabs.delete(tabContentId);
  
  // Если это активная вкладка, переключаемся на другую
  if (isActive) {
    // Ищем предыдущую вкладку или первую доступную
    let $prevTab = $('#crm-tabs a:last');
    if ($prevTab.length) {
      $prevTab.tab('show');
    }
  }
  
  // Сохраняем текущие открытые вкладки
  saveTabsState();
}

// Глобальная карта открытых вкладок для редактирования заказов и отгрузок
const openOrderTabs = new Map();

// Глобальное хранилище информации о формах
const globalFormsData = {
  // Форматированная дата последнего сохранения
  lastSaveTime: '',
  // Объект с данными по формам: {tabContentId: {formData: {...}}}
  forms: {}
};

// Функция для открытия редактирования заказа в новой вкладке
function openOrderEditTab(orderId, orderNumber = null) {
  const uniqueSuffix = Date.now(); // добавляем уникальный суффикс для избежания конфликтов
  const tabId = 'tab-order-edit-' + orderId + '-' + uniqueSuffix;
  const tabContentId = 'content-order-edit-' + orderId + '-' + uniqueSuffix;
  
  // Сохраняем информацию об открытой вкладке
  openOrderTabs.set(tabContentId, { tabId, orderId, type: 'order' });
  
  // Определяем заголовок вкладки
  let title = 'Новый заказ';
  
  if (orderId > 0) {
    // Если передан номер заказа, используем его
    if (orderNumber) {
      title = `Заказ ${orderNumber}`;
    } else {
      // Ищем номер заказа в таблице
      const orderRow = $(`table.table tbody tr td:first-child:contains(${orderId})`).closest('tr');
      if (orderRow.length) {
        const orderNumber = orderRow.find('td:nth-child(3)').text().trim();
        title = `Заказ ${orderNumber}`;
      } else {
        title = `Заказ #${orderId}`;
      }
    }
  }

  // Создаем новую вкладку
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-order-id="${orderId}" data-document-type="order">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}" data-document-type="order" data-id="${orderId}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Загружаем содержимое формы редактирования
  $.ajax({
    url: '/crm/modules/sales/orders/edit_partial.php',
    data: { 
      id: orderId, 
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Восстанавливаем сохраненное состояние формы, если есть
      restoreTabFormState(tabContentId, 'order', orderId);
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr) {
      tabPane.html('<div class="text-danger">Ошибка загрузки формы ('+xhr.status+')</div>');
    }
  });
  
  return { tabId, tabContentId };
}

// Функция для открытия редактирования рецепта производства в новой вкладке
function openRecipeTab(recipeId, viewMode = false) {
  console.log('Opening recipe tab:', recipeId, viewMode);
  const uniqueSuffix = Date.now(); // добавляем уникальный суффикс для избежания конфликтов
  const tabId = 'tab-recipe-' + recipeId + '-' + uniqueSuffix;
  const tabContentId = 'content-recipe-' + recipeId + '-' + uniqueSuffix;
  
  // Определяем заголовок вкладки
  let title = 'Новый рецепт';
  if (recipeId > 0) {
    title = 'Рецепт #' + recipeId;
  }
  if (viewMode) {
    title = 'Просмотр рецепта #' + recipeId;
  }

  // Создаем новую вкладку
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-recipe-id="${recipeId}" data-document-type="production-recipe">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}" data-document-type="production-recipe" data-id="${recipeId}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Загружаем содержимое формы редактирования
  $.ajax({
    url: '/crm/modules/production/recipes/edit_partial.php',
    data: { 
      id: recipeId, 
      view: viewMode ? 'true' : 'false',
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Восстанавливаем сохраненное состояние формы, если есть
      restoreTabFormState(tabContentId, 'production-recipe', recipeId);
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr) {
      if (xhr.status === 404) {
        tabPane.html('<div class="text-danger">Файл не найден (404). Проверьте структуру папок.</div>');
      } else if (xhr.status === 500) {
        tabPane.html('<div class="text-danger">Ошибка 500 на сервере. Проверьте PHP-код.</div>');
      } else {
        tabPane.html('<div class="text-danger">Ошибка загрузки ('+xhr.status+')</div>');
      }
    }
  });
  
  return { tabId, tabContentId };
}

// Экспортируем функции в глобальное пространство имен
window.openProductionOperationTab = openProductionOperationTab;
window.openRecipeTab = openRecipeTab;

// Функция для открытия редактирования отгрузки в новой вкладке
function openShipmentEditTab(shipmentId, options = {}) {
  const uniqueSuffix = Date.now(); // добавляем уникальный суффикс для избежания конфликтов
  const tabId = 'tab-shipment-edit-' + shipmentId + '-' + uniqueSuffix;
  const tabContentId = 'content-shipment-edit-' + shipmentId + '-' + uniqueSuffix;
  
  // Сохраняем информацию об открытой вкладке
  openOrderTabs.set(tabContentId, { tabId, shipmentId, type: 'shipment' });
  
  // Определяем заголовок вкладки
  let title = shipmentId > 0 ? 'Отгрузка #' + shipmentId : 'Новая отгрузка';

  // Создаем новую вкладку
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-shipment-id="${shipmentId}" data-document-type="shipment">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}" data-document-type="shipment" data-id="${shipmentId}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Формируем параметры для запроса
  const params = { 
    id: shipmentId, 
    tab: 1,
    tab_id: tabId,
    content_id: tabContentId
  };
  
  // Добавляем дополнительные параметры, если они есть
  if (options.order_id) params.order_id = options.order_id;
  if (options.based_on) params.based_on = options.based_on;

  // Загружаем содержимое формы редактирования
  $.ajax({
    url: '/crm/modules/shipments/edit_partial.php',
    data: params,
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Восстанавливаем сохраненное состояние формы, если есть
      restoreTabFormState(tabContentId, 'shipment', shipmentId);
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr) {
      tabPane.html('<div class="text-danger">Ошибка загрузки формы ('+xhr.status+')</div>');
    }
  });
  
  return { tabId, tabContentId };
}

// Функция для открытия редактирования финансовой операции в новой вкладке
function openFinanceEditTab(transactionId, transactionType, options = {}) {
  const uniqueSuffix = Date.now(); // добавляем уникальный суффикс для избежания конфликтов
  const tabId = 'tab-finance-edit-' + transactionId + '-' + uniqueSuffix;
  const tabContentId = 'content-finance-edit-' + transactionId + '-' + uniqueSuffix;
  
  // Сохраняем информацию об открытой вкладке
  openOrderTabs.set(tabContentId, { tabId, transactionId, type: 'finance', transactionType });
  
  // Заголовок вкладки
  let typeText = transactionType === 'income' ? 'Приход' : 'Расход';
  let title = transactionId > 0 ? `${typeText} #${transactionId}` : `Новый ${typeText.toLowerCase()}`;
  
  // Создаем новую вкладку
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-transaction-id="${transactionId}" data-transaction-type="${transactionType}" data-document-type="finance">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}" data-document-type="finance" data-id="${transactionId}" data-transaction-type="${transactionType}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Формируем параметры для запроса
  const params = { 
    id: transactionId,
    type: transactionType,
    tab: 1,
    tab_id: tabId,
    content_id: tabContentId
  };
  
  // Добавляем дополнительные параметры, если они есть
  if (options.order_id) params.order_id = options.order_id;
  if (options.shipment_id) params.shipment_id = options.shipment_id;
  if (options.return_id) params.return_id = options.return_id;
  if (options.amount) params.amount = options.amount;
  if (options.counterparty_id) params.counterparty_id = options.counterparty_id;
  if (options.based_on) params.based_on = options.based_on;
  if (options.description) params.description = options.description;

  // Загружаем содержимое формы редактирования
  $.ajax({
    url: '/crm/modules/finances/edit_partial.php',
    data: params,
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Восстанавливаем сохраненное состояние формы, если есть
      restoreTabFormState(tabContentId, 'finance', transactionId, { type: transactionType });
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr) {
      tabPane.html('<div class="text-danger">Ошибка загрузки формы ('+xhr.status+')</div>');
    }
  });
  
  return { tabId, tabContentId };
}

// Функция для открытия редактирования возврата в новой вкладке
function openReturnEditTab(returnId, options = {}) {
  const uniqueSuffix = Date.now(); // добавляем уникальный суффикс для избежания конфликтов
  const tabId = 'tab-return-edit-' + returnId + '-' + uniqueSuffix;
  const tabContentId = 'content-return-edit-' + returnId + '-' + uniqueSuffix;
  
  // Сохраняем информацию об открытой вкладке
  openOrderTabs.set(tabContentId, { tabId, returnId, type: 'return' });
  
  // Определяем заголовок вкладки
  let title = returnId > 0 ? `Возврат #${returnId}` : 'Новый возврат';
  
  // Создаем новую вкладку
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-return-id="${returnId}" data-document-type="return">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}" data-document-type="return" data-id="${returnId}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Формируем параметры для запроса
  const params = {
    id: returnId,
    tab: 1,
    tab_id: tabId,
    content_id: tabContentId
  };
  
  // Добавляем параметры, если они переданы
  if (options.order_id) params.order_id = options.order_id;
  if (options.based_on) params.based_on = options.based_on;
  
  // Загружаем содержимое редактирования
  $.ajax({
    url: '/crm/modules/sales/returns/edit_partial.php',
    data: params,
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Восстанавливаем сохраненное состояние формы, если есть
      restoreTabFormState(tabContentId, 'return', returnId);
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr) {
      tabPane.html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки формы возврата</h4>
          <p>Ответ сервера: ${xhr.responseText}</p>
        </div>
      `);
    }
  });
  
  return { tabId, tabContentId };
}

// Функция для обновления списка заказов во всех открытых вкладках
function updateOrderLists() {
  // Находим все вкладки со списком заказов
  $('div[id^="content-sales-orders-list"]').each(function() {
    let tabContent = $(this);
    
    $.ajax({
      url: '/crm/modules/sales/orders/list_partial.php',
      success: function(html) {
        // Заменяем только таблицу и кнопку, а не весь контент
        let tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        
        let newTable = $(tempDiv).find('table.table');
        let newButton = $(tempDiv).find('button.btn-primary.btn-sm.mb-2');
        
        tabContent.find('table.table').replaceWith(newTable);
        tabContent.find('button.btn-primary.btn-sm.mb-2').replaceWith(newButton);
        
        // Переподключаем обработчики событий к новым элементам
        initEventHandlers();
      }
    });
  });
}

// Функция для обновления списка отгрузок во всех открытых вкладках
function updateShipmentList() {
  // Находим все вкладки со списком отгрузок
  $('div[id^="content-shipments-list"]').each(function() {
    let tabContent = $(this);
    
    $.ajax({
      url: '/crm/modules/shipments/list_partial.php',
      success: function(html) {
        // Заменяем содержимое вкладки
        tabContent.html(html);
      }
    });
  });
}

// Функция для обновления списка финансовых операций
function updateFinanceList() {
  // Находим все вкладки со списком финансовых операций
  $('div[id^="content-finances-list"]').each(function() {
    let tabContent = $(this);
    
    $.ajax({
      url: '/crm/modules/finances/list_partial.php',
      success: function(html) {
        // Заменяем содержимое вкладки
        tabContent.html(html);
      }
    });
  });
}

// Функция для обновления списка возвратов
function updateReturnsList() {
  // Обновляем список возвратов в каждой вкладке, содержащей их
  $('div[id^="content-sales-returns-list"]').each(function() {
    let tabContent = $(this);
    
    $.ajax({
      url: '/crm/modules/sales/returns/list_partial.php',
      success: function(html) {
        // Заменяем содержимое вкладки
        tabContent.html(html);
      }
    });
  });
}

// Инициализация обработчиков событий для списка заказов
function initEventHandlers() {
  $('button.btn-danger.btn-sm').off('click').on('click', function() {
    const orderId = $(this).closest('tr').find('td:first').text();
    deleteOrder(orderId);
  });
}

// Функция для удаления отгрузки
function deleteShipment(shipmentId) {
  if (!confirm('Вы уверены, что хотите удалить эту отгрузку?')) return;
  
  $.get('/crm/modules/shipments/delete.php', { id: shipmentId }, function(response) {
    if (response === 'OK') {
      // Обновляем список отгрузок
      updateShipmentList();
      // alert('Отгрузка успешно удалена');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

// Функция для печати отгрузки
function printShipment(shipmentId) {
  // Открываем окно печати
  window.open('/crm/modules/shipments/print.php?id=' + shipmentId, '_blank');
}

// Функция для удаления финансовой операции
function deleteTransaction(transactionId) {
  if (!confirm('Вы уверены, что хотите удалить эту финансовую операцию?')) return;
  
  $.get('/crm/modules/finances/delete.php', { id: transactionId }, function(response) {
    if (response === 'OK') {
      // Обновляем список операций
      updateFinanceList();
      // alert('Финансовая операция успешно удалена');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

// Функция для печати финансовой операции
function printTransaction(transactionId) {
  // Открываем окно печати
  window.open('/crm/modules/finances/print.php?id=' + transactionId, '_blank');
}

// Функция для удаления возврата
function deleteReturn(returnId) {
  if (!confirm('Вы уверены, что хотите удалить этот возврат?')) return;
  
  $.get('/crm/modules/sales/returns/delete.php', { id: returnId }, function(response) {
    if (response === 'OK') {
      // Обновляем список возвратов
      updateReturnsList();
      // alert('Возврат успешно удален');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

// Функция для печати возврата
function printReturn(returnId) {
  // Открываем окно печати
  window.open('/crm/modules/sales/returns/print.php?id=' + returnId, '_blank');
}

// Создаем безопасную функцию уведомлений
window.appShowNotification = function(message, type = 'info', duration = 5000) {
  // Просто логируем в консоль, уведомления отключены для избежания рекурсии
  console.log('Notification:', message, type);
  
  // Создаём простое всплывающее окно bootstrap
  try {
    // Проверяем, есть ли уже контейнер для уведомлений
    let toastContainer = document.getElementById('toast-container');
    
    if (!toastContainer) {
      // Создаем контейнер, если его нет
      toastContainer = document.createElement('div');
      toastContainer.id = 'toast-container';
      toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      document.body.appendChild(toastContainer);
    }
    
    // Создаем класс для типа уведомления
    let bgClass = 'bg-primary';
    let iconClass = 'info-circle';
    
    switch(type) {
      case 'success':
        bgClass = 'bg-success';
        iconClass = 'check-circle';
        break;
      case 'warning':
        bgClass = 'bg-warning';
        iconClass = 'exclamation-triangle';
        break;
      case 'error':
      case 'danger':
        bgClass = 'bg-danger';
        iconClass = 'exclamation-circle';
        break;
      case 'info':
      default:
        bgClass = 'bg-primary';
        iconClass = 'info-circle';
        break;
    }
    
    // Создаем ID для этого уведомления
    const toastId = 'toast-' + Date.now();
    
    // Создаем HTML для уведомления
    const toastHTML = `
      <div id="${toastId}" class="toast ${bgClass} text-white" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header ${bgClass} text-white">
          <i class="fas fa-${iconClass} me-2"></i>
          <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Закрыть"></button>
        </div>
        <div class="toast-body">
          ${message}
        </div>
      </div>
    `;
    
    // Добавляем уведомление в контейнер
    toastContainer.innerHTML += toastHTML;
    
    // Показываем уведомление через Bootstrap API
    const toastElement = document.getElementById(toastId);
    if (toastElement) {
      const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: duration
      });
      toast.show();
      
      // Удаляем элемент после скрытия
      toastElement.addEventListener('hidden.bs.toast', function() {
        if (toastElement.parentNode) {
          toastElement.parentNode.removeChild(toastElement);
        }
      });
    }
  } catch (e) {
    console.error('Ошибка при показе уведомления:', e);
  }
};

// Для обратной совместимости с существующим кодом
function showNotification(message, type = 'info', duration = 5000) {
  // Используем глобальную функцию
  window.appShowNotification(message, type, duration);
}

// Делаем глобально доступным
window.showNotification = showNotification;

// Получение заголовка модуля
function getModuleTitle(path) {
  switch (path) {
    // Продажи
    case 'sales/orders/list':     return 'Заказы покупателей';
    case 'shipments/list':        return 'Отгрузки';
    case 'sales/returns/list':    return 'Возврат покупателя';

    // Закупки
    case 'purchases/orders/list':   return 'Заказ поставщику';
    case 'purchases/receipts/list': return 'Приёмки';
    case 'purchases/returns/list':  return 'Возврат поставщику';

    // Корректировки
    case 'corrections/inventory/list':       return 'Инвентаризация';
    case 'corrections/appropriations/list':  return 'Оприходование';
    case 'corrections/writeoff/list':        return 'Списание';

    // Прочие
    case 'users/list':       return 'Пользователи';
    case 'access/list':      return 'Управление доступом';
    case 'counterparty/list':return 'Контрагенты';
    case 'finances/list':    return 'Финансовые операции';
    case 'automations/list': return 'Автоматизации';

    // Товары
    case 'products/list':    return 'Список товаров';
    case 'categories/list':  return 'Категории';
    case 'warehouse/list':   return 'Склады';
    case 'stock/list':       return 'Остатки';

    // Производство
    case 'production/recipes/list_partial.php':   return 'Рецепты производства';
    case 'production/operations/list_partial.php': return 'Операции производства';
    case 'production/orders/list_partial.php':     return 'Заказы на производство';
    
    // Аналогичные пути без .php
    case 'production/recipes/list_partial':   return 'Рецепты производства';
    case 'production/operations/list_partial': return 'Операции производства';
    case 'production/orders/list_partial':     return 'Заказы на производство';

    // Справочники
    case 'loaders/list':     return 'Грузчики';
    case 'drivers/list':     return 'Водители';

    default:
      return path; // fallback
  }
}

// ======== ФУНКЦИИ ДЛЯ РАБОТЫ СО СВЯЗАННЫМИ ДОКУМЕНТАМИ ========

// Функция для создания отгрузки на основании заказа
function createShipmentFromOrder(orderId) {
  // Создаем новую вкладку для отгрузки
  const tabId = 'shipment-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'shipment-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = 'Новая отгрузка';
  
  // Добавляем новую вкладку
  $('#crm-tabs').append(`
    <li class="nav-item">
      <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab" data-order-id="${orderId}" data-document-type="shipment-from-order">
        ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);
  
  // Добавляем содержимое вкладки
  $('#crm-tab-content').append(`
    <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel" data-document-type="shipment-from-order" data-order-id="${orderId}">
      <div class="text-center p-5">
        <div class="spinner-border" role="status">
          <span class="visually-hidden">Загрузка...</span>
        </div>
      </div>
    </div>
  `);
  
  // Делаем новую вкладку активной
  $('.nav-link').removeClass('active');
  $('.tab-pane').removeClass('show active');
  $(`#${tabId}`).addClass('active');
  $(`#${tabContentId}`).addClass('show active');
  
  // Загружаем содержимое редактирования отгрузки
  $.ajax({
    url: '/crm/modules/shipments/edit_partial.php',
    data: { 
      id: 0, 
      order_id: orderId,
      based_on: 'order',
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      $(`#${tabContentId}`).html(html);
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Сохраняем информацию об открытой вкладке
      openOrderTabs.set(tabContentId, { tabId, orderId, type: 'shipment-from-order' });
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr, status, error) {
      $(`#${tabContentId}`).html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки формы отгрузки</h4>
          <p>Ответ сервера: ${xhr.responseText}</p>
        </div>
      `);
    }
  });
  
  // Обработчик закрытия вкладки
  $(`#${tabId} .btn-close`).on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });
}

// Функция для создания возврата на основании заказа
function createReturnFromOrder(orderId) {
  // Создаем новую вкладку для возврата
  const tabId = 'return-tab-' + Math.floor(Math.random() * 1000000);
  const tabContentId = 'return-content-' + Math.floor(Math.random() * 1000000);
  
  // Заголовок вкладки
  let tabTitle = 'Новый возврат';
  
  // Добавляем новую вкладку
  $('#crm-tabs').append(`
    <li class="nav-item">
      <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab" data-order-id="${orderId}" data-document-type="return-from-order">
        ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);
  
  // Добавляем содержимое вкладки
  $('#crm-tab-content').append(`
    <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel" data-document-type="return-from-order" data-order-id="${orderId}">
      <div class="text-center p-5">
        <div class="spinner-border" role="status">
          <span class="visually-hidden">Загрузка...</span>
        </div>
      </div>
    </div>
  `);
  
  // Делаем новую вкладку активной
  $('.nav-link').removeClass('active');
  $('.tab-pane').removeClass('show active');
  $(`#${tabId}`).addClass('active');
  $(`#${tabContentId}`).addClass('show active');
  
  // Загружаем содержимое редактирования возврата
  $.ajax({
    url: '/crm/modules/sales/returns/edit_partial.php',
    data: { 
      id: 0, 
      order_id: orderId,
      based_on: 'order',
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      $(`#${tabContentId}`).html(html);
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Сохраняем информацию об открытой вкладке
      openOrderTabs.set(tabContentId, { tabId, orderId, type: 'return-from-order' });
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr, status, error) {
      $(`#${tabContentId}`).html(`
        <div class="alert alert-danger">
          <h4>Ошибка загрузки формы возврата</h4>
          <p>Ответ сервера: ${xhr.responseText}</p>
        </div>
      `);
    }
  });
  
  // Обработчик закрытия вкладки
  $(`#${tabId} .btn-close`).on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });
}

// Функция для создания финансовой операции на основании заказа
function createFinanceFromOrder(orderId, type = 'income') {
  // Получаем информацию о заказе
  // ИСПРАВЛЕНИЕ: Изменен URL с несуществующего get_order_info.php на правильный order_api.php с параметром action
  $.getJSON('/crm/modules/sales/orders/order_api.php', { action: 'get_order_info', id: orderId }, function(response) {
    if (response.status === 'ok') {
      const data = response.data;
      
      // Создаем новую вкладку для финансовой операции
      const tabId = 'finance-tab-' + Math.floor(Math.random() * 1000000);
      const tabContentId = 'finance-content-' + Math.floor(Math.random() * 1000000);
      
      // Заголовок вкладки
      let tabTitle = (type === 'income') ? 'Новый приход' : 'Новый расход';
      
      // Добавляем новую вкладку
      $('#crm-tabs').append(`
        <li class="nav-item">
          <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab" data-order-id="${orderId}" data-document-type="finance-from-order" data-finance-type="${type}">
            ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
          </a>
        </li>
      `);
      
      // Добавляем содержимое вкладки
      $('#crm-tab-content').append(`
        <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel" data-document-type="finance-from-order" data-order-id="${orderId}" data-finance-type="${type}">
          <div class="text-center p-5">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">Загрузка...</span>
            </div>
          </div>
        </div>
      `);
      
      // Делаем новую вкладку активной
      $('.nav-link').removeClass('active');
      $('.tab-pane').removeClass('show active');
      $(`#${tabId}`).addClass('active');
      $(`#${tabContentId}`).addClass('show active');
      
      // Загружаем содержимое редактирования финансовой операции
      $.ajax({
        url: '/crm/modules/finances/edit_partial.php',
        data: { 
          id: 0,
          type: type,
          order_id: orderId,
          amount: data.total_amount || data.order_sum,
          counterparty_id: data.customer,
          tab: 1,
          tab_id: tabId,
          content_id: tabContentId,
          based_on: 'order',
          description: 'Оплата по заказу №' + data.order_number
        },
        success: function(html) {
          $(`#${tabContentId}`).html(html);
          
          // После загрузки содержимого запускаем отслеживание изменений формы
          initFormTracking(tabContentId);
          
          // Сохраняем информацию об открытой вкладке
          openOrderTabs.set(tabContentId, { tabId, orderId, type: 'finance-from-order', financeType: type });
          
          // Сохраняем состояние вкладок
          saveTabsState();
        },
        error: function(xhr, status, error) {
          $(`#${tabContentId}`).html(`
            <div class="alert alert-danger">
              <h4>Ошибка загрузки формы финансовой операции</h4>
              <p>Ответ сервера: ${xhr.responseText}</p>
            </div>
          `);
        }
      });
      
      // Обработчик закрытия вкладки
      $(`#${tabId} .btn-close`).on('click', function(e) {
        e.stopPropagation();
        closeModuleTab(tabId, tabContentId);
      });
    } else {
      alert('Ошибка при получении данных заказа: ' + (response.message || 'Неизвестная ошибка'));
    }
  });
}

// Функция для создания РКО на основании возврата
function createRkoFromReturn(returnId) {
  // Получаем информацию о возврате
  $.getJSON('/crm/modules/sales/returns/get_return_info.php', { id: returnId }, function(response) {
    if (response.status === 'ok') {
      const data = response.data;
      
      // Создаем новую вкладку для финансовой операции
      const tabId = 'finance-tab-' + Math.floor(Math.random() * 1000000);
      const tabContentId = 'finance-content-' + Math.floor(Math.random() * 1000000);
      
      // Заголовок вкладки
      let tabTitle = 'Новый расход';
      
      // Добавляем новую вкладку
      $('#crm-tabs').append(`
        <li class="nav-item">
          <a class="nav-link active" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" role="tab" data-return-id="${returnId}" data-document-type="finance-from-return">
            ${tabTitle} <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
          </a>
        </li>
      `);
      
      // Добавляем содержимое вкладки
      $('#crm-tab-content').append(`
        <div class="tab-pane fade show active" id="${tabContentId}" role="tabpanel" data-document-type="finance-from-return" data-return-id="${returnId}">
          <div class="text-center p-5">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">Загрузка...</span>
            </div>
          </div>
        </div>
      `);
      
      // Делаем новую вкладку активной
      $('.nav-link').removeClass('active');
      $('.tab-pane').removeClass('show active');
      $(`#${tabId}`).addClass('active');
      $(`#${tabContentId}`).addClass('show active');
      
      // Получаем контрагента из связанного заказа, если есть
      const orderId = data.order_id;
      let counterpartyId = null;
      
      function loadFinanceForm(counterpId) {
        // Загружаем содержимое редактирования финансовой операции
        $.ajax({
          url: '/crm/modules/finances/edit_partial.php',
          data: { 
            id: 0,
            type: 'expense',
            return_id: returnId,
            order_id: orderId || 0,
            amount: data.total_amount || 0,
            counterparty_id: counterpId || 0,
            tab: 1,
            tab_id: tabId,
            content_id: tabContentId,
            based_on: 'return', // Важно! Указываем что РКО создаётся на основе возврата
            description: 'Возврат средств по возврату №' + data.return_number + (orderId ? ' (Заказ №' + orderId + ')' : '')
          },
          success: function(html) {
            $(`#${tabContentId}`).html(html);
            
            // После загрузки содержимого запускаем отслеживание изменений формы
            initFormTracking(tabContentId);
            
            // Сохраняем информацию об открытой вкладке
            openOrderTabs.set(tabContentId, { tabId, returnId, type: 'finance-from-return' });
            
            // Сохраняем состояние вкладок
            saveTabsState();
          },
          error: function(xhr, status, error) {
            $(`#${tabContentId}`).html(`
              <div class="alert alert-danger">
                <h4>Ошибка загрузки формы финансовой операции</h4>
                <p>Ответ сервера: ${xhr.responseText}</p>
              </div>
            `);
          }
        });
      }
      
      // Если есть ID заказа, получаем контрагента из заказа
      if (orderId) {
        // ИСПРАВЛЕНИЕ: Изменен URL с несуществующего get_order_info.php на правильный order_api.php с параметром action
        $.getJSON('/crm/modules/sales/orders/order_api.php', { action: 'get_order_info', id: orderId }, function(orderData) {
          if (orderData.status === 'ok') {
            counterpartyId = orderData.data.customer;
            loadFinanceForm(counterpartyId);
          } else {
            loadFinanceForm(null);
          }
        });
      } else {
        loadFinanceForm(null);
      }
      
      // Обработчик закрытия вкладки
      $(`#${tabId} .btn-close`).on('click', function(e) {
        e.stopPropagation();
        closeModuleTab(tabId, tabContentId);
      });
    } else {
      alert('Ошибка при получении данных возврата: ' + (response.message || 'Неизвестная ошибка'));
    }
  });
}

// Функция для удаления заказа
function deleteOrder(orderId) {
  if (!confirm('Вы уверены, что хотите удалить этот заказ?')) return;
  
  $.get('/crm/modules/sales/orders/delete.php', { id: orderId }, function(response) {
    if (response === 'OK') {
      updateOrderLists();
      // alert('Заказ успешно удален');
    } else {
      alert('Ошибка при удалении: ' + response);
    }
  });
}

// Функция для печати заказа
function printOrder(orderId) {
  // Открываем окно печати
  window.open('/crm/modules/sales/orders/print.php?id=' + orderId, '_blank');
}

// ======== ФУНКЦИИ ДЛЯ УПРАВЛЕНИЯ ВКЛАДКАМИ И СЕССИЕЙ ========

// Функция для проверки наличия несохраненных изменений
function hasUnsavedChanges(tabContentId) {
  const tabContent = document.getElementById(tabContentId);
  if (!tabContent) return false;
  
  // Проверяем, есть ли в tabContent формы с измененными полями
  const forms = tabContent.querySelectorAll('form');
  
  for (let i = 0; i < forms.length; i++) {
    const form = forms[i];
    const formInputs = form.querySelectorAll('input, select, textarea');
    
    for (let j = 0; j < formInputs.length; j++) {
      const input = formInputs[j];
      
      // Проверяем, отличается ли текущее значение от исходного
      if (input.value !== input.defaultValue) {
        return true;
      }
    }
  }
  
  // Проверяем наличие сохраненного состояния формы
  if (globalFormsData.forms[tabContentId]) {
    return true;
  }
  
  return false;
}

// Функция для сохранения состояния вкладок
function saveTabsState() {
  const tabs = [];
  
  // Собираем информацию о всех открытых вкладках
  $('#crm-tabs .nav-item').each(function() {
    const link = $(this).find('.nav-link');
    const contentId = link.attr('href').substring(1); // Убираем # из href
    const tabId = link.attr('id');
    const title = link.text().trim().replace('×', '').trim(); // Убираем символ крестика и лишние пробелы
    
    // Получаем все дата-атрибуты
    const dataAttrs = {};
    const link_data = link.data();
    
    for (const key in link_data) {
      dataAttrs[key] = link_data[key];
    }
    
    const tabInfo = {
      tabId: tabId,
      contentId: contentId,
      title: title,
      isActive: link.hasClass('active'),
      data: dataAttrs
    };
    
    tabs.push(tabInfo);
  });
  
  // Сохраняем в localStorage с привязкой к текущему пользователю
  if (tabs.length > 0) {
    const userId = getUserId();
    if (userId) {
      // Обновляем и сразу отправляем на сервер
      localStorage.setItem('user_tabs_' + userId, JSON.stringify(tabs));
      
      // Отправляем состояние вкладок на сервер для надежного хранения
      $.ajax({
        url: '/crm/save_form_state.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          action: 'save_tabs',
          user_id: userId,
          tabs: tabs
        }),
        success: function(response) {
          console.log('Tabs state saved to server');
        },
        error: function(xhr, status, error) {
          console.error('Failed to save tabs state to server:', error);
        }
      });
    }
  }
}

// Функция для получения ID текущего пользователя
function getUserId() {
  // В этом примере просто извлекаем ID из метатега, который нужно добавить в header.php
  const userIdMeta = document.querySelector('meta[name="user-id"]');
  if (userIdMeta) {
    return userIdMeta.getAttribute('content');
  }
  // В случае отсутствия метатега, пытаемся получить ID пользователя из URL
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('user_id')) {
    return urlParams.get('user_id');
  }
  
  // Пытаемся извлечь из авторизованного куки
  const cookies = document.cookie.split(';');
  for (let i = 0; i < cookies.length; i++) {
    const cookie = cookies[i].trim();
    if (cookie.startsWith('user_id=')) {
      return cookie.substring('user_id='.length, cookie.length);
    }
  }
  
  return null;
}

// Функция для восстановления сессии пользователя
function restoreUserSession() {
  const userId = getUserId();
  if (!userId) {
    console.error('Не удалось получить ID пользователя. Восстановление сессии невозможно.');
    return;
  }
  
  console.log('Восстановление сессии для пользователя:', userId);
  
  // Пытаемся загрузить состояние сессии с сервера
  $.ajax({
    url: '/crm/save_form_state.php',
    type: 'GET',
    data: {
      action: 'restore',
      user_id: userId
    },
    success: function(response) {
      const serverData = typeof response === 'string' ? JSON.parse(response) : response;
      
      if (serverData.status === 'ok' && serverData.data) {
        // Восстанавливаем состояние форм
        if (serverData.data.forms) {
          globalFormsData.forms = serverData.data.forms;
        }
        
        // Восстанавливаем вкладки
        if (serverData.data.tabs && serverData.data.tabs.length > 0) {
          // Показываем диалог подтверждения
          showSessionRestoreDialog(serverData.data.tabs);
          return;
        }
      }
      
      // Если с сервера не получили ничего, пробуем из localStorage
      tryRestoreFromLocalStorage(userId);
    },
    error: function(xhr, status, error) {
      console.error('Ошибка при загрузке состояния с сервера:', error);
      
      // В случае ошибки пробуем восстановить из localStorage
      tryRestoreFromLocalStorage(userId);
    }
  });
}

// Функция для восстановления из localStorage
function tryRestoreFromLocalStorage(userId) {
  const savedTabs = localStorage.getItem('user_tabs_' + userId);
  if (!savedTabs) {
    console.log('В localStorage нет сохраненных вкладок');
    return;
  }
  
  try {
    const tabs = JSON.parse(savedTabs);
    if (tabs.length === 0) {
      console.log('Найдено пустое состояние вкладок');
      return;
    }
    
    // Показываем диалог подтверждения
    showSessionRestoreDialog(tabs);
  } catch (e) {
    console.error('Ошибка при разборе сохраненных вкладок:', e);
  }
}

// Функция для отображения диалога восстановления сессии
function showSessionRestoreDialog(tabs) {
  console.log('Предлагаем диалог восстановления. Количество вкладок:', tabs.length);
  
  // Создаем модальное окно
  const modal = document.createElement('div');
  modal.className = 'modal fade';
  modal.id = 'sessionRestoreModal';
  modal.setAttribute('tabindex', '-1');
  modal.setAttribute('aria-hidden', 'true');
  
  // Создаем список вкладок для показа в диалоге
  let tabsList = '';
  tabs.forEach(function(tab) {
    tabsList += `<li>${tab.title || 'Неизвестная вкладка'}</li>`;
  });
  
  modal.innerHTML = `
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Восстановление сессии</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Обнаружена предыдущая сессия с открытыми вкладками (${tabs.length}).
          </div>
          <p>Открытые вкладки:</p>
          <ul>${tabsList}</ul>
          <p>Хотите продолжить предыдущую сессию или начать новую?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="startNewSessionBtn">Начать новую</button>
          <button type="button" class="btn btn-primary" id="restoreSessionBtn">
            <i class="fas fa-sync me-2"></i>Продолжить предыдущую
          </button>
        </div>
      </div>
    </div>
  `;
  
  document.body.appendChild(modal);
  
  // Инициализируем модальное окно Bootstrap
  const modalInstance = new bootstrap.Modal(modal);
  modalInstance.show();
  
  // Обработчики событий кнопок
  document.getElementById('startNewSessionBtn').addEventListener('click', function() {
    console.log('Пользователь выбрал начать новую сессию');
    
    // Очищаем сохраненные вкладки для текущего пользователя
    const userId = getUserId();
    if (userId) {
      // Очистка данных вкладок
      localStorage.removeItem('user_tabs_' + userId);
      
      // Очистка данных форм - ищем все ключи localStorage и удаляем их
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key && key.startsWith(`form_state_${userId}_`)) {
          localStorage.removeItem(key);
        }
      }
      
      // Очищаем глобальные данные форм
      globalFormsData.forms = {};
      
      // Также отправляем запрос на сервер для очистки данных
      $.ajax({
        url: '/crm/save_form_state.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          action: 'clear',
          user_id: userId
        }),
        success: function(response) {
          console.log('Server session data cleared');
        }
      });
    }
  });
  
  document.getElementById('restoreSessionBtn').addEventListener('click', function() {
    console.log('Пользователь выбрал восстановить предыдущую сессию');
    
    // Восстанавливаем вкладки
    restoreSavedTabs(tabs);
    modalInstance.hide();
  });
}

// Функция для восстановления сохраненных вкладок
function restoreSavedTabs(tabs) {
  console.log('Начинаем восстановление вкладок:', tabs.length);
  
  // Если нет вкладок, выходим
  if (!tabs || tabs.length === 0) return;
  
  // Восстанавливаем каждую вкладку по данным
  tabs.forEach(function(tab) {
    console.log('Восстанавливаем вкладку:', tab);
    
    if (tab.data) {
      // Если есть данные о типе документа и его ID, восстанавливаем соответствующую вкладку
      if (tab.data.documentType) {
        const type = tab.data.documentType;
        
        // Обычные модули
        if (tab.data.module) {
          openModuleTab(tab.data.module);
          return;
        }
        
        // Заказы
        if (type === 'order' && tab.data.orderId) {
          openOrderEditTab(tab.data.orderId);
          return;
        }
        
        // Отгрузки
        if (type === 'shipment' && tab.data.shipmentId) {
          openShipmentEditTab(tab.data.shipmentId);
          return;
        }
        
        // Возвраты
        if (type === 'return' && tab.data.returnId) {
          openReturnEditTab(tab.data.returnId);
          return;
        }
        
        // Финансы
        if (type === 'finance' && tab.data.transactionId !== undefined) {
          openFinanceEditTab(tab.data.transactionId, tab.data.transactionType || 'income');
          return;
        }
        
        // Специальные типы документов на основании других
        if (type === 'shipment-from-order' && tab.data.orderId) {
          createShipmentFromOrder(tab.data.orderId);
          return;
        }
        
        if (type === 'return-from-order' && tab.data.orderId) {
          createReturnFromOrder(tab.data.orderId);
          return;
        }
        
        if (type === 'finance-from-order' && tab.data.orderId) {
          createFinanceFromOrder(tab.data.orderId, tab.data.financeType || 'income');
          return;
        }
        
        if (type === 'finance-from-return' && tab.data.returnId) {
          createRkoFromReturn(tab.data.returnId);
          return;
        }
      }
      // Стандартные модули
      else if (tab.data.module) {
        openModuleTab(tab.data.module);
        return;
      }
    }
    
    // Совместимость со старым форматом
    if (tab.modulePath) {
      openModuleTab(tab.modulePath);
      return;
    }
    
    // Восстанавливаем заказы
    if (tab.orderId !== undefined) {
      openOrderEditTab(tab.orderId);
      return;
    }
    
    // Восстанавливаем отгрузки
    if (tab.shipmentId !== undefined) {
      openShipmentEditTab(tab.shipmentId);
      return;
    }
    
    // Восстанавливаем возвраты
    if (tab.returnId !== undefined) {
      openReturnEditTab(tab.returnId);
      return;
    }
    
    // Восстанавливаем финансовые операции
    if (tab.transactionId !== undefined && tab.transactionType) {
      openFinanceEditTab(tab.transactionId, tab.transactionType);
      return;
    }
    
    // Восстанавливаем специальные типы документов
    if (tab.documentType) {
      switch(tab.documentType) {
        case 'shipment-from-order':
          if (tab.orderId) createShipmentFromOrder(tab.orderId);
          break;
        case 'return-from-order':
          if (tab.orderId) createReturnFromOrder(tab.orderId);
          break;
        case 'finance-from-order':
          if (tab.orderId) createFinanceFromOrder(tab.orderId, tab.financeType || 'income');
          break;
        case 'finance-from-return':
          if (tab.returnId) createRkoFromReturn(tab.returnId);
          break;
      }
    }
    
    // Добавляем возможность восстановления вкладок производственных операций
    if (type === 'production-operation' && tab.data.operationId) {
      openProductionOperationTab(tab.data.operationId);
      return;
    }
  });
  
  console.log('Восстановление вкладок завершено');
}

// ======== НОВЫЕ ФУНКЦИИ ДЛЯ УПРАВЛЕНИЯ ФОРМАМИ ========

// Инициализация отслеживания изменений формы
function initFormTracking(tabContentId) {
  const contentElement = document.getElementById(tabContentId);
  if (!contentElement) return;
  
  console.log(`Инициализация отслеживания формы для: ${tabContentId}`);
  
  // Находим все формы в контейнере вкладки
  const forms = contentElement.querySelectorAll('form');
  const inputs = contentElement.querySelectorAll('input, select, textarea');
  
  // Если нет форм и элементов ввода, просто выходим
  if (forms.length === 0 && inputs.length === 0) return;
  
  // Восстанавливаем состояние формы, если оно было сохранено
  restoreFormState(tabContentId);
  
  // Добавляем обработчики событий для отслеживания изменений
  inputs.forEach(input => {
    input.addEventListener('change', function() {
      saveFormState(tabContentId);
    });
    
    // Для текстовых полей еще отслеживаем ввод, но с задержкой
    if (input.tagName === 'TEXTAREA' || (input.tagName === 'INPUT' && 
        ['text', 'number', 'email', 'tel', 'password', 'date', 'datetime-local'].includes(input.type))) {
      
      // Используем debounce для оптимизации
      let debounceTimer;
      input.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
          saveFormState(tabContentId);
        }, 300); // Задержка 300 мс
      });
    }
  });
  
  // Добавляем обработчик для отслеживания click по кнопкам и другим элементам, которые могут менять состояние формы
  contentElement.addEventListener('click', function(e) {
    // Сохраняем состояние через небольшую задержку, чтобы оно успело измениться после клика
    setTimeout(function() {
      saveFormState(tabContentId);
    }, 100);
  });
}

// Сохранение состояния формы
function saveFormState(tabContentId) {
  const userId = getUserId();
  if (!userId) return;
  
  const contentElement = document.getElementById(tabContentId);
  if (!contentElement) return;
  
  // Получаем тип документа и его ID для сохранения
  const documentType = contentElement.getAttribute('data-document-type');
  const documentId = contentElement.getAttribute('data-id');
  
  // Собираем данные всех элементов ввода
  const formData = {
    tabContentId: tabContentId,
    documentType: documentType,
    documentId: documentId,
    timestamp: new Date().toISOString(),
    values: {}
  };
  
  // Сохраняем все значения полей ввода
  const inputs = contentElement.querySelectorAll('input, select, textarea');
  
  inputs.forEach(input => {
    // Пропускаем кнопки и скрытые поля без имени
    if (input.type === 'button' || input.type === 'submit' || input.type === 'reset' || (!input.id && !input.name)) {
      return;
    }
    
    // Используем id или name как ключ
    const key = input.id || input.name;
    if (!key) return;
    
    // Сохраняем значение в зависимости от типа поля
    if (input.type === 'checkbox' || input.type === 'radio') {
      formData.values[key] = input.checked;
    } else {
      formData.values[key] = input.value;
    }
  });
  
  // Сохраняем для специфичных элементов интерфейса (например, таблицы с гибридными платежами)
  if (contentElement.querySelector('#payment-details-table')) {
    const paymentDetails = [];
    contentElement.querySelectorAll('#payment-details-table tbody tr').forEach(row => {
      const method = row.querySelector('.pd-method')?.value;
      const amount = row.querySelector('.pd-amount')?.value;
      const description = row.querySelector('.pd-description')?.value;
      
      if (method) {
        paymentDetails.push({ method, amount, description });
      }
    });
    formData.values['payment_details'] = paymentDetails;
  }
  
  // Сохраняем в глобальный объект
  globalFormsData.forms[tabContentId] = formData;
  globalFormsData.lastSaveTime = new Date().toLocaleTimeString();
  
  // Сохраняем данные в localStorage
  saveFormDataToLocalStorage(userId, tabContentId, formData);
}

// Функция для сохранения данных формы в localStorage
function saveFormDataToLocalStorage(userId, tabContentId, formData) {
  try {
    const formKey = `form_state_${userId}_${tabContentId}`;
    localStorage.setItem(formKey, JSON.stringify(formData));
    
    // Добавляем ключ формы в список сохраненных форм
    const formsListKey = `form_keys_${userId}`;
    const formsList = JSON.parse(localStorage.getItem(formsListKey) || '[]');
    
    if (!formsList.includes(formKey)) {
      formsList.push(formKey);
      localStorage.setItem(formsListKey, JSON.stringify(formsList));
    }
  } catch (e) {
    console.error('Ошибка при сохранении формы в localStorage:', e);
  }
}

// Восстановление состояния формы
function restoreFormState(tabContentId) {
  const userId = getUserId();
  if (!userId) return;
  
  // Сначала проверяем глобальный объект
  let formData = globalFormsData.forms[tabContentId];
  
  // Если нет в глобальном объекте, пытаемся загрузить из localStorage
  if (!formData) {
    const formKey = `form_state_${userId}_${tabContentId}`;
    const savedData = localStorage.getItem(formKey);
    
    if (savedData) {
      try {
        formData = JSON.parse(savedData);
        // Добавляем в глобальный объект для дальнейшего использования
        globalFormsData.forms[tabContentId] = formData;
      } catch (e) {
        console.error('Ошибка при восстановлении состояния формы из localStorage:', e);
        return;
      }
    } else {
      return; // Если нет сохраненных данных, выходим
    }
  }
  
  const contentElement = document.getElementById(tabContentId);
  if (!contentElement || !formData.values) return;
  
  try {
    // Восстанавливаем значения элементов ввода
    for (const key in formData.values) {
      if (key === 'payment_details') continue; // Обработаем отдельно
      
      const input = contentElement.querySelector(`#${key}`) || contentElement.querySelector(`[name="${key}"]`);
      if (!input) continue;
      
      if (input.type === 'checkbox' || input.type === 'radio') {
        input.checked = formData.values[key];
      } else {
        input.value = formData.values[key];
      }
    }
    
    // Восстанавливаем специфичные элементы интерфейса
    if (formData.values.payment_details && contentElement.querySelector('#payment-details-table')) {
      // Очищаем существующие строки
      const tbody = contentElement.querySelector('#payment-details-table tbody');
      tbody.innerHTML = '';
      
      // Добавляем сохраненные строки
      formData.values.payment_details.forEach(detail => {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>
            <select class="form-select pd-method">
              <option value="cash" ${detail.method === 'cash' ? 'selected' : ''}>Наличные</option>
              <option value="card" ${detail.method === 'card' ? 'selected' : ''}>Эквайринг</option>
              <option value="transfer_rncb" ${detail.method === 'transfer_rncb' ? 'selected' : ''}>Перевод (РНКБ)</option>
              <option value="transfer_other" ${detail.method === 'transfer_other' ? 'selected' : ''}>Перевод (Другой банк)</option>
              <option value="bank_account" ${detail.method === 'bank_account' ? 'selected' : ''}>Банковский счёт</option>
            </select>
          </td>
          <td><input type="number" step="0.01" class="form-control pd-amount" value="${detail.amount || 0}"></td>
          <td><input type="text" class="form-control pd-description" value="${detail.description || ''}"></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove();calcTotalHybrid();">×</button></td>
        `;
        tbody.appendChild(row);
      });
      
      // Вызываем перерасчет итоговой суммы, если функция доступна
      if (typeof window.calcTotalHybrid === 'function') {
        window.calcTotalHybrid();
      } else if (typeof calcTotalHybrid === 'function') {
        calcTotalHybrid();
      }
    }
    
    // Если есть функция для проверки категории расходов - вызываем её
    if (typeof window.checkExpenseCategoryOther === 'function') {
      window.checkExpenseCategoryOther();
    } else if (typeof checkExpenseCategoryOther === 'function') {
      checkExpenseCategoryOther();
    }
    
    // Вызываем событие change для запуска обработчиков
    contentElement.querySelectorAll('input, select, textarea').forEach(input => {
      const event = new Event('change', { bubbles: true });
      input.dispatchEvent(event);
    });
    
    console.log(`Форма ${tabContentId} успешно восстановлена`);
  } catch (e) {
    console.error('Ошибка при восстановлении состояния формы:', e);
  }
}

// Восстановление состояния формы для динамической вкладки по типу
function restoreTabFormState(tabContentId, documentType, documentId, options = {}) {
  // Пытаемся найти сохраненную форму по типу документа и его ID
  const userId = getUserId();
  if (!userId) return;
  
  console.log(`Поиск сохраненной формы для: ${documentType}/${documentId}`);
  
  // Ищем подходящие формы в глобальном объекте
  for (const key in globalFormsData.forms) {
    const formData = globalFormsData.forms[key];
    if (formData.documentType === documentType && formData.documentId == documentId) {
      globalFormsData.forms[tabContentId] = formData;
      delete globalFormsData.forms[key]; // Удаляем из старого ключа
      console.log(`Найдена форма в глобальном объекте: ${key} -> ${tabContentId}`);
      return;
    }
  }
  
  // Если в глобальном объекте не нашли, ищем в localStorage
  const formsListKey = `form_keys_${userId}`;
  const formsList = JSON.parse(localStorage.getItem(formsListKey) || '[]');
  
  for (const formKey of formsList) {
    try {
      const savedData = localStorage.getItem(formKey);
      if (!savedData) continue;
      
      const formData = JSON.parse(savedData);
      
      // Проверяем, соответствует ли эта форма нашему документу
      if (formData.documentType === documentType && formData.documentId == documentId) {
        // Копируем данные в новый ключ
        const newFormKey = `form_state_${userId}_${tabContentId}`;
        localStorage.setItem(newFormKey, savedData);
        
        // Добавляем новый ключ в список, если его там нет
        if (!formsList.includes(newFormKey)) {
          formsList.push(newFormKey);
          localStorage.setItem(formsListKey, JSON.stringify(formsList));
        }
        
        // Удаляем старый ключ из localStorage и списка
        localStorage.removeItem(formKey);
        const index = formsList.indexOf(formKey);
        if (index > -1) {
          formsList.splice(index, 1);
          localStorage.setItem(formsListKey, JSON.stringify(formsList));
        }
        
        console.log(`Найдена форма в localStorage: ${formKey} -> ${newFormKey}`);
        return;
      }
    } catch (e) {
      console.error('Ошибка при поиске формы в localStorage:', e);
    }
  }
  
  console.log(`Не найдено сохраненной формы для: ${documentType}/${documentId}`);
}

// Удаление сохраненного состояния формы
function removeTabFormState(tabContentId) {
  const userId = getUserId();
  if (!userId) return;
  
  // Удаляем из глобального объекта
  if (globalFormsData.forms[tabContentId]) {
    delete globalFormsData.forms[tabContentId];
  }
  
  // Удаляем из localStorage
  const formKey = `form_state_${userId}_${tabContentId}`;
  localStorage.removeItem(formKey);
  
  // Удаляем из списка сохраненных форм
  const formsListKey = `form_keys_${userId}`;
  const formsList = JSON.parse(localStorage.getItem(formsListKey) || '[]');
  const index = formsList.indexOf(formKey);
  if (index > -1) {
    formsList.splice(index, 1);
    localStorage.setItem(formsListKey, JSON.stringify(formsList));
  }
}

// Автосохранение всех форм на открытых вкладках
function autoSaveAllForms() {
  // Сохраняем состояние всех открытых вкладок
  $('.tab-pane').each(function() {
    const tabContentId = $(this).attr('id');
    if (tabContentId) {
      saveFormState(tabContentId);
    }
  });
  
  // Также обновляем состояние вкладок
  saveTabsState();
  
  console.log(`[${globalFormsData.lastSaveTime}] Автосохранение выполнено`);
}

// Синхронизация данных форм с сервером
function syncFormsWithServer(sync = false) {
  const userId = getUserId();
  if (!userId) return;
  
  // Копия данных для отправки
  const syncData = {
    forms: JSON.parse(JSON.stringify(globalFormsData.forms)),
    tabs: [],
    timestamp: new Date().toISOString()
  };
  
  // Добавляем информацию о текущих вкладках
  $('#crm-tabs .nav-item').each(function() {
    const link = $(this).find('.nav-link');
    const contentId = link.attr('href').substring(1);
    const tabId = link.attr('id');
    
    // Собираем все дата-атрибуты
    const dataAttrs = {};
    $.each(link.data(), function(key, value) {
      dataAttrs[key] = value;
    });
    
    syncData.tabs.push({
      tabId: tabId,
      contentId: contentId,
      title: link.text().trim().replace('×', '').trim(),
      data: dataAttrs,
      isActive: link.hasClass('active')
    });
  });
  
  // Отправляем на сервер
  $.ajax({
    url: '/crm/save_form_state.php',
    type: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({
      action: 'sync',
      user_id: userId,
      data: syncData
    }),
    async: !sync, // Если sync=true, то запрос синхронный
    success: function(response) {
      console.log('Синхронизация с сервером выполнена успешно');
    },
    error: function(xhr, status, error) {
      console.error('Ошибка синхронизации с сервером:', error);
    }
  });
}

// Функция для открытия редактирования операции производства в новой вкладке
function openProductionOperationTab(operationId, operationNumber = null) {
  console.log('Opening production operation tab:', operationId, operationNumber);
  const uniqueSuffix = Date.now(); // добавляем уникальный суффикс для избежания конфликтов
  const tabId = 'tab-production-operation-' + operationId + '-' + uniqueSuffix;
  const tabContentId = 'content-production-operation-' + operationId + '-' + uniqueSuffix;
  
  // Определяем заголовок вкладки
  let title = 'Новая операция производства';
  
  if (operationId > 0) {
    // Если передан номер операции, используем его
    if (operationNumber) {
      title = `Операция ${operationNumber}`;
    } else {
      title = `Операция #${operationId}`;
    }
  }

  // Создаем новую вкладку
  let navItem = $(`
    <li class="nav-item">
      <a class="nav-link" id="${tabId}" data-bs-toggle="tab" href="#${tabContentId}" data-operation-id="${operationId}" data-document-type="production-operation">
        ${title}
        <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
      </a>
    </li>
  `);

  navItem.find('.btn-close').on('click', function(e) {
    e.stopPropagation();
    closeModuleTab(tabId, tabContentId);
  });

  let tabPane = $(`
    <div class="tab-pane fade" id="${tabContentId}" data-document-type="production-operation" data-id="${operationId}">
      <p>Загрузка формы редактирования...</p>
    </div>
  `);

  $('#crm-tabs').append(navItem);
  $('#crm-tab-content').append(tabPane);

  $('#' + tabId).tab('show');

  // Загружаем содержимое формы редактирования
  $.ajax({
    url: '/crm/modules/production/operations/edit_partial.php',
    data: { 
      id: operationId, 
      tab: 1,
      tab_id: tabId,
      content_id: tabContentId
    },
    success: function(html) {
      tabPane.html(html).addClass('fade-in');
      
      // После загрузки содержимого запускаем отслеживание изменений формы
      initFormTracking(tabContentId);
      
      // Восстанавливаем сохраненное состояние формы, если есть
      restoreTabFormState(tabContentId, 'production-operation', operationId);
      
      // Сохраняем состояние вкладок
      saveTabsState();
    },
    error: function(xhr) {
      if (xhr.status === 404) {
        tabPane.html('<div class="text-danger">Файл не найден (404). Проверьте структуру папок.</div>');
      } else if (xhr.status === 500) {
        tabPane.html('<div class="text-danger">Ошибка 500 на сервере. Проверьте PHP-код.</div>');
      } else {
        tabPane.html('<div class="text-danger">Ошибка загрузки ('+xhr.status+')</div>');
      }
    }
  });
  
  return { tabId, tabContentId };
}
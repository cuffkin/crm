/**
 * Современный селектор товаров для CRM системы
 * Версия: 2.0
 * Автор: AI Assistant
 */

class ProductSelector {
    constructor(container, options = {}) {
        this.container = typeof container === 'string' ? document.querySelector(container) : container;
        this.options = {
            context: 'sale', // sale, purchase, production
            placeholder: 'Введите название товара...',
            showRecent: true,
            showModal: true,
            onSelect: null,
            onClear: null,
            allowQuantity: true,
            allowPrice: true,
            defaultQuantity: 1,
            ...options
        };
        
        this.selectedProduct = null;
        this.isOpen = false;
        this.searchTimeout = null;
        this.modal = null;
        this.currentModalId = null;
        
        this.init();
    }
    
    init() {
        this.render();
        this.bindEvents();
        this.loadRecentProducts();
    }
    
    render() {
        const selectorId = 'product-selector-' + Math.random().toString(36).substr(2, 9);
        const dropdownId = 'dropdown-' + selectorId;
        
        this.container.innerHTML = `
            <div class="product-selector" data-selector-id="${selectorId}">
                <div class="product-selector-input">
                    <input type="text" 
                           class="form-control product-search" 
                           placeholder="${this.options.placeholder}"
                           autocomplete="off"
                           data-bs-toggle="dropdown" 
                           data-bs-target="#${dropdownId}">
                    <div class="product-selector-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary clear-product" title="Очистить" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                        ${this.options.showModal ? `
                        <button type="button" class="btn btn-sm btn-outline-primary show-modal" title="Показать всё">
                            <i class="fas fa-th-list"></i>
                        </button>
                        ` : ''}
                    </div>
                </div>
                
                <div class="dropdown-menu product-dropdown" id="${dropdownId}">
                    <div class="dropdown-content">
                        <div class="recent-products" style="display: none;">
                            <h6 class="dropdown-header">Недавно выбранные</h6>
                            <div class="recent-products-list"></div>
                            <div class="dropdown-divider"></div>
                        </div>
                        
                        <div class="search-results">
                            <div class="no-results text-muted text-center py-3" style="display: none;">
                                <i class="fas fa-search mb-2"></i><br>
                                Начните вводить название товара
                            </div>
                            <div class="search-results-list"></div>
                        </div>
                        
                        <div class="dropdown-footer">
                            ${this.options.showModal ? `
                            <button type="button" class="btn btn-sm btn-link show-modal w-100">
                                <i class="fas fa-external-link-alt me-1"></i>
                                Показать всё
                            </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
                
                <div class="selected-product-info" style="display: none;"></div>
            </div>
        `;
        
        this.elements = {
            container: this.container.querySelector('.product-selector'),
            input: this.container.querySelector('.product-search'),
            dropdown: this.container.querySelector('.product-dropdown'),
            recentSection: this.container.querySelector('.recent-products'),
            recentList: this.container.querySelector('.recent-products-list'),
            searchResults: this.container.querySelector('.search-results-list'),
            noResults: this.container.querySelector('.no-results'),
            clearBtn: this.container.querySelector('.clear-product'),
            modalBtn: this.container.querySelector('.show-modal'),
            selectedInfo: this.container.querySelector('.selected-product-info')
        };
    }
    
    renderModal(modalId) {
        return `
            <div class="modal fade product-selector-modal" id="${modalId}" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Выбор товара</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Категории</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="categories-tree"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <input type="text" class="form-control modal-search" placeholder="Поиск товаров...">
                                                </div>
                                                <div class="col-auto">
                                                    <span class="text-muted products-count"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="products-table-container" style="max-height: 400px; overflow-y: auto;">
                                                <table class="table table-hover mb-0">
                                                    <thead class="table-light sticky-top">
                                                        <tr>
                                                            <th>Товар</th>
                                                            <th>Артикул</th>
                                                            <th>Цена</th>
                                                            <th>Остаток</th>
                                                            <th>Ед.изм.</th>
                                                            <th width="100">Кол-во</th>
                                                            <th width="80">Действие</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="modal-products-list">
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="modal-loading text-center py-4" style="display: none;">
                                                <div class="spinner-border" role="status">
                                                    <span class="visually-hidden">Загрузка...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    bindEvents() {
        // Поиск в основном поле
        this.elements.input.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            this.handleSearch(query);
        });
        
        // Фокус на поле - показать недавние
        this.elements.input.addEventListener('focus', () => {
            this.showDropdown();
        });
        
        // Клик вне селектора - скрыть dropdown
        document.addEventListener('click', (e) => {
            if (!this.elements.container.contains(e.target)) {
                this.hideDropdown();
            }
        });
        
        // Кнопка очистки
        this.elements.clearBtn?.addEventListener('click', () => {
            this.clearSelection();
        });
        
        // Кнопки модального окна (все варианты)
        this.container.querySelectorAll('.show-modal').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('🔧 Клик по кнопке "Показать всё"');
                this.showModal();
            });
        });
    }
    
    bindModalEvents(modalElement) {
        const modalSearch = modalElement.querySelector('.modal-search');
        const categoriesTree = modalElement.querySelector('.categories-tree');
        const productsList = modalElement.querySelector('.modal-products-list');
        
        // Поиск в модальном окне
        modalSearch.addEventListener('input', (e) => {
            this.handleModalSearch(e.target.value.trim());
        });
        
        // Загрузка данных при открытии модального окна
        modalElement.addEventListener('shown.bs.modal', () => {
            this.loadModalData();
        });
    }
    
    handleSearch(query) {
        clearTimeout(this.searchTimeout);
        
        if (query.length === 0) {
            this.showRecentProducts();
            return;
        }
        
        if (query.length < 2) {
            this.elements.noResults.style.display = 'block';
            this.elements.searchResults.innerHTML = '';
            return;
        }
        
        this.searchTimeout = setTimeout(() => {
            this.searchProducts(query);
        }, 300);
    }
    
    async searchProducts(query) {
        try {
            const response = await fetch(`/crm/api/product_selector.php?action=search&q=${encodeURIComponent(query)}&context=${this.options.context}&limit=10`);
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            this.renderSearchResults(data.products);
        } catch (error) {
            console.error('Ошибка поиска товаров:', error);
            this.showError('Ошибка поиска товаров');
        }
    }
    
    async loadRecentProducts() {
        if (!this.options.showRecent) return;
        
        try {
            const response = await fetch(`/crm/api/product_selector.php?action=recent&context=${this.options.context}&limit=5`);
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            this.renderRecentProducts(data.products);
        } catch (error) {
            console.error('Ошибка загрузки недавних товаров:', error);
        }
    }
    
    renderSearchResults(products) {
        this.elements.noResults.style.display = 'none';
        this.elements.recentSection.style.display = 'none';
        
        if (products.length === 0) {
            this.elements.searchResults.innerHTML = '<div class="dropdown-item text-muted">Товары не найдены</div>';
            return;
        }
        
        this.elements.searchResults.innerHTML = products.map(product => `
            <div class="dropdown-item product-item" data-product-id="${product.id}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="fw-bold">${this.escapeHtml(product.name)}</div>
                        <div class="text-muted small">
                            ${product.sku ? `Артикул: ${this.escapeHtml(product.sku)} • ` : ''}
                            ${product.category.name ? `${this.escapeHtml(product.category.name)} • ` : ''}
                            ${product.unit ? this.escapeHtml(product.unit) : 'шт'}
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold">${this.formatPrice(product.price)} ₽</div>
                        <div class="text-muted small">Остаток: ${product.stock}</div>
                    </div>
                </div>
            </div>
        `).join('');
        
        // Привязываем события клика
        this.elements.searchResults.querySelectorAll('.product-item').forEach(item => {
            item.addEventListener('click', () => {
                const productId = parseInt(item.dataset.productId);
                const product = products.find(p => p.id === productId);
                this.selectProduct(product);
            });
        });
    }
    
    renderRecentProducts(products) {
        if (products.length === 0) {
            this.elements.recentSection.style.display = 'none';
            return;
        }
        
        this.elements.recentSection.style.display = 'block';
        this.elements.recentList.innerHTML = products.map(product => `
            <div class="dropdown-item product-item" data-product-id="${product.id}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold">${this.escapeHtml(product.name)}</div>
                        <div class="text-muted small">${product.sku ? this.escapeHtml(product.sku) : ''}</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold">${this.formatPrice(product.price)} ₽</div>
                    </div>
                </div>
            </div>
        `).join('');
        
        // Привязываем события клика
        this.elements.recentList.querySelectorAll('.product-item').forEach(item => {
            item.addEventListener('click', () => {
                const productId = parseInt(item.dataset.productId);
                const product = products.find(p => p.id === productId);
                this.selectProduct(product);
            });
        });
    }
    
    async selectProduct(product) {
        this.selectedProduct = product;
        this.elements.input.value = product.name;
        this.hideDropdown();
        
        // Показываем кнопку очистки
        this.elements.clearBtn.style.display = 'block';
        
        // Сохраняем в недавние товары
        await this.saveToRecent(product.id);
        
        // Вызываем callback
        if (this.options.onSelect) {
            this.options.onSelect(product);
        }
    }
    
    showSelectedProductInfo(product) {
        this.elements.selectedInfo.innerHTML = `
            <div class="mt-2 p-2 bg-light rounded">
                <div class="row align-items-center">
                    <div class="col">
                        <small class="text-muted">
                            ${product.sku ? `Артикул: ${this.escapeHtml(product.sku)} • ` : ''}
                            Остаток: ${product.stock} ${product.unit || 'шт'} • 
                            Цена: ${this.formatPrice(product.price)} ₽
                        </small>
                    </div>
                </div>
            </div>
        `;
        this.elements.selectedInfo.style.display = 'block';
        this.elements.selectedInfo.classList.add('show');
    }
    
    clearSelection() {
        this.selectedProduct = null;
        this.elements.input.value = '';
        this.elements.selectedInfo.style.display = 'none';
        this.elements.selectedInfo.classList.remove('show');
        this.elements.clearBtn.style.display = 'none';
        
        if (this.options.onClear) {
            this.options.onClear();
        }
    }
    
    async saveToRecent(productId) {
        try {
            await fetch('/crm/api/product_selector.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=save_recent&product_id=${productId}&context=${this.options.context}`
            });
        } catch (error) {
            console.error('Ошибка сохранения в недавние:', error);
        }
    }
    
    showDropdown() {
        this.isOpen = true;
        this.elements.dropdown.classList.add('show');
        this.showRecentProducts();
    }
    
    hideDropdown() {
        this.isOpen = false;
        this.elements.dropdown.classList.remove('show');
    }
    
    showRecentProducts() {
        this.elements.searchResults.innerHTML = '';
        if (this.elements.recentList.children.length > 0) {
            this.elements.recentSection.style.display = 'block';
            this.elements.noResults.style.display = 'none';
        } else {
            this.elements.recentSection.style.display = 'none';
            this.elements.noResults.style.display = 'block';
        }
    }
    
    showModal() {
        console.log('🔧 Метод showModal() вызван');
        
        if (!this.modal) {
            console.log('🔧 Создаем новое модальное окно...');
            
            // Создаем уникальный ID для модального окна
            const modalId = `product-modal-${Date.now()}`;
            this.currentModalId = modalId; // Сохраняем ID для поиска
            
            const modalHtml = this.renderModal(modalId);
            
            // Добавляем HTML в DOM
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Ищем созданный элемент
            const modalElement = document.getElementById(modalId);
            if (!modalElement) {
                console.error('❌ Не удалось создать модальное окно в DOM');
                return;
            }
            
            console.log('✅ Модальное окно добавлено в DOM:', modalElement);
            
            // Инициализируем Bootstrap modal
            try {
                this.modal = new bootstrap.Modal(modalElement, {
                    backdrop: 'static',
                    keyboard: true
                });
                console.log('✅ Bootstrap Modal инициализирован');
            } catch (error) {
                console.error('❌ Ошибка инициализации Bootstrap Modal:', error);
                return;
            }
            
            // Привязываем события модального окна
            this.bindModalEvents(modalElement);
            
            // Удаляем модальное окно из DOM при закрытии
            modalElement.addEventListener('hidden.bs.modal', () => {
                console.log('🗑️ Удаляем модальное окно из DOM');
                modalElement.remove();
                this.modal = null;
                this.currentModalId = null;
            });
        }
        
        console.log('📋 Показываем модальное окно...');
        this.modal.show();
    }
    
    async loadModalData() {
        console.log('📊 Загружаем данные для модального окна...');
        
        // Ищем модальное окно по сохраненному ID
        const modalElement = this.findModalElement();
        if (!modalElement) {
            console.error('❌ Модальное окно не найдено в DOM, ID:', this.currentModalId);
            return;
        }
        
        console.log('✅ Модальное окно найдено, загружаем категории...');
        
        try {
            const response = await fetch(`/crm/api/product_selector.php?action=categories`);
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            console.log('✅ Категории загружены:', data.categories?.length || 0);
            this.renderModalCategories(data.categories, modalElement);
            this.loadModalProducts(); // Загружаем товары
        } catch (error) {
            console.error('❌ Ошибка загрузки данных модального окна:', error);
            this.showModalError('Ошибка загрузки данных: ' + error.message);
        }
    }
    
    findModalElement() {
        // Способ 1: По сохраненному ID
        if (this.currentModalId) {
            const modal = document.getElementById(this.currentModalId);
            if (modal) {
                console.log('✅ Модальное окно найдено по ID:', this.currentModalId);
                return modal;
            }
        }
        
        // Способ 2: По классу последнего созданного
        const modals = document.querySelectorAll('.product-selector-modal');
        if (modals.length > 0) {
            const lastModal = modals[modals.length - 1];
            console.log('✅ Модальное окно найдено по классу (последнее)');
            return lastModal;
        }
        
        // Способ 3: По Bootstrap модальному экземпляру
        if (this.modal && this.modal._element) {
            console.log('✅ Модальное окно найдено через Bootstrap экземпляр');
            return this.modal._element;
        }
        
        console.error('❌ Модальное окно не найдено ни одним способом');
        return null;
    }
    
    renderModalCategories(categories, modalElement = null) {
        console.log('🏗️ Рендерим категории в модальном окне');
        
        if (!modalElement) {
            modalElement = this.findModalElement();
        }
        
        if (!modalElement) {
            console.error('❌ Не удалось найти модальное окно для рендера категорий');
            return;
        }
        
        const categoriesTree = modalElement.querySelector('.categories-tree');
        if (!categoriesTree) {
            console.error('❌ Не найден элемент .categories-tree в модальном окне');
            console.log('🔍 Доступные элементы в модальном окне:', modalElement.innerHTML.substring(0, 500));
            return;
        }
        
        if (!categories || !Array.isArray(categories)) {
            console.error('❌ Категории не переданы или имеют неверный формат:', categories);
            categoriesTree.innerHTML = '<div class="text-muted p-3">Нет категорий для отображения</div>';
            return;
        }
        
        console.log('✅ Обрабатываем', categories.length, 'категорий');
        
        // Функция для рендеринга дерева категорий
        function renderCategoryTree(categories, level = 0) {
            if (!categories || categories.length === 0) return '';
            
            let html = '<ul>';
            categories.forEach(category => {
                const hasChildren = category.children && category.children.length > 0;
                const statusClass = category.status !== 'active' ? 'inactive' : '';
                const childrenClass = hasChildren ? 'has-children' : '';
                
                html += `
                    <li data-category-id="${category.id}" 
                        class="${statusClass} ${childrenClass}"
                        data-level="${level}">
                        <span class="category-name">${escapeHtml(category.name)}</span>
                        <span class="category-count">${category.products_count}</span>
                `;
                
                if (hasChildren) {
                    html += renderCategoryTree(category.children, level + 1);
                }
                
                html += '</li>';
            });
            html += '</ul>';
            return html;
        }
        
        // Функция экранирования HTML (локальная версия)
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        try {
            // Рендерим дерево
            const treeHtml = renderCategoryTree(categories);
            categoriesTree.innerHTML = treeHtml;
            
            // Привязываем события клика по категориям
            categoriesTree.addEventListener('click', (e) => {
                const target = e.target.closest('li[data-category-id]');
                if (!target) return;
                
                e.stopPropagation();
                
                const categoryId = parseInt(target.dataset.categoryId);
                const isExpanded = target.classList.contains('expanded');
                const hasChildren = target.classList.contains('has-children');
                
                // Обработка раскрытия/скрытия подкategorий
                if (hasChildren) {
                    if (isExpanded) {
                        target.classList.remove('expanded');
                    } else {
                        target.classList.add('expanded');
                    }
                }
                
                // Загружаем товары по категории
                this.loadModalProducts(categoryId);
                
                // Подсвечиваем выбранную категорию
                categoriesTree.querySelectorAll('li').forEach(li => li.classList.remove('active'));
                target.classList.add('active');
                
                console.log('✅ Выбрана категория:', categoryId, target.querySelector('.category-name')?.textContent);
            });
            
            // Добавляем кнопку "Все товары"
            const totalProductsCount = categories.reduce((sum, cat) => sum + this.countTotalProducts(cat), 0);
            const allProductsItem = document.createElement('li');
            allProductsItem.innerHTML = `
                <span class="category-name"><strong>Все товары</strong></span>
                <span class="category-count">${totalProductsCount}</span>
            `;
            allProductsItem.style.borderBottom = '1px solid #dee2e6';
            allProductsItem.style.marginBottom = '8px';
            allProductsItem.style.paddingBottom = '8px';
            allProductsItem.addEventListener('click', () => {
                this.loadModalProducts(null); // null = все товары
                categoriesTree.querySelectorAll('li').forEach(li => li.classList.remove('active'));
                allProductsItem.classList.add('active');
                console.log('✅ Выбраны все товары');
            });
            
            // Вставляем "Все товары" в начало
            const firstUl = categoriesTree.querySelector('ul');
            if (firstUl) {
                firstUl.insertBefore(allProductsItem, firstUl.firstChild);
            }
            
            console.log('✅ Иерархическое дерево категорий отрендерено:', categories.length);
            
        } catch (error) {
            console.error('❌ Ошибка рендеринга категорий:', error);
            categoriesTree.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Ошибка загрузки категорий
                </div>
            `;
        }
    }
    
    // Вспомогательная функция для подсчета общего количества товаров
    countTotalProducts(category) {
        let total = category.products_count || 0;
        if (category.children) {
            category.children.forEach(child => {
                total += this.countTotalProducts(child);
            });
        }
        return total;
    }
    
    async loadModalProducts(categoryId = null) {
        const modalElement = this.findModalElement();
        if (!modalElement) {
            console.error('❌ Модальное окно не найдено для загрузки товаров');
            return;
        }
        
        const productsList = modalElement.querySelector('.modal-products-list');
        const productsCount = modalElement.querySelector('.products-count');
        const loading = modalElement.querySelector('.modal-loading');
        
        if (!productsList || !productsCount || !loading) {
            console.error('❌ Не найдены необходимые элементы в модальном окне');
            return;
        }
        
        try {
            loading.style.display = 'block';
            productsList.innerHTML = '';
            
            let url = `/crm/api/product_selector.php?action=search&context=${this.options.context}&limit=50`;
            if (categoryId) {
                url = `/crm/api/product_selector.php?action=category_products&category_id=${categoryId}&context=${this.options.context}&limit=50`;
            }
            
            console.log('🔍 Загружаем товары:', url);
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            const products = data.products || [];
            productsCount.textContent = `Найдено: ${products.length} товаров`;
            console.log('✅ Товары загружены:', products.length);
            
            productsList.innerHTML = products.map(product => `
                <tr>
                    <td>
                        <div class="fw-bold">${this.escapeHtml(product.name)}</div>
                        <div class="text-muted small">${product.description || ''}</div>
                    </td>
                    <td>${product.sku || ''}</td>
                    <td>${this.formatPrice(product.price)} ₽</td>
                    <td>${product.stock}</td>
                    <td>${product.unit || 'шт'}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm" 
                               value="1" min="1" max="${product.stock}" 
                               data-product-id="${product.id}">
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary select-product-btn" 
                                data-product-id="${product.id}">
                            Выбрать
                        </button>
                    </td>
                </tr>
            `).join('');
            
            // Привязываем события кнопок выбора
            productsList.querySelectorAll('.select-product-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const productId = parseInt(btn.dataset.productId);
                    const product = products.find(p => p.id === productId);
                    console.log('✅ Товар выбран из модального окна:', product);
                    this.selectProduct(product);
                    this.modal.hide();
                });
            });
            
        } catch (error) {
            console.error('❌ Ошибка загрузки товаров для модального окна:', error);
            productsList.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Ошибка загрузки товаров</td></tr>';
        } finally {
            loading.style.display = 'none';
        }
    }
    
    async handleModalSearch(query) {
        if (query.length < 2) {
            this.loadModalProducts();
            return;
        }
        
        const modalElement = this.findModalElement();
        if (!modalElement) {
            console.error('❌ Модальное окно не найдено для поиска');
            return;
        }
        
        const productsList = modalElement.querySelector('.modal-products-list');
        const productsCount = modalElement.querySelector('.products-count');
        const loading = modalElement.querySelector('.modal-loading');
        
        if (!productsList || !productsCount || !loading) {
            console.error('❌ Не найдены необходимые элементы для поиска');
            return;
        }
        
        try {
            loading.style.display = 'block';
            productsList.innerHTML = '';
            
            console.log('🔍 Поиск в модальном окне:', query);
            const response = await fetch(`/crm/api/product_selector.php?action=search&q=${encodeURIComponent(query)}&context=${this.options.context}&limit=50`);
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            const products = data.products || [];
            productsCount.textContent = `Найдено: ${products.length} товаров`;
            console.log('✅ Результаты поиска:', products.length);
            
            productsList.innerHTML = products.map(product => `
                <tr>
                    <td>
                        <div class="fw-bold">${this.escapeHtml(product.name)}</div>
                        <div class="text-muted small">${product.description || ''}</div>
                    </td>
                    <td>${product.sku || ''}</td>
                    <td>${this.formatPrice(product.price)} ₽</td>
                    <td>${product.stock}</td>
                    <td>${product.unit || 'шт'}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm" 
                               value="1" min="1" max="${product.stock}" 
                               data-product-id="${product.id}">
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary select-product-btn" 
                                data-product-id="${product.id}">
                            Выбрать
                        </button>
                    </td>
                </tr>
            `).join('');
            
            // Привязываем события кнопок выбора
            productsList.querySelectorAll('.select-product-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const productId = parseInt(btn.dataset.productId);
                    const product = products.find(p => p.id === productId);
                    console.log('✅ Товар выбран из поиска:', product);
                    this.selectProduct(product);
                    this.modal.hide();
                });
            });
            
        } catch (error) {
            console.error('❌ Ошибка поиска в модальном окне:', error);
            productsList.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Ошибка поиска товаров</td></tr>';
        } finally {
            loading.style.display = 'none';
        }
    }
    
    showError(message) {
        this.elements.searchResults.innerHTML = `<div class="dropdown-item text-danger">${message}</div>`;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatPrice(price) {
        return parseFloat(price).toLocaleString('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Публичные методы API
    getSelectedProduct() {
        return this.selectedProduct;
    }
    
    setProduct(product) {
        this.selectProduct(product);
    }
    
    clear() {
        this.clearSelection();
    }
    
    enable() {
        this.elements.input.disabled = false;
        this.elements.container.classList.remove('disabled');
    }
    
    disable() {
        this.elements.input.disabled = true;
        this.elements.container.classList.add('disabled');
    }
    
    showModalError(message) {
        const modalElement = this.findModalElement();
        if (!modalElement) return;
        
        const modalBody = modalElement.querySelector('.modal-body');
        if (modalBody) {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                </div>
            `;
        }
    }
}

// Глобальная функция для создания селектора
window.createProductSelector = function(container, options = {}) {
    return new ProductSelector(container, options);
};

// Экспорт для модулей
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ProductSelector;
} 
/**
 * Стабильный селектор товаров для CRM системы
 * Версия: Stable (восстановлена)
 * Описание: Простая рабочая версия без сложных Bootstrap переделок
 */

class ProductSelector {
    constructor(container, options = {}) {
        this.container = typeof container === 'string' ? document.querySelector(container) : container;
        if (!this.container) {
            console.error('Контейнер для селектора не найден:', container);
            return;
        }
        
        this.options = {
            placeholder: 'Введите название или артикул...',
            showRecent: true,
            showModal: true,
            onSelect: null,
            onClear: null,
            disabled: false,
            initialProductId: null,
            onShowAll: null,
            ...options
        };

        this.selectedProduct = null;
        this.searchTimeout = null;
        
        this.init();
        
        if (this.options.initialProductId) {
            this.setProductById(this.options.initialProductId);
        }
        
        if (this.options.disabled) {
            this.disable();
        }
    }

    init() {
        this.render();
        this.bindEvents();
        this.loadRecentProducts();
    }

    render() {
        const selectorId = 'product-selector-' + Math.random().toString(36).substr(2, 9);
        
        // Простая структура без сложных Bootstrap dropdown переделок
        this.container.innerHTML = `
            <div class="product-selector" data-selector-id="${selectorId}">
                <div class="product-selector-input-group">
                    <input type="text" 
                           class="form-control product-search" 
                           placeholder="${this.options.placeholder}"
                           autocomplete="off">
                    <div class="product-selector-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary clear-product" title="Очистить" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                        ${this.options.showModal ? `
                        <button type="button" class="btn btn-sm btn-outline-primary show-modal-btn" title="Показать всё">
                            <i class="fas fa-th-list"></i>
                        </button>
                        ` : ''}
                    </div>
                </div>
                
                <div class="product-dropdown" style="display: none;">
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
                    
                    ${this.options.showModal ? `
                    <div class="dropdown-footer">
                        <button type="button" class="btn btn-sm btn-link show-modal-btn w-100">
                            <i class="fas fa-external-link-alt me-1"></i>
                            Показать всё
                        </button>
                    </div>
                    ` : ''}
                </div>
                
                <div class="selected-product-info" style="display: none;"></div>
            </div>
        `;

        this.elements = {
            wrapper: this.container.querySelector('.product-selector'),
            input: this.container.querySelector('.product-search'),
            dropdownMenu: this.container.querySelector('.product-dropdown'),
            recentSection: this.container.querySelector('.recent-products'),
            recentList: this.container.querySelector('.recent-products-list'),
            searchResults: this.container.querySelector('.search-results-list'),
            noResults: this.container.querySelector('.no-results'),
            clearBtn: this.container.querySelector('.clear-product'),
            modalBtns: this.container.querySelectorAll('.show-modal-btn'),
            selectedInfo: this.container.querySelector('.selected-product-info')
        };
    }

    bindEvents() {
        // Простые события без Bootstrap dropdown API
        this.elements.input.addEventListener('focus', () => {
            if (!this.selectedProduct) {
                this.showDropdown();
                this.showRecentProducts();
            }
        });

        this.elements.input.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            this.handleSearch(query);
        });

        this.elements.clearBtn.addEventListener('click', () => {
            this.clearSelection();
        });

        this.elements.modalBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (typeof this.options.onShowAll === 'function') {
                    this.options.onShowAll(this);
                } else {
                    console.error('Callback onShowAll не определен для селектора');
                }
            });
        });
        
        // Клик по элементу в списке
        this.elements.dropdownMenu.addEventListener('click', async (e) => {
            const item = e.target.closest('.product-item');
            if (item) {
                e.preventDefault();
                e.stopPropagation();
                const productId = item.dataset.productId;
                if (productId) {
                    await this.setProductById(productId);
                    this.hideDropdown();
                }
            }
        });

        // Клик вне селектора
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target)) {
                this.hideDropdown();
            }
        });
    }

    showDropdown() {
        this.elements.dropdownMenu.style.display = 'block';
    }

    hideDropdown() {
        this.elements.dropdownMenu.style.display = 'none';
    }

    handleSearch(query) {
        clearTimeout(this.searchTimeout);

        if (query.length === 0) {
            this.showRecentProducts();
            return;
        }

        if (query.length < 2) {
            this.elements.noResults.innerHTML = '<i class="fas fa-search mb-2"></i><br>Введите еще хотя бы ' + (2 - query.length) + ' симв.';
            this.elements.noResults.style.display = 'block';
            this.elements.searchResults.innerHTML = '';
            this.elements.recentSection.style.display = 'none';
            return;
        }
        
        this.searchTimeout = setTimeout(() => {
            this.searchProducts(query);
        }, 300);
    }
    
    async searchProducts(query) {
        try {
            const response = await fetch(`/crm/api/product_selector.php?action=search&q=${encodeURIComponent(query)}&limit=10`);
            const data = await response.json();
            
            if (data.error) throw new Error(data.error);
            
            this.elements.recentSection.style.display = 'none';
            this.renderSearchResults(data.products || []);

        } catch (error) {
            console.error('Ошибка поиска товаров:', error);
            this.showError('Ошибка при поиске товаров');
        }
    }

    async loadRecentProducts() {
        try {
            const response = await fetch('/crm/api/product_selector.php?action=get_recent&limit=5');
            const data = await response.json();
            
            if (data.error) throw new Error(data.error);
            
            this.renderRecentProducts(data.products || []);
        } catch (error) {
            console.error('Ошибка загрузки недавних товаров:', error);
        }
    }

    renderSearchResults(products) {
        this.elements.noResults.style.display = products.length === 0 ? 'block' : 'none';
        if (products.length === 0) {
            this.elements.noResults.innerHTML = '<i class="fas fa-search mb-2"></i><br>Товары не найдены';
        }
        
        this.elements.searchResults.innerHTML = products.map(product => this.renderProductItem(product)).join('');
    }

    renderRecentProducts(products) {
        if (products.length > 0) {
            this.elements.recentList.innerHTML = products.map(product => this.renderProductItem(product)).join('');
        }
    }

    renderProductItem(product) {
        const price = product.price ? this.formatPrice(product.price) : '';
        const unit = product.unit || product.unit_of_measure || 'шт';
        
        return `
            <div class="product-item dropdown-item" data-product-id="${product.id}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold">${this.escapeHtml(product.name)}</div>
                        <small class="text-muted">Артикул: ${this.escapeHtml(product.article || 'Н/Д')}</small>
                    </div>
                    <div class="text-end">
                        ${price ? `<div class="fw-bold text-primary">${price}</div>` : ''}
                        <small class="text-muted">${this.escapeHtml(unit)}</small>
                    </div>
                </div>
            </div>
        `;
    }

    async setProductById(productId) {
        try {
            const response = await fetch(`/crm/api/product_selector.php?action=get_by_id&id=${productId}`);
            const data = await response.json();
            
            if (data.error) throw new Error(data.error);
            if (!data.product) throw new Error('Товар не найден');
            
            this.selectProduct(data.product);
            await this.saveToRecent(productId);
        } catch (error) {
            console.error('Ошибка загрузки товара:', error);
            this.showError('Ошибка при загрузке товара');
        }
    }

    selectProduct(product) {
        this.selectedProduct = product;
        this.elements.input.value = product.name;
        this.elements.clearBtn.style.display = 'inline-block';
        this.hideDropdown();
        
        if (typeof this.options.onSelect === 'function') {
            this.options.onSelect(product);
        }
    }

    clearSelection() {
        this.selectedProduct = null;
        this.elements.input.value = '';
        this.elements.clearBtn.style.display = 'none';
        this.elements.selectedInfo.style.display = 'none';
        this.hideDropdown();
        
        if (typeof this.options.onClear === 'function') {
            this.options.onClear();
        }
    }

    async saveToRecent(productId) {
        try {
            await fetch('/crm/api/product_selector.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=save_recent&product_id=${productId}`
            });
        } catch (error) {
            console.error('Ошибка сохранения в недавние:', error);
        }
    }

    showRecentProducts() {
        this.elements.recentSection.style.display = this.elements.recentList.children.length > 0 ? 'block' : 'none';
        this.elements.noResults.style.display = this.elements.recentList.children.length === 0 ? 'block' : 'none';
        this.elements.searchResults.innerHTML = '';
        
        if (this.elements.recentList.children.length === 0) {
            this.elements.noResults.innerHTML = '<i class="fas fa-search mb-2"></i><br>Начните вводить название товара';
        }
    }

    showError(message) {
        this.elements.noResults.innerHTML = `<i class="fas fa-exclamation-triangle mb-2 text-danger"></i><br>${message}`;
        this.elements.noResults.style.display = 'block';
        this.elements.searchResults.innerHTML = '';
        this.elements.recentSection.style.display = 'none';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatPrice(price) {
        return new Intl.NumberFormat('ru-RU', {
            style: 'currency',
            currency: 'RUB'
        }).format(price);
    }

    disable() {
        this.elements.input.disabled = true;
        this.elements.clearBtn.disabled = true;
        this.elements.modalBtns.forEach(btn => btn.disabled = true);
    }

    enable() {
        this.elements.input.disabled = false;
        this.elements.clearBtn.disabled = false;
        this.elements.modalBtns.forEach(btn => btn.disabled = false);
    }

    getValue() {
        return this.selectedProduct ? this.selectedProduct.id : null;
    }

    getSelectedProduct() {
        return this.selectedProduct;
    }

    // Методы для модального окна
    loadModalData() {
        if (typeof window.openModal === 'function') {
            const modalHTML = this.generateModalHTML();
            window.openModal(modalHTML, (modalElement) => {
                this.initModalEvents(modalElement);
                this.loadModalCategories(modalElement);
            });
        } else {
            console.error('Функция openModal не найдена!');
        }
    }

    generateModalHTML() {
        return `
            <div class="modal fade product-selector-modal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Выбор товара</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <h6>Категории</h6>
                                    <div class="categories-list" style="max-height: 400px; overflow-y: auto;">
                                        <div class="text-center py-3">
                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                            <div>Загрузка...</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <input type="text" class="form-control modal-search" placeholder="Поиск товаров...">
                                    </div>
                                    <div class="products-list" style="max-height: 400px; overflow-y: auto;">
                                        <div class="text-center py-3 text-muted">
                                            Выберите категорию или введите запрос для поиска
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    initModalEvents(modalElement) {
        const searchInput = modalElement.querySelector('.modal-search');
        const categoriesList = modalElement.querySelector('.categories-list');
        const productsList = modalElement.querySelector('.products-list');

        // Поиск в модальном окне
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    this.searchModalProducts(modalElement, query);
                }, 300);
            } else if (query.length === 0) {
                productsList.innerHTML = '<div class="text-center py-3 text-muted">Выберите категорию или введите запрос для поиска</div>';
            }
        });

        // Клик по категории
        categoriesList.addEventListener('click', (e) => {
            const categoryItem = e.target.closest('li[data-category-id]');
            if (categoryItem) {
                e.stopPropagation();
                const categoryId = categoryItem.dataset.categoryId;
                
                // Снимаем выделение с других категорий
                categoriesList.querySelectorAll('li').forEach(li => li.classList.remove('active'));
                categoryItem.classList.add('active');
                
                this.loadModalProducts(modalElement, categoryId);
            }
        });

        // Клик по товару
        productsList.addEventListener('click', (e) => {
            const productItem = e.target.closest('.product-item');
            if (productItem) {
                const productId = productItem.dataset.productId;
                this.setProductById(productId);
                
                // Закрываем модальное окно
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
            }
        });
    }

    async loadModalCategories(modalElement) {
        const categoriesList = modalElement.querySelector('.categories-list');
        
        try {
            const response = await fetch('/crm/api/product_selector.php?action=get_categories');
            const data = await response.json();
            
            if (data.error) throw new Error(data.error);
            
            this.renderModalCategories(categoriesList, data.categories || []);
        } catch (error) {
            console.error('Ошибка загрузки категорий:', error);
            categoriesList.innerHTML = '<div class="text-danger text-center py-3">Ошибка загрузки категорий</div>';
        }
    }

    renderModalCategories(container, categories) {
        if (categories.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-3">Категории не найдены</div>';
            return;
        }

        const html = categories.map(category => `
            <li class="list-group-item list-group-item-action" data-category-id="${category.id}">
                <div class="d-flex justify-content-between align-items-center">
                    <span>${this.escapeHtml(category.name)}</span>
                    <small class="text-muted">${category.product_count || 0}</small>
                </div>
            </li>
        `).join('');

        container.innerHTML = `<ul class="list-group list-group-flush">${html}</ul>`;
    }

    async loadModalProducts(modalElement, categoryId = null) {
        const productsList = modalElement.querySelector('.products-list');
        
        productsList.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm" role="status"></div><div>Загрузка...</div></div>';
        
        try {
            let url = '/crm/api/product_selector.php?action=by_category';
            if (categoryId) {
                url += `&category_id=${categoryId}`;
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.error) throw new Error(data.error);
            
            this.renderModalProducts(productsList, data.products || []);
        } catch (error) {
            console.error('Ошибка загрузки товаров:', error);
            productsList.innerHTML = '<div class="text-danger text-center py-3">Ошибка загрузки товаров</div>';
        }
    }

    async searchModalProducts(modalElement, query) {
        const productsList = modalElement.querySelector('.products-list');
        
        productsList.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm" role="status"></div><div>Поиск...</div></div>';
        
        try {
            const response = await fetch(`/crm/api/product_selector.php?action=search&q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.error) throw new Error(data.error);
            
            this.renderModalProducts(productsList, data.products || []);
        } catch (error) {
            console.error('Ошибка поиска товаров:', error);
            productsList.innerHTML = '<div class="text-danger text-center py-3">Ошибка поиска товаров</div>';
        }
    }

    renderModalProducts(container, products) {
        if (products.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-3">Товары не найдены</div>';
            return;
        }

        const html = products.map(product => {
            const price = product.price ? this.formatPrice(product.price) : '';
            const unit = product.unit || product.unit_of_measure || 'шт';
            
            return `
                <div class="product-item card mb-2" data-product-id="${product.id}" style="cursor: pointer;">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold">${this.escapeHtml(product.name)}</div>
                                <small class="text-muted">Артикул: ${this.escapeHtml(product.article || 'Н/Д')}</small>
                            </div>
                            <div class="text-end">
                                ${price ? `<div class="fw-bold text-primary">${price}</div>` : ''}
                                <small class="text-muted">${this.escapeHtml(unit)}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = html;
    }
}

// Экспорт для глобального использования
window.ProductSelector = ProductSelector; 
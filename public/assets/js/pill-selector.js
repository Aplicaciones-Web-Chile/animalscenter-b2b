/**
 * Pill Selector - Componente para selección múltiple con autocompletado
 * 
 * Este script implementa un selector de tipo "pill list" con autocompletado
 * para la selección de marcas en el formulario de usuarios.
 */

class PillSelector {
    constructor(options) {
        this.containerSelector = options.containerSelector;
        this.inputSelector = options.inputSelector;
        this.hiddenInputSelector = options.hiddenInputSelector;
        this.dataSource = options.dataSource || [];
        this.maxItems = options.maxItems || 0; // 0 = sin límite
        this.placeholder = options.placeholder || 'Escriba para buscar...';
        this.noResultsText = options.noResultsText || 'No se encontraron resultados';
        this.selectedItems = options.selectedItems || [];
        
        this.container = document.querySelector(this.containerSelector);
        this.input = document.querySelector(this.inputSelector);
        this.hiddenInput = document.querySelector(this.hiddenInputSelector);
        
        this.suggestionsContainer = null;
        this.pillsContainer = null;
        
        this.init();
    }
    
    init() {
        if (!this.container || !this.input || !this.hiddenInput) {
            console.error('No se encontraron los elementos necesarios para el PillSelector');
            return;
        }
        
        // Crear contenedor de pills
        this.pillsContainer = document.createElement('div');
        this.pillsContainer.className = 'pill-container';
        this.container.insertBefore(this.pillsContainer, this.input);
        
        // Crear contenedor de sugerencias
        this.suggestionsContainer = document.createElement('div');
        this.suggestionsContainer.className = 'suggestions-container';
        this.container.appendChild(this.suggestionsContainer);
        
        // Configurar input
        this.input.setAttribute('placeholder', this.placeholder);
        this.input.setAttribute('autocomplete', 'off');
        
        // Inicializar pills existentes
        this.renderSelectedItems();
        
        // Eventos
        this.input.addEventListener('input', this.handleInput.bind(this));
        this.input.addEventListener('keydown', this.handleKeyDown.bind(this));
        this.input.addEventListener('focus', this.handleFocus.bind(this));
        document.addEventListener('click', this.handleDocumentClick.bind(this));
    }
    
    handleInput(e) {
        const value = e.target.value.trim().toLowerCase();
        
        if (value.length < 1) {
            this.hideSuggestions();
            return;
        }
        
        const filteredItems = this.dataSource.filter(item => {
            // No mostrar items ya seleccionados
            if (this.isItemSelected(item.id)) {
                return false;
            }
            
            // Filtrar por texto
            return item.nombre.toLowerCase().includes(value);
        });
        
        this.renderSuggestions(filteredItems);
    }
    
    handleKeyDown(e) {
        // Si presiona Escape, ocultar sugerencias
        if (e.key === 'Escape') {
            this.hideSuggestions();
        }
        
        // Si presiona Enter y hay sugerencias seleccionadas, agregarlas
        if (e.key === 'Enter') {
            const selectedSuggestion = this.suggestionsContainer.querySelector('.suggestion.selected');
            if (selectedSuggestion) {
                e.preventDefault();
                this.addItem({
                    id: selectedSuggestion.dataset.id,
                    nombre: selectedSuggestion.dataset.nombre
                });
            }
        }
        
        // Navegación con teclas de flecha
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            
            const suggestions = this.suggestionsContainer.querySelectorAll('.suggestion');
            if (suggestions.length === 0) return;
            
            const selectedSuggestion = this.suggestionsContainer.querySelector('.suggestion.selected');
            let nextIndex = 0;
            
            if (selectedSuggestion) {
                const currentIndex = Array.from(suggestions).indexOf(selectedSuggestion);
                if (e.key === 'ArrowDown') {
                    nextIndex = (currentIndex + 1) % suggestions.length;
                } else {
                    nextIndex = (currentIndex - 1 + suggestions.length) % suggestions.length;
                }
                selectedSuggestion.classList.remove('selected');
            } else if (e.key === 'ArrowUp') {
                nextIndex = suggestions.length - 1;
            }
            
            suggestions[nextIndex].classList.add('selected');
            suggestions[nextIndex].scrollIntoView({ block: 'nearest' });
        }
    }
    
    handleFocus() {
        const value = this.input.value.trim();
        if (value.length > 0) {
            this.handleInput({ target: this.input });
        }
    }
    
    handleDocumentClick(e) {
        if (!this.container.contains(e.target)) {
            this.hideSuggestions();
        }
    }
    
    renderSuggestions(items) {
        this.suggestionsContainer.innerHTML = '';
        
        if (items.length === 0) {
            const noResults = document.createElement('div');
            noResults.className = 'no-results';
            noResults.textContent = this.noResultsText;
            this.suggestionsContainer.appendChild(noResults);
            this.suggestionsContainer.style.display = 'block';
            return;
        }
        
        items.forEach(item => {
            const suggestion = document.createElement('div');
            suggestion.className = 'suggestion';
            suggestion.dataset.id = item.id;
            suggestion.dataset.nombre = item.nombre;
            suggestion.textContent = item.nombre;
            
            suggestion.addEventListener('click', () => {
                this.addItem(item);
            });
            
            this.suggestionsContainer.appendChild(suggestion);
        });
        
        this.suggestionsContainer.style.display = 'block';
    }
    
    hideSuggestions() {
        this.suggestionsContainer.style.display = 'none';
    }
    
    addItem(item) {
        if (this.isItemSelected(item.id)) {
            return;
        }
        
        if (this.maxItems > 0 && this.selectedItems.length >= this.maxItems) {
            alert(`No puede seleccionar más de ${this.maxItems} elementos`);
            return;
        }
        
        this.selectedItems.push(item);
        this.renderSelectedItems();
        this.updateHiddenInput();
        this.input.value = '';
        this.hideSuggestions();
    }
    
    removeItem(itemId) {
        this.selectedItems = this.selectedItems.filter(item => item.id !== itemId);
        this.renderSelectedItems();
        this.updateHiddenInput();
    }
    
    isItemSelected(itemId) {
        return this.selectedItems.some(item => item.id === itemId);
    }
    
    renderSelectedItems() {
        this.pillsContainer.innerHTML = '';
        
        this.selectedItems.forEach(item => {
            const pill = document.createElement('div');
            pill.className = 'pill';
            
            const pillText = document.createElement('span');
            pillText.className = 'pill-text';
            pillText.textContent = item.nombre;
            
            const removeBtn = document.createElement('span');
            removeBtn.className = 'pill-remove';
            removeBtn.innerHTML = '&times;';
            removeBtn.addEventListener('click', () => {
                this.removeItem(item.id);
            });
            
            pill.appendChild(pillText);
            pill.appendChild(removeBtn);
            this.pillsContainer.appendChild(pill);
        });
    }
    
    updateHiddenInput() {
        const selectedIds = this.selectedItems.map(item => item.id);
        this.hiddenInput.value = JSON.stringify(selectedIds);
    }
    
    // Método para actualizar los items seleccionados desde fuera
    setSelectedItems(items) {
        this.selectedItems = items;
        this.renderSelectedItems();
        this.updateHiddenInput();
    }
}

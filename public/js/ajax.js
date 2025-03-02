/**
 * Funciones JavaScript para manejar peticiones AJAX en el sistema B2B
 */

// Función para obtener el token CSRF
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

/**
 * Realiza una petición AJAX
 * 
 * @param {string} url - URL de la petición
 * @param {string} method - Método HTTP (GET, POST, PUT, DELETE)
 * @param {object} data - Datos a enviar (para POST, PUT)
 * @param {function} successCallback - Función a ejecutar si la petición es exitosa
 * @param {function} errorCallback - Función a ejecutar si hay un error
 */
function ajaxRequest(url, method, data, successCallback, errorCallback) {
    // Crear el objeto XMLHttpRequest
    var xhr = new XMLHttpRequest();
    
    // Configurar la petición
    xhr.open(method, url, true);
    
    // Configurar cabeceras
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    if (method === 'POST' || method === 'PUT') {
        xhr.setRequestHeader('Content-Type', 'application/json');
        
        // Agregar token CSRF si existe
        const csrfToken = getCsrfToken();
        if (csrfToken) {
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        }
    }
    
    // Configurar manejo de la respuesta
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            // Éxito
            var response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                response = xhr.responseText;
            }
            if (typeof successCallback === 'function') {
                successCallback(response);
            }
        } else {
            // Error HTTP
            var errorResponse;
            try {
                errorResponse = JSON.parse(xhr.responseText);
            } catch (e) {
                errorResponse = {
                    message: 'Error en la petición: ' + xhr.status + ' ' + xhr.statusText
                };
            }
            if (typeof errorCallback === 'function') {
                errorCallback(errorResponse, xhr.status);
            } else {
                console.error('Error en la petición:', errorResponse);
            }
        }
    };
    
    // Manejar errores de red
    xhr.onerror = function() {
        if (typeof errorCallback === 'function') {
            errorCallback({
                message: 'Error de red. Compruebe su conexión a Internet.'
            }, 0);
        } else {
            console.error('Error de red');
        }
    };
    
    // Enviar la petición
    if (method === 'POST' || method === 'PUT') {
        xhr.send(JSON.stringify(data));
    } else {
        xhr.send();
    }
}

/**
 * Realiza una petición GET AJAX
 */
function ajaxGet(url, successCallback, errorCallback) {
    ajaxRequest(url, 'GET', null, successCallback, errorCallback);
}

/**
 * Realiza una petición POST AJAX
 */
function ajaxPost(url, data, successCallback, errorCallback) {
    ajaxRequest(url, 'POST', data, successCallback, errorCallback);
}

/**
 * Actualiza dinámicamente una tabla con datos de AJAX
 * 
 * @param {string} tableId - ID de la tabla a actualizar
 * @param {array} data - Array de objetos con los datos
 * @param {array} columns - Array con los nombres de las columnas a mostrar
 * @param {function} rowCallback - Función opcional para personalizar filas
 */
function updateTable(tableId, data, columns, rowCallback) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    // Limpiar tabla
    tbody.innerHTML = '';
    
    // Si no hay datos
    if (!data || data.length === 0) {
        var emptyRow = document.createElement('tr');
        var emptyCell = document.createElement('td');
        emptyCell.setAttribute('colspan', columns.length);
        emptyCell.textContent = 'No hay datos disponibles';
        emptyCell.className = 'text-center p-3';
        emptyRow.appendChild(emptyCell);
        tbody.appendChild(emptyRow);
        return;
    }
    
    // Agregar datos
    data.forEach(function(item) {
        var row = document.createElement('tr');
        
        columns.forEach(function(column) {
            var cell = document.createElement('td');
            cell.textContent = item[column] || '';
            row.appendChild(cell);
        });
        
        // Si hay callback para personalizar la fila
        if (typeof rowCallback === 'function') {
            rowCallback(row, item);
        }
        
        tbody.appendChild(row);
    });
}

/**
 * Muestra un mensaje de notificación temporal
 * 
 * @param {string} message - Mensaje a mostrar
 * @param {string} type - Tipo de mensaje (success, danger, warning, info)
 * @param {number} duration - Duración en milisegundos
 */
function showNotification(message, type = 'info', duration = 3000) {
    // Crear el elemento de notificación
    var notification = document.createElement('div');
    notification.className = 'toast align-items-center text-white bg-' + type;
    notification.setAttribute('role', 'alert');
    notification.setAttribute('aria-live', 'assertive');
    notification.setAttribute('aria-atomic', 'true');
    
    // Agregar contenido
    notification.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    // Agregar al contenedor de notificaciones o crear uno
    var container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    container.appendChild(notification);
    
    // Inicializar y mostrar la notificación
    var toast = new bootstrap.Toast(notification, {
        delay: duration
    });
    toast.show();
    
    // Eliminar la notificación después de ocultarse
    notification.addEventListener('hidden.bs.toast', function() {
        notification.remove();
        
        // Eliminar el contenedor si está vacío
        if (container.childNodes.length === 0) {
            container.remove();
        }
    });
}

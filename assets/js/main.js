/**
 * Sistema de Gerenciamento de Funcionários - JavaScript Principal
 */

// Configurações globais
const Config = {
    baseUrl: window.location.origin + '/sistema_funcionarios',
    ajaxTimeout: 30000,
    sessionCheckInterval: 300000, // 5 minutos
    autoSaveInterval: 60000 // 1 minuto
};

// Utilitários globais
const Utils = {
    // Formatar CPF
    formatCPF: function(cpf) {
        cpf = cpf.replace(/\D/g, '');
        return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    },
    
    // Formatar CNPJ
    formatCNPJ: function(cnpj) {
        cnpj = cnpj.replace(/\D/g, '');
        return cnpj.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    },
    
    // Formatar data
    formatDate: function(date) {
        if (!date) return '';
        const d = new Date(date);
        return d.toLocaleDateString('pt-BR');
    },
    
    // Formatar data e hora
    formatDateTime: function(datetime) {
        if (!datetime) return '';
        const d = new Date(datetime);
        return d.toLocaleString('pt-BR');
    },
    
    // Validar CPF
    validateCPF: function(cpf) {
        cpf = cpf.replace(/\D/g, '');
        
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
            return false;
        }
        
        let sum = 0;
        for (let i = 0; i < 9; i++) {
            sum += parseInt(cpf.charAt(i)) * (10 - i);
        }
        let remainder = 11 - (sum % 11);
        if (remainder === 10 || remainder === 11) remainder = 0;
        if (remainder !== parseInt(cpf.charAt(9))) return false;
        
        sum = 0;
        for (let i = 0; i < 10; i++) {
            sum += parseInt(cpf.charAt(i)) * (11 - i);
        }
        remainder = 11 - (sum % 11);
        if (remainder === 10 || remainder === 11) remainder = 0;
        if (remainder !== parseInt(cpf.charAt(10))) return false;
        
        return true;
    },
    
    // Validar CNPJ
    validateCNPJ: function(cnpj) {
        cnpj = cnpj.replace(/\D/g, '');
        
        if (cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)) {
            return false;
        }
        
        let sum = 0;
        let weight = 5;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(cnpj.charAt(i)) * weight;
            weight = weight === 2 ? 9 : weight - 1;
        }
        let remainder = sum % 11;
        let digit1 = remainder < 2 ? 0 : 11 - remainder;
        
        sum = 0;
        weight = 6;
        for (let i = 0; i < 13; i++) {
            sum += parseInt(cnpj.charAt(i)) * weight;
            weight = weight === 2 ? 9 : weight - 1;
        }
        remainder = sum % 11;
        let digit2 = remainder < 2 ? 0 : 11 - remainder;
        
        return parseInt(cnpj.charAt(12)) === digit1 && parseInt(cnpj.charAt(13)) === digit2;
    },
    
    // Validar email
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    // Debounce function
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Mostrar loading
    showLoading: function(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.disabled = true;
            const originalText = element.textContent;
            element.dataset.originalText = originalText;
            element.innerHTML = '<span class="loading"></span> Carregando...';
        }
    },
    
    // Esconder loading
    hideLoading: function(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element && element.dataset.originalText) {
            element.disabled = false;
            element.textContent = element.dataset.originalText;
            delete element.dataset.originalText;
        }
    }
};

// Sistema de notificações
const Notifications = {
    show: function(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} notification fade-in`;
        notification.innerHTML = `
            <span>${message}</span>
            <button type="button" class="notification-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        // Adicionar estilos para notificação flutuante
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            box-shadow: var(--shadow-heavy);
            cursor: pointer;
        `;
        
        document.body.appendChild(notification);
        
        // Auto remover
        if (duration > 0) {
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, duration);
        }
        
        // Remover ao clicar
        notification.addEventListener('click', () => {
            notification.remove();
        });
    },
    
    success: function(message, duration = 5000) {
        this.show(message, 'success', duration);
    },
    
    error: function(message, duration = 8000) {
        this.show(message, 'danger', duration);
    },
    
    warning: function(message, duration = 6000) {
        this.show(message, 'warning', duration);
    },
    
    info: function(message, duration = 5000) {
        this.show(message, 'info', duration);
    }
};

// Sistema de modais
const Modal = {
    show: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    },
    
    hide: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    },
    
    confirm: function(message, callback) {
        const modalHtml = `
            <div class="modal" id="confirmModal">
                <div class="modal-content" style="max-width: 400px;">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirmação</h5>
                        <button type="button" class="modal-close" onclick="Modal.hide('confirmModal')">×</button>
                    </div>
                    <div class="modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="Modal.hide('confirmModal')">Cancelar</button>
                        <button type="button" class="btn btn-danger" id="confirmBtn">Confirmar</button>
                    </div>
                </div>
            </div>
        `;
        
        // Remover modal existente
        const existingModal = document.getElementById('confirmModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Adicionar novo modal
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Configurar callback
        document.getElementById('confirmBtn').addEventListener('click', () => {
            Modal.hide('confirmModal');
            if (callback) callback();
        });
        
        // Mostrar modal
        this.show('confirmModal');
    }
};

// Sistema AJAX
const Ajax = {
    request: function(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            timeout: Config.ajaxTimeout
        };
        
        const config = { ...defaults, ...options };
        
        // Adicionar CSRF token se disponível
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            config.headers['X-CSRF-Token'] = csrfToken.getAttribute('content');
        }
        
        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                throw error;
            });
    },
    
    get: function(url, params = {}) {
        const urlParams = new URLSearchParams(params);
        const fullUrl = urlParams.toString() ? `${url}?${urlParams}` : url;
        return this.request(fullUrl);
    },
    
    post: function(url, data = {}) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },
    
    put: function(url, data = {}) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },
    
    delete: function(url) {
        return this.request(url, {
            method: 'DELETE'
        });
    },
    
    // Enviar formulário via AJAX
    submitForm: function(form, callback) {
        if (typeof form === 'string') {
            form = document.querySelector(form);
        }
        
        const formData = new FormData(form);
        const url = form.action || window.location.href;
        const method = form.method || 'POST';
        
        return fetch(url, {
            method: method,
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (callback) callback(data);
            return data;
        })
        .catch(error => {
            console.error('Erro no envio do formulário:', error);
            Notifications.error('Erro ao enviar formulário');
            throw error;
        });
    }
};

// Sistema de validação de formulários
const FormValidator = {
    rules: {
        required: function(value) {
            return value.trim() !== '';
        },
        
        email: function(value) {
            return Utils.validateEmail(value);
        },
        
        cpf: function(value) {
            return Utils.validateCPF(value);
        },
        
        cnpj: function(value) {
            return Utils.validateCNPJ(value);
        },
        
        minLength: function(value, min) {
            return value.length >= min;
        },
        
        maxLength: function(value, max) {
            return value.length <= max;
        },
        
        numeric: function(value) {
            return /^\d+$/.test(value);
        },
        
        date: function(value) {
            const date = new Date(value);
            return !isNaN(date.getTime());
        }
    },
    
    messages: {
        required: 'Este campo é obrigatório',
        email: 'Digite um e-mail válido',
        cpf: 'Digite um CPF válido',
        cnpj: 'Digite um CNPJ válido',
        minLength: 'Mínimo de {0} caracteres',
        maxLength: 'Máximo de {0} caracteres',
        numeric: 'Digite apenas números',
        date: 'Digite uma data válida'
    },
    
    validate: function(form) {
        if (typeof form === 'string') {
            form = document.querySelector(form);
        }
        
        let isValid = true;
        const fields = form.querySelectorAll('[data-validate]');
        
        fields.forEach(field => {
            const rules = field.dataset.validate.split('|');
            const value = field.value.trim();
            let fieldValid = true;
            
            // Remover erros anteriores
            this.clearFieldError(field);
            
            rules.forEach(rule => {
                if (!fieldValid) return;
                
                const [ruleName, ruleParam] = rule.split(':');
                
                if (this.rules[ruleName]) {
                    if (!this.rules[ruleName](value, ruleParam)) {
                        fieldValid = false;
                        isValid = false;
                        
                        let message = this.messages[ruleName] || 'Campo inválido';
                        if (ruleParam) {
                            message = message.replace('{0}', ruleParam);
                        }
                        
                        this.showFieldError(field, message);
                    }
                }
            });
        });
        
        return isValid;
    },
    
    showFieldError: function(field, message) {
        field.classList.add('error');
        
        let errorElement = field.parentElement.querySelector('.form-error');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'form-error';
            field.parentElement.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
    },
    
    clearFieldError: function(field) {
        field.classList.remove('error');
        
        const errorElement = field.parentElement.querySelector('.form-error');
        if (errorElement) {
            errorElement.remove();
        }
    },
    
    clearAllErrors: function(form) {
        if (typeof form === 'string') {
            form = document.querySelector(form);
        }
        
        const fields = form.querySelectorAll('.error');
        fields.forEach(field => {
            this.clearFieldError(field);
        });
    }
};

// Sistema de máscaras
const Masks = {
    apply: function() {
        // CPF
        document.querySelectorAll('[data-mask="cpf"]').forEach(input => {
            input.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                this.value = value;
            });
        });
        
        // CNPJ
        document.querySelectorAll('[data-mask="cnpj"]').forEach(input => {
            input.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                value = value.replace(/(\d{2})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1/$2');
                value = value.replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                this.value = value;
            });
        });
        
        // Telefone
        document.querySelectorAll('[data-mask="phone"]').forEach(input => {
            input.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.length <= 10) {
                    value = value.replace(/(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                } else {
                    value = value.replace(/(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{5})(\d)/, '$1-$2');
                }
                this.value = value;
            });
        });
        
        // CEP
        document.querySelectorAll('[data-mask="cep"]').forEach(input => {
            input.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                this.value = value;
            });
        });
    }
};

// Sistema de sidebar
const Sidebar = {
    init: function() {
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.querySelector('.sidebar-toggle');
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.querySelector('.mobile-overlay');
        
        if (toggle) {
            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
            });
        }
        
        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }
        
        // Restaurar estado do sidebar
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
        }
        
        // Marcar item ativo no menu
        const currentPath = window.location.pathname;
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('active');
            }
        });
    }
};

// Sistema de verificação de sessão
const SessionManager = {
    init: function() {
        // Verificar sessão periodicamente
        setInterval(() => {
            this.checkSession();
        }, Config.sessionCheckInterval);
        
        // Verificar ao focar na janela
        window.addEventListener('focus', () => {
            this.checkSession();
        });
    },
    
    checkSession: function() {
        Ajax.get(Config.baseUrl + '/controllers/AuthController.php?action=verificar_sessao')
            .then(data => {
                if (!data.ativo) {
                    Notifications.warning('Sua sessão expirou. Você será redirecionado para o login.');
                    setTimeout(() => {
                        window.location.href = Config.baseUrl + '/public/login.php';
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Erro ao verificar sessão:', error);
            });
    }
};

// Inicialização quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar sistemas
    Sidebar.init();
    Masks.apply();
    SessionManager.init();
    
    // Configurar modais
    document.addEventListener('click', function(e) {
        // Fechar modal ao clicar no overlay
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('show');
            document.body.style.overflow = '';
        }
        
        // Fechar modal ao clicar no botão de fechar
        if (e.target.classList.contains('modal-close')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
    });
    
    // Configurar formulários com validação
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (FormValidator.validate(this)) {
                // Se o formulário for válido, enviar via AJAX se especificado
                if (this.dataset.ajax === 'true') {
                    const submitBtn = this.querySelector('[type="submit"]');
                    Utils.showLoading(submitBtn);
                    
                    Ajax.submitForm(this)
                        .then(data => {
                            if (data.success) {
                                Notifications.success(data.message || 'Operação realizada com sucesso');
                                
                                // Redirecionar se especificado
                                if (data.redirect) {
                                    setTimeout(() => {
                                        window.location.href = data.redirect;
                                    }, 1500);
                                }
                            } else {
                                Notifications.error(data.message || 'Erro ao processar solicitação');
                            }
                        })
                        .catch(error => {
                            Notifications.error('Erro de conexão. Tente novamente.');
                        })
                        .finally(() => {
                            Utils.hideLoading(submitBtn);
                        });
                } else {
                    // Envio normal do formulário
                    this.submit();
                }
            }
        });
    });
    
    // Configurar botões de confirmação
    document.querySelectorAll('[data-confirm]').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const message = this.dataset.confirm;
            const href = this.href || this.dataset.href;
            
            Modal.confirm(message, () => {
                if (href) {
                    window.location.href = href;
                } else if (this.onclick) {
                    this.onclick();
                }
            });
        });
    });
    
    // Auto-save para formulários longos
    document.querySelectorAll('form[data-autosave]').forEach(form => {
        const formId = form.id || 'form_' + Date.now();
        
        // Carregar dados salvos
        const savedData = localStorage.getItem('autosave_' + formId);
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                Object.keys(data).forEach(name => {
                    const field = form.querySelector(`[name="${name}"]`);
                    if (field && field.type !== 'password') {
                        field.value = data[name];
                    }
                });
            } catch (e) {
                console.error('Erro ao carregar dados salvos:', e);
            }
        }
        
        // Salvar dados periodicamente
        setInterval(() => {
            const formData = new FormData(form);
            const data = {};
            
            for (let [name, value] of formData.entries()) {
                const field = form.querySelector(`[name="${name}"]`);
                if (field && field.type !== 'password') {
                    data[name] = value;
                }
            }
            
            localStorage.setItem('autosave_' + formId, JSON.stringify(data));
        }, Config.autoSaveInterval);
        
        // Limpar dados salvos ao enviar com sucesso
        form.addEventListener('submit', function() {
            setTimeout(() => {
                localStorage.removeItem('autosave_' + formId);
            }, 1000);
        });
    });
    
    // Configurar tooltips simples
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.dataset.tooltip;
            tooltip.style.cssText = `
                position: absolute;
                background: rgba(0,0,0,0.8);
                color: white;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 9999;
                pointer-events: none;
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                delete this._tooltip;
            }
        });
    });
});

// Exportar para uso global
window.Utils = Utils;
window.Notifications = Notifications;
window.Modal = Modal;
window.Ajax = Ajax;
window.FormValidator = FormValidator;
window.Masks = Masks;


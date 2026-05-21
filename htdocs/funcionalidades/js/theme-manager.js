/* ═══════════════════════════════════════════════════════════
   theme-manager.js — Sistema Global de Gestión de Temas
   Se carga en TODAS las páginas para consistencia total
   ═══════════════════════════════════════════════════════════ */

(function() {
    'use strict';

    // ── Configuración del sistema de temas ──────────────────
    const THEME_CONFIG = {
        STORAGE_KEY: 'valtasy-theme',
        DEFAULT_THEME: 'light',   // Modo claro fijo como predeterminado
        THEMES: ['dark', 'light'],
        ATTRIBUTE: 'data-theme'
    };

    // ── Clase principal del gestor de temas ─────────────────
    class ThemeManager {
        constructor() {
            this.currentTheme = this.loadTheme();
            this.toggleButton = null;
            this.init();
        }

        // Inicializar el sistema
        init() {
            this.applyTheme(this.currentTheme, false);
            
            // Esperar a que el DOM esté listo
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setupUI());
            } else {
                this.setupUI();
            }

            // Escuchar cambios de tema desde otras pestañas
            window.addEventListener('storage', (e) => {
                if (e.key === THEME_CONFIG.STORAGE_KEY && e.newValue) {
                    this.applyTheme(e.newValue, false);
                }
            });
        }

        // Cargar tema guardado o usar default
        loadTheme() {
            try {
                const saved = localStorage.getItem(THEME_CONFIG.STORAGE_KEY);
                if (!saved) {
                    // Primera visita: guardar el tema por defecto (light)
                    localStorage.setItem(THEME_CONFIG.STORAGE_KEY, THEME_CONFIG.DEFAULT_THEME);
                    return THEME_CONFIG.DEFAULT_THEME;
                }
                return THEME_CONFIG.THEMES.includes(saved) ? saved : THEME_CONFIG.DEFAULT_THEME;
            } catch (e) {
                console.warn('No se pudo acceder a localStorage:', e);
                return THEME_CONFIG.DEFAULT_THEME;
            }
        }

        // Guardar tema en localStorage
        saveTheme(theme) {
            try {
                localStorage.setItem(THEME_CONFIG.STORAGE_KEY, theme);
            } catch (e) {
                console.warn('No se pudo guardar el tema:', e);
            }
        }

        // Aplicar tema al documento
        applyTheme(theme, save = true) {
            if (!THEME_CONFIG.THEMES.includes(theme)) {
                theme = THEME_CONFIG.DEFAULT_THEME;
            }

            this.currentTheme = theme;
            document.documentElement.setAttribute(THEME_CONFIG.ATTRIBUTE, theme);
            
            if (save) {
                this.saveTheme(theme);
            }

            this.updateToggleUI();
            this.dispatchThemeChange(theme);
        }

        // Cambiar entre temas
        toggleTheme() {
            const newTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
            this.applyTheme(newTheme);
        }

        // Configurar UI del toggle
        setupUI() {
            // Buscar toggle existente
            this.toggleButton = document.getElementById('themeToggle');
            
            // Si no existe, crear uno
            if (!this.toggleButton) {
                this.createToggleButton();
            }

            // Configurar evento
            if (this.toggleButton) {
                this.toggleButton.addEventListener('click', () => this.toggleTheme());
                this.updateToggleUI();
            }
        }

        // Crear botón de toggle si no existe
        createToggleButton() {
            // Buscar la barra superior o header
            const topbar = document.querySelector('.topbar-right, .header-right, .header');
            
            if (topbar) {
                const toggle = document.createElement('div');
                toggle.id = 'themeToggle';
                toggle.className = 'theme-toggle';
                toggle.innerHTML = '<div class="theme-toggle-slider">🌙</div>';
                toggle.title = 'Cambiar tema';
                
                // Insertar al principio del contenedor
                topbar.insertBefore(toggle, topbar.firstChild);
                this.toggleButton = toggle;
            }
        }

        // Actualizar UI del toggle
        updateToggleUI() {
            if (!this.toggleButton) return;

            const slider = this.toggleButton.querySelector('.theme-toggle-slider');
            if (!slider) return;

            if (this.currentTheme === 'light') {
                this.toggleButton.classList.add('active');
                slider.textContent = '☀️';
            } else {
                this.toggleButton.classList.remove('active');
                slider.textContent = '🌙';
            }
        }

        // Disparar evento personalizado de cambio de tema
        dispatchThemeChange(theme) {
            const event = new CustomEvent('themechange', {
                detail: { theme: theme }
            });
            window.dispatchEvent(event);
        }

        // Obtener tema actual
        getTheme() {
            return this.currentTheme;
        }

        // Establecer tema específico
        setTheme(theme) {
            this.applyTheme(theme);
        }
    }

    // ── Crear instancia global ──────────────────────────────
    window.ValtasyTheme = new ThemeManager();

    // ── API pública para compatibilidad ─────────────────────
    window.toggleTheme = function() {
        window.ValtasyTheme.toggleTheme();
    };

    window.getTheme = function() {
        return window.ValtasyTheme.getTheme();
    };

    window.setTheme = function(theme) {
        window.ValtasyTheme.setTheme(theme);
    };

})();

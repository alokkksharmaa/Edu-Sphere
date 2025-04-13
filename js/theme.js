// Theme management
const Theme = {
    init() {
        this.theme = localStorage.getItem('theme') || 'light';
        this.applyTheme();
        this.setupListeners();
    },

    applyTheme() {
        if (this.theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        
        // Save to server if user is logged in
        const userId = document.body.dataset.userId;
        if (userId) {
            fetch('/api/update_preferences.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    theme: this.theme,
                    csrf_token: document.querySelector('meta[name="csrf-token"]').content
                })
            });
        }
    },

    toggle() {
        this.theme = this.theme === 'light' ? 'dark' : 'light';
        localStorage.setItem('theme', this.theme);
        this.applyTheme();
    },

    setupListeners() {
        document.getElementById('theme-toggle')?.addEventListener('click', () => this.toggle());
    }
};

// Initialize theme on page load
document.addEventListener('DOMContentLoaded', () => Theme.init());

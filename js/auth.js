// Manejo del formulario de login
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
        
        // Manejar selección de tipo de usuario
        const userTypeOptions = document.querySelectorAll('.type-option');
        const userTypeInput = document.getElementById('userType');
        
        userTypeOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remover selección previa
                userTypeOptions.forEach(opt => opt.classList.remove('selected'));
                
                // Agregar selección actual
                this.classList.add('selected');
                userTypeInput.value = this.dataset.type;
            });
        });
        
        // Validar fortaleza de contraseña
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', checkPasswordStrength);
        }
    }
    
    // Manejar logout
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
});

async function handleLogin(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    // Validación básica
    if (!data.email || !data.password || !data.userType) {
        showNotification('Por favor, completa todos los campos', 'error');
        return;
    }
    
    try {
        // Mostrar loading
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Iniciando sesión...';
        submitBtn.disabled = true;
        
        // Simular petición al servidor
        await simulateServerRequest(1500);
        
        // En producción, usar fetch real:
        // const response = await fetch('php/login.php', {
        //     method: 'POST',
        //     headers: {'Content-Type': 'application/json'},
        //     body: JSON.stringify(data)
        // });
        
        // Guardar en localStorage (simulación)
        localStorage.setItem('userData', JSON.stringify({
            email: data.email,
            userType: data.userType,
            loggedIn: true,
            timestamp: new Date().toISOString()
        }));
        
        // Redirigir según tipo de usuario
        let redirectUrl = 'dashboard.html';
        
        showNotification('¡Inicio de sesión exitoso!', 'success');
        
        setTimeout(() => {
            window.location.href = redirectUrl;
        }, 1000);
        
    } catch (error) {
        showNotification('Error al iniciar sesión. Verifica tus credenciales.', 'error');
        console.error('Login error:', error);
    } finally {
        // Restaurar botón
        const submitBtn = e.target.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Iniciar Sesión';
            submitBtn.disabled = false;
        }
    }
}

async function handleRegister(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    // Validaciones
    if (data.password !== data.confirm_password) {
        showNotification('Las contraseñas no coinciden', 'error');
        return;
    }
    
    if (!data.userType) {
        showNotification('Por favor, selecciona un tipo de usuario', 'error');
        return;
    }
    
    if (!data.terms) {
        showNotification('Debes aceptar los términos y condiciones', 'error');
        return;
    }
    
    try {
        // Mostrar loading
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando cuenta...';
        submitBtn.disabled = true;
        
        // Simular petición al servidor
        await simulateServerRequest(2000);
        
        // Guardar datos (simulación)
        localStorage.setItem('userData', JSON.stringify({
            nombre: data.nombre,
            email: data.email,
            userType: data.userType,
            fecha_nacimiento: data.fecha_nacimiento,
            loggedIn: true,
            timestamp: new Date().toISOString()
        }));
        
        showNotification('¡Cuenta creada exitosamente!', 'success');
        
        // Redirigir al dashboard
        setTimeout(() => {
            window.location.href = 'dashboard.html';
        }, 1500);
        
    } catch (error) {
        showNotification('Error al crear la cuenta. Intenta nuevamente.', 'error');
        console.error('Register error:', error);
    } finally {
        // Restaurar botón
        const submitBtn = e.target.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Crear cuenta';
            submitBtn.disabled = false;
        }
    }
}

function checkPasswordStrength() {
    const password = document.getElementById('password').value;
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.querySelector('.strength-text');
    
    let strength = 0;
    let color = '#EF476F'; // Rojo por defecto
    let text = 'Muy débil';
    
    // Verificar longitud
    if (password.length >= 8) strength += 25;
    
    // Verificar mayúsculas
    if (/[A-Z]/.test(password)) strength += 25;
    
    // Verificar números
    if (/[0-9]/.test(password)) strength += 25;
    
    // Verificar caracteres especiales
    if (/[^A-Za-z0-9]/.test(password)) strength += 25;
    
    // Determinar color y texto
    if (strength >= 75) {
        color = '#06D6A0'; // Verde
        text = 'Fuerte';
    } else if (strength >= 50) {
        color = '#FFD166'; // Amarillo
        text = 'Moderada';
    } else if (strength >= 25) {
        color = '#FF9AA2'; // Rosa
        text = 'Débil';
    }
    
    // Actualizar UI
    if (strengthBar) {
        strengthBar.style.width = `${strength}%`;
        strengthBar.style.backgroundColor = color;
    }
    
    if (strengthText) {
        strengthText.textContent = `Seguridad: ${text}`;
        strengthText.style.color = color;
    }
}

function handleLogout(e) {
    e.preventDefault();
    
    if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
        localStorage.removeItem('userData');
        window.location.href = 'index.html';
    }
}

function showNotification(message, type = 'info') {
    // Crear notificación
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;
    
    // Estilos de la notificación
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#06D6A0' : '#EF476F'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    
    // Animación
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    
    // Agregar al documento
    document.body.appendChild(notification);
    
    // Botón de cerrar
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    });
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

function simulateServerRequest(duration) {
    return new Promise(resolve => setTimeout(resolve, duration));
}

// Verificar autenticación al cargar páginas protegidas
function checkAuth() {
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    
    if (!userData.loggedIn) {
        window.location.href = 'login.html';
        return false;
    }
    
    return userData;
}

// Cargar datos del usuario en el dashboard
function loadUserData() {
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    
    if (userData.nombre) {
        const userNameElements = document.querySelectorAll('#userName, #welcomeName');
        userNameElements.forEach(el => {
            if (el) el.textContent = userData.nombre.split(' ')[0];
        });
    }
    
    if (userData.userType) {
        const userRoleElement = document.getElementById('userRole');
        if (userRoleElement) {
            const roles = {
                'alumno': 'Alumno',
                'tutor': 'Tutor/Docente',
                'padre': 'Padre/Tutor',
                'admin': 'Administrador'
            };
            userRoleElement.textContent = roles[userData.userType] || userData.userType;
        }
    }
}

// Ejecutar cuando se cargue la página
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        // Páginas protegidas
        if (window.location.pathname.includes('dashboard') || 
            window.location.pathname.includes('admin') ||
            window.location.pathname.includes('student') ||
            window.location.pathname.includes('teacher')) {
            
            const userData = checkAuth();
            if (userData) {
                loadUserData();
            }
        }
    });
} else {
    // DOM ya cargado
    if (window.location.pathname.includes('dashboard') || 
        window.location.pathname.includes('admin') ||
        window.location.pathname.includes('student') ||
        window.location.pathname.includes('teacher')) {
        
        const userData = checkAuth();
        if (userData) {
            loadUserData();
        }
    }
}
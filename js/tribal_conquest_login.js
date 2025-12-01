// Tribal Conquest Login/Registration Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            document.getElementById(targetTab + '-tab').classList.add('active');
        });
    });
    
    // Form validation
    const loginForm = document.querySelector('#login-tab form');
    const registerForm = document.querySelector('#register-tab form');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                showMessage('Please fill in all fields', 'error');
            }
        });
    }
    
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const username = document.getElementById('reg_username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('reg_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!username || !email || !password || !confirmPassword) {
                e.preventDefault();
                showMessage('Please fill in all fields', 'error');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showMessage('Passwords do not match', 'error');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                showMessage('Password must be at least 6 characters long', 'error');
                return;
            }
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                showMessage('Please enter a valid email address', 'error');
                return;
            }
        });
    }
    
    // Show message function
    function showMessage(message, type) {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.success-message, .error-message');
        existingMessages.forEach(msg => msg.remove());
        
        // Create new message
        const messageDiv = document.createElement('p');
        messageDiv.className = type === 'error' ? 'error-message' : 'success-message';
        messageDiv.textContent = message;
        
        // Insert message before the active form
        const activeTab = document.querySelector('.tab-content.active');
        const form = activeTab.querySelector('form');
        form.parentNode.insertBefore(messageDiv, form);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            messageDiv.style.opacity = '0';
            messageDiv.style.transition = 'opacity 0.5s ease';
            setTimeout(() => messageDiv.remove(), 500);
        }, 5000);
    }
    
    // Add enter key support for better UX
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const form = this.closest('form');
                if (form) {
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            }
        });
    });
    
    // Add visual feedback for input focus
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
    
    // Animate elements on page load
    const parchmentFrame = document.querySelector('.parchment-frame');
    if (parchmentFrame) {
        setTimeout(() => {
            parchmentFrame.style.transform = 'scale(1)';
        }, 100);
    }
    
    // Add particle effect (optional - medieval dust particles)
    createParticles();
});

// Optional: Create floating particle effect for atmosphere
function createParticles() {
    const particleCount = 20;
    const background = document.querySelector('.login-background');
    
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.cssText = `
            position: absolute;
            width: 2px;
            height: 2px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            pointer-events: none;
            left: ${Math.random() * 100}%;
            top: ${Math.random() * 100}%;
            animation: float ${5 + Math.random() * 10}s linear infinite;
            animation-delay: ${Math.random() * 5}s;
        `;
        background.appendChild(particle);
    }
    
    // Add CSS animation for particles
    if (!document.getElementById('particle-animation')) {
        const style = document.createElement('style');
        style.id = 'particle-animation';
        style.textContent = `
            @keyframes float {
                0% {
                    transform: translateY(0) translateX(0);
                    opacity: 0;
                }
                10% {
                    opacity: 0.5;
                }
                90% {
                    opacity: 0.5;
                }
                100% {
                    transform: translateY(-100vh) translateX(${Math.random() * 100 - 50}px);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Password strength indicator (optional enhancement)
function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z\d]/.test(password)) strength++;
    
    return strength;
}

// Add password strength indicator to register form
const regPasswordInput = document.getElementById('reg_password');
if (regPasswordInput) {
    regPasswordInput.addEventListener('input', function() {
        const strength = checkPasswordStrength(this.value);
        const strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        const strengthColors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997'];
        
        let indicator = document.getElementById('password-strength');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'password-strength';
            indicator.style.cssText = `
                margin-top: 5px;
                font-size: 0.85rem;
                font-weight: bold;
                transition: color 0.3s ease;
            `;
            this.parentElement.appendChild(indicator);
        }
        
        if (this.value.length > 0) {
            indicator.textContent = `Password Strength: ${strengthText[strength]}`;
            indicator.style.color = strengthColors[strength];
        } else {
            indicator.textContent = '';
        }
    });
}

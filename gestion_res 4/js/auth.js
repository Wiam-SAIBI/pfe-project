// js/auth.js - Script d'authentification et gestion de session

// Vérification de l'authentification sur toutes les pages protégées
function checkAuthentication() {
    const sessionData = localStorage.getItem('marsaUserSession') || 
                       sessionStorage.getItem('marsaUserSession');
    
    if (!sessionData) {
        // Pas de session, rediriger vers login
        window.location.href = 'login.html';
        return null;
    }
    
    try {
        const userData = JSON.parse(sessionData);
        
        // Vérifier si la session n'est pas expirée (24h)
        const now = new Date().getTime();
        const sessionAge = now - userData.timestamp;
        
        if (sessionAge > 24 * 60 * 60 * 1000) { // 24 heures
            // Session expirée
            localStorage.removeItem('marsaUserSession');
            sessionStorage.removeItem('marsaUserSession');
            window.location.href = 'login.html';
            return null;
        }
        
        return userData;
    } catch (e) {
        console.error('Erreur lors de la lecture de la session:', e);
        window.location.href = 'login.html';
        return null;
    }
}

// Fonction de déconnexion
function logout() {
    localStorage.removeItem('marsaUserSession');
    sessionStorage.removeItem('marsaUserSession');
    window.location.href = 'login.html';
}

// Initialisation de l'authentification pour chaque page
function initAuth() {
    const currentPage = window.location.pathname.split('/').pop();
    
    // Pages qui ne nécessitent pas d'authentification
    const publicPages = ['login.html', 'index.html'];
    
    if (!publicPages.includes(currentPage)) {
        const userData = checkAuthentication();
        if (userData) {
            // Mettre à jour les informations utilisateur dans l'interface
            updateUserInterface(userData);
        }
    }
}

// Mettre à jour l'interface utilisateur avec les données de session
function updateUserInterface(userData) {
    // Mettre à jour le nom d'utilisateur
    const userElements = document.querySelectorAll('.user-email');
    userElements.forEach(el => {
        el.textContent = userData.user.email;
    });
    
    // Mettre à jour le rôle
    const roleElements = document.querySelectorAll('.user-role');
    roleElements.forEach(el => {
        el.textContent = userData.user.role;
    });
    
    // Cacher les éléments admin pour les utilisateurs normaux
    if (userData.user.role !== 'ADMIN') {
        const adminElements = document.querySelectorAll('.admin-only');
        adminElements.forEach(el => {
            el.style.display = 'none';
        });
    }
}

// Fonction pour faire des requêtes API authentifiées
function makeAuthenticatedRequest(url, options = {}) {
    const sessionData = localStorage.getItem('marsaUserSession') || 
                       sessionStorage.getItem('marsaUserSession');
    
    if (!sessionData) {
        window.location.href = 'login.html';
        return Promise.reject('Non authentifié');
    }
    
    try {
        const userData = JSON.parse(sessionData);
        
        // Ajouter le token aux headers
        options.headers = options.headers || {};
        options.headers['Authorization'] = 'Bearer ' + userData.token;
        
        return fetch(url, options);
    } catch (e) {
        window.location.href = 'login.html';
        return Promise.reject('Session invalide');
    }
}

// Gestion du thème
function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.body.className = savedTheme === 'dark' ? 'theme-dark' : 'theme-light';
}

function toggleTheme() {
    const isDark = document.body.classList.contains('theme-dark');
    const newTheme = isDark ? 'light' : 'dark';
    
    document.body.className = newTheme === 'dark' ? 'theme-dark' : 'theme-light';
    localStorage.setItem('theme', newTheme);
}

// Auto-initialisation
document.addEventListener('DOMContentLoaded', function() {
    initAuth();
    initTheme();
});
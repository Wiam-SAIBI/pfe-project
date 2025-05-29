// Fonctions utilitaires communes

// Declare the angular variable before using it
var angular = window.angular

var app = angular.module("commonApp", [])

// Filtre pour formater les dates
app.filter("formatDate", () => (dateString, format) => {
  if (!dateString) return ""
  var date = new Date(dateString)

  if (format === "dd/MM/yyyy") {
    return (
      String(date.getDate()).padStart(2, "0") +
      "/" +
      String(date.getMonth() + 1).padStart(2, "0") +
      "/" +
      date.getFullYear()
    )
  } else if (format === "dd/MM/yyyy HH:mm") {
    return (
      String(date.getDate()).padStart(2, "0") +
      "/" +
      String(date.getMonth() + 1).padStart(2, "0") +
      "/" +
      date.getFullYear() +
      " " +
      String(date.getHours()).padStart(2, "0") +
      ":" +
      String(date.getMinutes()).padStart(2, "0")
    )
  } else {
    return date.toLocaleDateString("fr-FR")
  }
})

// Directive pour confirmer une action
app.directive("confirmAction", () => ({
  restrict: "A",
  scope: {
    confirmAction: "&",
    confirmMessage: "@",
    confirmTitle: "@",
  },
  link: (scope, element, attrs) => {
    element.bind("click", (e) => {
      e.preventDefault()

      if (window.confirm(scope.confirmMessage || "Êtes-vous sûr ?")) {
        scope.$apply(scope.confirmAction)
      }
    })
  },
}))

// Service pour les notifications
app.service("NotificationService", () => {
  var notifications = []

  return {
    add: function (type, message, timeout) {
      if (timeout === undefined) timeout = 5000

      var notification = {
        id: Date.now(),
        type: type,
        message: message,
      }

      notifications.push(notification)

      if (timeout) {
        
        setTimeout(() => {
          this.remove(notification.id)
        }, timeout)
      }

      return notification
    },

    remove: (id) => {
      var index = notifications.findIndex((n) => n.id === id)
      if (index !== -1) {
        notifications.splice(index, 1)
      }
    },

    getAll: () => notifications,

    clear: () => {
      notifications.length = 0
    },
  }
})

// Fonction pour formater les nombres
app.filter("number", () => (value, decimals) => {
  if (decimals === undefined) decimals = 0
  if (isNaN(value)) return "0"
  return Number.parseFloat(value)
    .toFixed(decimals)
    .replace(/\B(?=(\d{3})+(?!\d))/g, " ")
})

// Fonction pour tronquer le texte
app.filter("truncate", () => (text, length, end) => {
  if (end === undefined) end = "..."
  if (!text) return ""
  if (text.length <= length) return text

  return text.substring(0, length) + end
})

// Fonction pour convertir en majuscules la première lettre
app.filter("capitalize", () => (text) => {
  if (!text) return ""
  return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase()
})

// Fonction pour vérifier les permissions
app.factory("PermissionService", () => ({
  hasPermission: (user, permission) => {
    if (!user || !user.role) return false

    // Administrateur a toutes les permissions
    if (user.role === "ADMIN") return true

    // Vérifier les permissions spécifiques pour les autres rôles
    var userPermissions = {
      USER: ["read"],
      MANAGER: ["read", "write"],
      SUPERVISOR: ["read", "write", "update"],
    }

    return userPermissions[user.role] && userPermissions[user.role].includes(permission)
  },
}))
# Todo Pro

TODO list moderne en **PHP pur** — aucune base de données, aucune dépendance.
Stockage dans un fichier JSON local. Fonctionne partout où PHP tourne.

## Fonctionnalités

- Ajout de tâches avec **catégorie**, **priorité** (1-10), **date limite**
- **Catégories** prédéfinies : Personnel, Travail, Études, Santé, Finances, Loisirs, Autre
- **Recherche** en temps réel
- **Filtres** : toutes / à faire / terminées / par catégorie
- **Tri** : plus récent, plus ancien, priorité, alphabétique
- **Stats** : total, à faire, terminées, % de progression
- **Dark mode** (persisté via cookie)
- **Export** des données au format JSON
- **Import** depuis fichier JSON
- **Nettoyage** des tâches terminées
- **CSRF protection** sur tous les formulas
- **Interface AJAX** : ajout et filtres sans rechargement de page
- **Responsive** : mobile-friendly
- **Icons SVG** intégrés (aucun emoji)

## Installation

```bash
# 1. Place les deux fichiers dans le même dossier
todo.php
todo_data.json   (peut être vide : [])

# 2. Point ton navigateur vers todo.php
http://localhost/todo.php
```

Pas de configuration nécessaire. PHP 7.4+ recommandé.

## Stockage

Toutes les données sont dans `todo_data.json` — un simple fichier JSON :

```json
[
  {
    "id": "a1b2c3d4",
    "task": "Faire les courses",
    "done": false,
    "category": "Personnel",
    "priority": 5,
    "due": "2026-01-15",
    "created": 1736947200,
    "completed": null
  }
]
```

## Todo

- [ ] Animations CSS plus fluides
- [ ] Mode super-utilisateur pour l'admin
- [ ] Synchronisation cloud optionnelle
- [ ] API REST (pour client JS externe)

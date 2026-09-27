# git-repo-finder — OSINT : retrouve l'URL du dépôt Git d'un site web

Outil CLI en Python (3.x) qui explore un site web pour retrouver l'URL de son dépôt Git public. 100% gratuit, sans clé API, sans compte.

## Installation

```bash
cd /home/kali
git clone <ce-repo>   # ou copie directe de git-repo-finder.py
python -m venv .venv && source .venv/bin/activate
pip install requests dnspython
```

Sur Kali Linux, les dépendances sont probablement déjà installées. Sinon :

```bash
pip install -r requirements.txt
```

## Utilisation

```bash
# Mode texte (par défaut)
python git-repo-finder.py https://exemple.com

# Mode JSON (pour automation / pipeline)
python git-repo-finder.py https://exemple.com --json
```

### Exemples concrets

```bash
python git-repo-finder.py https://www.python.org
python git-repo-finder.py https://github.com
python git-repo-finder.py https://nodejs.org
python git-repo-finder.py https://flask.palletsprojects.com
python git-repo-finder.py https://getpelican.com
python git-repo-finder.py https://www.sphinx-doc.org
python git-repo-finder.py https://www.djangoproject.com
```

## Techniques implémentées

| # | Technique | Description |
|---|-----------|-------------|
| 1 | **Exposition .git** | Teste si le répertoire `.git` est exposé (HEAD, config, index, description, etc.) — faille de déploiement |
| 2 | **Service git HTTP** | Détecte un dépôt git servi via HTTP smart protocol (`/info/refs?service=git-upload-pack`) — équivalent `git ls-remote` |
| 3 | **Scraping HTML** | Extrait les URLs de dépôts publics (GitHub, GitLab, Bitbucket, SourceForge, Framagit) depuis le code HTML de la page |
| 4 | **Fichiers standards** | Vérifie la présence de `security.txt`, `robots.txt`, `sitemap.xml`, `manifest.json`, `README.md`, `LICENSE`, `humans.txt` et extrait d'éventuels liens de dépôt |
| 5 | **Méta-données & headers** | Analyse les headers HTTP (`X-Git-Repo`, `X-Powered-By`, `Server`), les méta tags (`generator`), les commentaires HTML, les source maps |
| 6 | **Inférences de domaine** | Devine des URLs de dépôt probables à partir du nom de domaine (ex: `myproject.github.io` → `github.com/myproject/myproject`) |

## Sortie

- **Mode texte** : rapport formaté avec tous les liens captés, étiquetés `[depot]` ou `[page-produit GH]` (indice heuristique, non filtrant).
- **Mode JSON** : objet complet avec tous les liens, étiquettes, fichiers standards, méta-données, etc.

## Liens captés

L'outil capture les URLs suivantes depuis le HTML :

- `https://github.com/<owner>/<repo>`
- `https://gitlab.com/<owner>/<repo>`
- `https://bitbucket.org/<owner>/<repo>`
- `https://sourceforge.net/p/<project>/<repo>`
- `https://framagit.org/<owner>/<repo>`

## Limitations

- **Pas de clonage** : l'outil ne clone pas le dépôt, il seulement le localise
- **Pas de dépôts privés** : seuls les dépôts publics liés depuis le HTML sont trouvables
- **Sites sans lien vers le dépôt** : si le site ne lie pas son dépôt (généré sans référence publique), l'outil ne peut pas le trouver
- **Rate limiting** : les sites protégés (Cloudflare, Vercel, etc.) peuvent bloquer les requêtes ; l'outil les signale mais ne les contourne pas
- **Précision des étiquettes** : l'étiquetage `[depot]` / `[page-produit GH]` est heuristique et peut comporter des faux positifs/négatifs ; il n'impacte pas les résultats

## Éthique

Cet outil est conçu pour l'OSINT et l'analyse publique. Il ne fait que lire des données déjà publiques (HTML, headers, fichiers standards). Il ne exploite aucune vulnérabilité et ne tente pas de contourner des mesures de protection.

Utilisez-le de manière responsable et légale.

#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
git-repo-finder — OSINT : retrouve l'URL du dépôt Git d'un site web.

Techniques (100% free, sans API key) :
  1. Exposition .git/HEAD, .git/config, ... (si mauvaise configuration serveur)
  2. Détection service git HTTP (git ls-remote over HTTP)
  3. Scraping HTML : liens GitHub/GitLab/Bitbucket dans le code source
  4. Fichiers standards : security.txt, robots.txt, sitemap, source maps, humans.txt
  5. Méta-données & headers : generator meta, commentaires, X-Headers
  6. Inférences probabilistes basées sur le nom de domaine (patterns github.io, gitlab.io)

TOUS les liens captés sont affichés. Aucun lien n'est supprimé ni filté.
L'étiquetage [depot]/[page-produit] est purement indicatif.

Usage :
  python git-repo-finder.py https://exemple.com
  python git-repo-finder.py https://exemple.com --json
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from urllib.parse import urlparse

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

# ---------------------------------------------------------------------------
# Session HTTP partagée
# ---------------------------------------------------------------------------

SESSION = requests.Session()
SESSION.headers.update(
    {
        "User-Agent": "git-repo-finder/1.0 (OSINT research; free tier; contact: local)",
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language": "fr,fr-FR;q=0.9,en;q=0.8",
    }
)
_retry = Retry(total=2, backoff_factor=1, status_forcelist=(429, 500, 502, 503, 504))
_SESSION_ADAPTER = HTTPAdapter(max_retries=_retry)
SESSION.mount("https://", _SESSION_ADAPTER)
SESSION.mount("http://", _SESSION_ADAPTER)

TIMEOUT = 20


# ---------------------------------------------------------------------------
# Normalisation
# ---------------------------------------------------------------------------


def normalize_url(url: str) -> tuple[str, urlparse]:
    """Normalise l'URL : ajoute https:// si absent, extrait le domaine de base."""
    url = url.strip()
    if not url.startswith(("http://", "https://")):
        url = "https://" + url
    parsed = urlparse(url)
    base = f"{parsed.scheme}://{parsed.netloc}"
    return base, parsed


# ---------------------------------------------------------------------------
# Technique 1 · Exposition .git
# ---------------------------------------------------------------------------

GIT_EXPOSURE_PATHS = [
    "/.git/HEAD",
    "/.git/config",
    "/.git/index",
    "/.git/objects/info/packs",
    "/.git/refs/heads/master",
    "/.git/refs/heads/main",
    "/.git/description",
]


def check_git_exposure(domain: str) -> dict:
    """Teste si le répertoire .git est exposé (déploiement défaillant)."""
    result: dict = {
        "exposed": False,
        "paths_found": [],
        "head_content": None,
        "config_content": None,
        "description_content": None,
    }
    for path in GIT_EXPOSURE_PATHS:
        probe = f"{domain}{path}"
        try:
            resp = SESSION.get(probe, timeout=TIMEOUT, allow_redirects=False)
        except requests.RequestException:
            continue
        if resp.status_code != 200:
            continue
        result["paths_found"].append(path)
        text = resp.text
        if path.endswith("HEAD"):
            head = text.strip()
            if head.startswith("ref: "):
                result["head_content"] = head
            elif re.match(r"^[0-9a-f]{40}$", head):
                result["head_content"] = f"detached HEAD -> {head}"
            else:
                result["head_content"] = head
            result["exposed"] = True
        elif path.endswith("config") and "core" in text:
            result["config_content"] = text[:2000]
            result["exposed"] = True
        elif path.endswith("description"):
            result["description_content"] = text.strip()
    return result


# ---------------------------------------------------------------------------
# Technique 2 · Service git HTTP (smart HTTP)
# ---------------------------------------------------------------------------


def check_git_http_service(domain: str) -> dict:
    """git ls-remote over HTTP : GET /info/refs?service=git-upload-pack."""
    result: dict = {
        "service_available": False,
        "service_type": None,
        "refs": [],
        "repo_url_candidates": [],
    }
    url = f"{domain}/info/refs?service=git-upload-pack"
    try:
        resp = SESSION.get(url, timeout=TIMEOUT, headers={"Accept": "*/*"})
    except requests.RequestException:
        return result
    if resp.status_code != 200:
        return result
    if "ref " not in resp.text:
        return result
    result["service_available"] = True
    result["service_type"] = "smart-http"
    refs = re.findall(r"^ref:\s+(\S+)", resp.text, re.MULTILINE)
    result["refs"] = refs[:20]
    result["repo_url_candidates"] = [domain, f"{domain}.git"]
    return result


# ---------------------------------------------------------------------------
# Technique 3 · Scraping HTML pour liens de dépôt
# ---------------------------------------------------------------------------

GIT_HOST_PATTERNS: dict[str, tuple[str, re.Pattern]] = {
    "GitHub": ("github.com", re.compile(
        r"github\.com/(?:[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?/[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?)",
        re.I,
    )),
    "GitLab": ("gitlab.com", re.compile(
        r"gitlab\.com/(?:[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?/[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?)",
        re.I,
    )),
    "Bitbucket": ("bitbucket.org", re.compile(
        r"bitbucket\.org/(?:[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?/[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?)",
        re.I,
    )),
    "SourceForge": ("sourceforge.net", re.compile(
        r"sourceforge\.net/p/(?:[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?)/(?:[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?)",
        re.I,
    )),
    "Framagit": ("framagit.org", re.compile(
        r"framagit\.org/(?:[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?/[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?)",
        re.I,
    )),
}

# Sections GitHub de premier niveau qui sont des pages produit (pas des dépôts)
# Used ONLY for label display — never for filtering.
GIT_PRODUCT_SECTIONS: frozenset[str] = frozenset({
    "features", "solutions", "topics", "marketplace", "explore",
    "copilot", "docs", "training", "blog", "about", "contact",
    "search", "collections", "sponsorships", "jobs", "students",
    "teachers", "developers", "businesses", "pricing", "download",
    "integrations", "actions", "packages", "codespaces",
    "discussions", "issues", "pulls", "projects", "wikis",
    "pages", "apps", "desktop", "mobile", "enterprise",
    "teams", "community", "support", "status", "passports",
    "verify", "campus", "nonprofits", "accelerated", "startups",
    "assets", "media", "images", "avatars", "themes", "languages",
    "licenses", "readme", "feed", "trending", "stars", "network",
    "members", "pipelines", "registry", "deploy", "environments",
    "insights", "analytics", "pulse", "settings", "admin", "hooks",
    "webhooks", "releases", "archive", "branches", "tags", "milestones",
    "assignees", "reviewers", "labels", "runs", "artifacts",
    "dependencies", "checks", "compare", "commits", "history",
    "source", "tree", "files", "find", "code", "cmc_internal",
    "sourcedefense", "users", "orgs", "organizations", "teams",
    "get-started", "security", "resources", "open-source", "site-policy",
})

GITLAB_RESERVED_OWNERS = {
    "img", "users", "hc", "customers", "handbook", "gitlab-com",
    "overview", "topics", "api", "admin", "explore", "dashboard",
    "projects", "groups", "snippets", "profile",
    "compliance", "security", "legal", "about", "blog", "careers",
    "research", "solutions", "partners", "services", "premium",
    "starter", "catalog", "marketplace", "registry",
}


def scrape_html_for_repo_links(html: str) -> dict[str, list[str]]:
    """Extrait les URLs complètes de dépôts publics depuis le HTML.
    Retourne {host: [url, ...]} — TOUS les liens captés, sans filtrage."""
    found: dict[str, list[str]] = {}
    for display_host, (real_host, pattern) in GIT_HOST_PATTERNS.items():
        raw = pattern.findall(html)
        if not raw:
            continue
        seen: set[str] = set()
        unique: list[str] = []
        for m in raw:
            if m in seen:
                continue
            seen.add(m)
            path = m.split("/", 1)[1]  # ex: "python/cpython"
            unique.append(f"https://{real_host}/{path}")
        found[display_host] = unique
    return found


def _guess_repo_label(path: str, real_host: str) -> str:
    """Heuristique rapide pour étiqueter un lien capturé owner/repo.
    Retourne 'depot' ou 'page-produit' selon si l'owner ressemble à une
    section produit GitHub de premier niveau.
    WARNING: cette fonction peut produire des faux positifs/negatifs.
    L'étiquette n'impacte PAS le résultat final."""
    parts = path.split("/")
    if len(parts) != 2:
        return "inconnu"
    owner, repo = parts
    owner_l = owner.lower()
    if real_host == "github.com":
        if owner_l in GIT_PRODUCT_SECTIONS:
            return "page-produit"
        return "depot"
    if real_host == "gitlab.com":
        if owner_l in GITLAB_RESERVED_OWNERS:
            return "page-produit"
        return "depot"
    return "depot"


# ---------------------------------------------------------------------------
# Technique 4 · Fichiers standards
# ---------------------------------------------------------------------------

STANDARD_FILES = [
    "/.well-known/security.txt",
    "/security.txt",
    "/robots.txt",
    "/sitemap.xml",
    "/manifest.json",
    "/README.md",
    "/LICENSE",
    "/humans.txt",
]


def fetch_standard_files(domain: str) -> dict[str, dict]:
    """Récupère les fichiers standards qui peuvent contenir des indices."""
    out: dict[str, dict] = {}
    for path in STANDARD_FILES:
        url = f"{domain}{path}"
        try:
            resp = SESSION.get(url, timeout=TIMEOUT, allow_redirects=False)
        except requests.RequestException:
            continue
        if resp.status_code != 200:
            continue
        out[path.lstrip("/")] = {
            "status": resp.status_code,
            "content_type": resp.headers.get("content-type", ""),
            "length": len(resp.content),
            "text": resp.text[:3000],
        }
    return out


def extract_hints_from_standard_files(files: dict[str, dict]) -> list[str]:
    """Indices de dépôt issus des fichiers standards."""
    hints: list[str] = []
    for name, data in files.items():
        text = data.get("text", "")
        for display_host, (real_host, pattern) in GIT_HOST_PATTERNS.items():
            for m in pattern.findall(text):
                path = m.split("/", 1)[1] if "/" in m else m
                label = _guess_repo_label(path, real_host)
                label_str = {
                    "depot": "[depot]",
                    "page-produit": "[page-produit GH]",
                    "inconnu": "[?]",
                }.get(label, f"[{label}]")
                hints.append(f"{name} : {display_host} {label_str} : https://{real_host}/{path}")
        if name in ("security.txt", "robots.txt") and re.search(r"\bgit\b", text, re.I):
            hints.append(f"{name} : mot 'git' détecté")
    return hints


# ---------------------------------------------------------------------------
# Technique 5 · Méta-données & headers
# ---------------------------------------------------------------------------


def analyze_page(domain: str) -> dict:
    """Récupère et analyse la page d'accueil."""
    try:
        resp = SESSION.get(domain, timeout=TIMEOUT)
    except requests.RequestException as exc:
        return {"error": str(exc)}
    html = resp.text
    raw_links = scrape_html_for_repo_links(html)
    out: dict = {
        "status": resp.status_code,
        "content_type": resp.headers.get("content-type", ""),
        "server": resp.headers.get("server", ""),
        "x_powered_by": resp.headers.get("x-powered-by", ""),
        "x_git_repo": resp.headers.get("x-git-repo")
        or resp.headers.get("x-repo-url")
        or resp.headers.get("x-source-repo"),
        "generator_meta": None,
        "repo_links": raw_links,
        "repo_links_with_labels": {},
        "git_mentions": [],
        "source_map_hints": [],
        "html_comments": [],
    }
    # Ajouter les étiquettes pour chaque lien
    for display_host, urls in raw_links.items():
        real_host = real_host_from_display_host(display_host)
        labeled: dict[str, str] = {}
        for url in urls:
            parsed_url = urlparse(url)
            path = parsed_url.path.strip("/")
            label = _guess_repo_label(path, real_host)
            labeled[url] = label
        out["repo_links_with_labels"][display_host] = labeled
    m = re.search(r'<meta\s+name="generator"\s+content="([^"]+)"', html, re.I)
    if m:
        out["generator_meta"] = m.group(1)
    for keyword in ("git", "github", "gitlab", "bitbucket", "repository", "dépôt", "depot", "source"):
        if re.search(rf"\b{re.escape(keyword)}\b", html, re.I):
            out["git_mentions"].append(keyword)
    comments = re.findall(r"<!--(.*?)-->", html, re.S)
    out["html_comments"] = [c.strip()[:200] for c in comments if c.strip()][:5]
    map_refs = re.findall(r'[^"\']\S*\.map', html)
    if map_refs:
        out["source_map_hints"] = list(dict.fromkeys(map_refs))[:10]
    return out


def real_host_from_display_host(display_host: str) -> str:
    """Retourne le hostname réel depuis le nom d'affichage."""
    for dhost, (rhost, _pattern) in GIT_HOST_PATTERNS.items():
        if dhost == display_host:
            return rhost
    return display_host


# ---------------------------------------------------------------------------
# Technique 6 · Inférences probabilistes
# ---------------------------------------------------------------------------


def guess_repo_urls_from_domain(domain: str) -> list[str]:
    """Émet des hypothèses d'URL de dépôts depuis le nom de domaine."""
    parsed = urlparse(domain)
    host = parsed.netloc.lower()
    host_clean = re.sub(r"^www\.", "", host)
    candidates: list[str] = []

    m = re.match(r"([^.]+)\.github\.io$", host_clean)
    if m:
        proj = m.group(1)
        candidates.extend([f"https://github.com/{proj}/{proj}", f"https://github.com/{proj}"])

    m = re.match(r"([^.]+)\.gitlab\.io$", host_clean)
    if m:
        proj = m.group(1)
        candidates.extend([f"https://gitlab.com/{proj}/{proj}", f"https://gitlab.com/{proj}"])

    return candidates


# ---------------------------------------------------------------------------
# Orchestre
# ---------------------------------------------------------------------------


def find_git_repo(url: str) -> dict:
    """Orchestre toutes les techniques et retourne le rapport."""
    base_url, parsed = normalize_url(url)
    domain = base_url

    report: dict = {
        "target": url,
        "base_url": base_url,
        "domain": parsed.netloc,
        "techniques": {},
        "repo_candidates": [],
        "hints_from_files": [],
        "conclusion": "",
    }

    # 1
    report["techniques"]["git_exposure"] = check_git_exposure(domain)
    # 2
    report["techniques"]["git_http_service"] = check_git_http_service(domain)
    # 5
    page = analyze_page(domain)
    report["techniques"]["page_analysis"] = page
    # 4
    files = fetch_standard_files(domain)
    report["techniques"]["standard_files"] = files
    report["hints_from_files"] = extract_hints_from_standard_files(files)

    # Compiler les candidats : TOUS les liens captés, sans aucun filtrage
    candidates: list[str] = []
    for host, urls in page.get("repo_links", {}).items():
        candidates.extend(urls)

    svc = report["techniques"].get("git_http_service", {})
    for c in svc.get("repo_url_candidates", []):
        if c not in candidates:
            candidates.append(c)

    guesses = guess_repo_urls_from_domain(domain)
    for g in guesses:
        if g not in candidates:
            candidates.append(g)

    report["repo_candidates"] = candidates

    # Conclusion : basée sur l'existence d'AU MOINS un lien capté (peu importe l'étiquette)
    tech = report["techniques"]
    exposed = tech.get("git_exposure", {}).get("exposed", False)
    service = tech.get("git_http_service", {}).get("service_available", False)
    links = page.get("repo_links", {})
    any_links = any(links.values())

    if exposed:
        report["conclusion"] = (
            "Le répertoire .git est EXPOSÉ sur ce site — le dépôt est directement accessible. "
            "C'est une faille de déploiement. Explorez les chemins listés pour récupérer l'intégralité du dépôt."
        )
    elif service:
        report["conclusion"] = (
            "Un service git HTTP est détecté sur ce domaine. "
            f"Le dépôt peut être cloné via : {domain} (ou {domain}.git)"
        )
    elif any_links:
        report["conclusion"] = (
            "Des liens vers des dépôts publics (GitHub/GitLab/Bitbucket) ont été trouvés dans le code HTML."
        )
    elif guesses:
        report["conclusion"] = (
            "Aucune preuve directe trouvée. Voici des hypothèses basées sur le domaine (à vérifier manuellement)."
        )
    else:
        report["conclusion"] = (
            "Aucune trace de dépôt git trouvée via les techniques disponibles (exposition .git, service git HTTP, "
            "scraping HTML, fichiers standards). Le dépôt peut être privé, hébergé sur une plateforme non couverte, "
            "ou le site est généré sans lien public vers le repository."
        )

    if exposed and report["techniques"]["git_exposure"].get("head_content"):
        report["git_head"] = report["techniques"]["git_exposure"]["head_content"]

    return report


# ---------------------------------------------------------------------------
# Affichage texte
# ---------------------------------------------------------------------------


def print_report(report: dict) -> None:
    print("\n" + "=" * 60)
    print(" GIT REPO FINDER — Rapport OSINT")
    print("=" * 60)
    print(f" Cible       : {report['target']}")
    print(f" Domaine     : {report['domain']}")
    print(f" Base URL    : {report['base_url']}")

    print("\n--- Technique 1 : Exposition .git ---")
    t = report["techniques"].get("git_exposure", {})
    if t.get("exposed"):
        print("  [OK] .git EXPOSÉ (faille de déploiement)")
        print(f"  HEAD : {t.get('head_content', 'n/a')}")
        print(f"  Chemins trouvés : {len(t.get('paths_found', []))}")
        for p in t.get("paths_found", []):
            print(f"    - {p}")
        if t.get("config_content"):
            print("  Config .git (extrait) :")
            for line in t["config_content"].splitlines()[:12]:
                print(f"    {line}")
    else:
        print("  [FAIL] Aucun fichier .git accessible (ou non trouvé)")

    print("\n--- Technique 2 : Service git HTTP ---")
    t = report["techniques"].get("git_http_service", {})
    if t.get("service_available"):
        print(f"  [OK] Service détecté : {t.get('service_type')}")
        print(f"  Refs ({len(t.get('refs', []))}) : {', '.join(t.get('refs', [])[:10])}")
        print(f"  URL de dépôt candidate(s) : {', '.join(t.get('repo_url_candidates', []))}")
    else:
        print("  [FAIL] Aucun service git HTTP détecté")

    print("\n--- Technique 3-5 : Analyse page & fichiers standards ---")
    page = report["techniques"].get("page_analysis", {})
    if "error" in page:
        print(f"  [ERREUR] {page['error']}")
    else:
        print(f"  Status page      : {page.get('status')}")
        print(f"  Server          : {page.get('server') or 'n/a'}")
        print(f"  X-Powered-By    : {page.get('x_powered_by') or 'n/a'}")
        gx = page.get("x_git_repo")
        if gx:
            print(f"  Header X-Git-Repo : {gx}")
        gen = page.get("generator_meta")
        if gen:
            print(f"  Meta generator   : {gen}")
        mentions = page.get("git_mentions", [])
        if mentions:
            print(f"  Mots-clés HTML   : {', '.join(sorted(set(mentions)))}")
        comments = page.get("html_comments", [])
        if comments:
            print("  Commentaires HTML :")
            for c in comments:
                print(f"    <!-- {c} -->")
        maps = page.get("source_map_hints", [])
        if maps:
            print(f"  Source maps référencés : {len(maps)}")
            for m in maps[:5]:
                print(f"    - {m}")

        links = page.get("repo_links", {})
        if links:
            print("  Liens de dépôts trouvés dans le HTML (TOUS, sans filtrage) :")
            for display_host, urls in links.items():
                real_host = real_host_from_display_host(display_host)
                for url in urls:
                    parsed_url = urlparse(url)
                    path = parsed_url.path.strip("/")
                    label = _guess_repo_label(path, real_host)
                    label_str = {
                        "depot": "[depot]",
                        "page-produit": "[page-produit GH]",
                        "inconnu": "[?]",
                    }.get(label, f"[{label}]")
                    print(f"    - {display_host} {label_str} : {url}")

    print("\n  Fichiers standards accessibles :")
    files = report["techniques"].get("standard_files", {})
    for name in STANDARD_FILES:
        key = name.lstrip("/")
        if key in files:
            d = files[key]
            print(f"    [OK] {name} ({d['length']} octets, {d['content_type']})")
        else:
            print(f"    [FAIL] {name}")

    hints = report.get("hints_from_files", [])
    if hints:
        print("\n  Indices depuis fichiers standards :")
        for h in hints:
            print(f"    - {h}")

    print("\n--- Candidats URL de dépôt (TOUS les liens captés, aucun filtrage) ---")
    cands = report["repo_candidates"]
    if cands:
        for c in cands:
            print(f"  → {c}")
    else:
        print("  Aucun candidat")

    print("\n--- Conclusion ---")
    print(f" {report['conclusion']}")
    print("\n" + "=" * 60)


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(
        prog="git-repo-finder.py",
        description="OSINT : retrouve l'URL du dépôt Git d'un site web (100% free, sans clé).",
    )
    p.add_argument("url", help="URL du site (ex: https://exemple.com)")
    p.add_argument("--json", action="store_true", help="Sortie JSON (pour pipeline)")
    return p


def main() -> None:
    parser = build_parser()
    args = parser.parse_args()
    report = find_git_repo(args.url)
    if args.json:
        print(json.dumps(report, indent=2, ensure_ascii=False))
    else:
        print_report(report)


if __name__ == "__main__":
    main()

# DiviToElementor — Migration Plugin

<!-- SECTION:overview -->
## Vue d'ensemble

Plugin WordPress (PHP 8.1+) permettant la migration de contenus Divi Builder vers Elementor.

Le projet fournit un parseur récursif de shortcodes Divi (`[et_pb_*]`) qui construit un arbre de nœuds typés (`DiviNode`), avec détection automatique du contenu encodé en base64, gestion de la profondeur maximale d'imbrication et classification supported/unsupported/malformed de chaque module.

| Élément | Détail |
|---------|--------|
| **Stack** | PHP 8.1+, PHPUnit 10, Brain\Monkey, PSR-3 Logger |
| **Type** | `wordpress-plugin` |
| **Licence** | GPL-2.0-or-later |
| **Namespace** | `DiviToElementor\` |
<!-- END:overview -->

<!-- SECTION:getting-started -->
## Démarrage rapide

### Prérequis

- PHP >= 8.1
- Composer

### Installation

```bash
composer install
```

### Lancer les tests unitaires

```bash
./vendor/bin/phpunit --testsuite Unit
```

### Lancer les tests d'intégration

Les tests d'intégration nécessitent un environnement WordPress (wp-env ou `WP_TESTS_DIR` défini).

```bash
./vendor/bin/phpunit --testsuite Integration
```
<!-- END:getting-started -->

<!-- SECTION:architecture -->
## Architecture

```
src/
└── Parser/
    ├── DiviParser.php          # Parseur récursif — point d'entrée principal
    ├── DiviNode.php            # Value object représentant un nœud de l'arbre
    └── DiviShortcodeType.php   # Enum PHP 8.1 — catalogue des shortcodes Divi

tests/
├── bootstrap.php
├── Unit/
│   └── Parser/
│       └── DiviParserTest.php              # Tests unitaires (Brain\Monkey)
└── Integration/
    └── Parser/
        └── DiviParserIntegrationTest.php   # Tests d'intégration (WP_UnitTestCase)
```

### Flux de parsing

1. `DiviParser::parse(int $postId)` — récupère le `post_content` via WordPress.
2. Détection et décodage base64 automatique (`decodeIfEncoded`).
3. Protection ReDoS : troncature à 500 000 caractères.
4. Parsing récursif (`parseShortcodes`) avec profondeur max de 10 niveaux.
5. Construction d'un arbre de `DiviNode` avec statut (`supported`, `unsupported`, `malformed`).
<!-- END:architecture -->

<!-- SECTION:features -->
## Modules supportés

### Modules structurels

| Shortcode | Type |
|-----------|------|
| `et_pb_section` | Section |
| `et_pb_row` / `et_pb_row_inner` | Row |
| `et_pb_column` / `et_pb_column_inner` | Column |

### Modules de contenu (mapping Elementor défini)

`et_pb_text`, `et_pb_image`, `et_pb_button`, `et_pb_cta`, `et_pb_video`, `et_pb_code`, `et_pb_divider`, `et_pb_blurb`, `et_pb_tabs`, `et_pb_tab`, `et_pb_toggle`, `et_pb_accordion`, `et_pb_slider`, `et_pb_slide`, `et_pb_gallery`

### Modules non supportés (pas de mapping Elementor)

`et_pb_fullwidth_slider`, `et_pb_fullwidth_header`, `et_pb_portfolio`, `et_pb_shop`, `et_pb_countdown_timer`, `et_pb_pricing_table`

### Fonctionnalités du parseur

- Décodage base64 automatique du contenu Divi
- Gestion des shortcodes imbriqués (même type)
- Détection des shortcodes non fermés (statut `malformed`)
- Limite de profondeur récursive (10 niveaux)
- Protection ReDoS (troncature à 500k caractères)
- Fallback regex pour le parsing d'attributs (hors contexte WordPress)
- Logging PSR-3 (warnings pour contenu manquant, tronqué ou malformé)
<!-- END:features -->

<!-- SECTION:test-coverage -->
## Couverture de tests

### Tests unitaires (12 tests)

| Test | Couverture |
|------|-----------|
| `testParseReturnsEmptyArrayWhenPostContentIsFalse` | Guard clause — post_content = false |
| `testParseReturnsEmptyArrayWhenPostContentIsEmpty` | Guard clause — post_content vide |
| `testParseReturnsEmptyArrayWhenNoEtPbShortcodes` | Contenu HTML sans shortcodes Divi |
| `testParseReturnsSectionNodeWithChildren` | Happy path — section avec row enfant |
| `testParseShortcodesExtractsAttributes` | Extraction d'attributs (src, alt, align) |
| `testParseShortcodesHandlesUnclosedShortcode` | Shortcode non fermé → statut malformed |
| `testParseShortcodesIgnoresFreeHtml` | HTML libre ignoré autour des shortcodes |
| `testParseShortcodesMarksUnsupportedModules` | Module non supporté → statut unsupported |
| `testParseShortcodesStopsAtMaxDepth` | Profondeur max (10) respectée |
| `testDecodeIfEncodedDecodesBase64Content` | Décodage base64 valide |
| `testDecodeIfEncodedReturnsOriginalWhenNotBase64` | Contenu non-base64 inchangé |
| `testDecodeIfEncodedReturnsEmptyStringOnEmptyInput` | Chaîne vide → chaîne vide |
| `testNodeToArrayHasAllRequiredKeys` | toArray() — 5 clés obligatoires |

### Tests d'intégration (4 tests)

| Test | Couverture |
|------|-----------|
| `testParseRealPostWithDiviContent` | AC-1 — parsing d'un vrai post WordPress |
| `testParseRealPostWithBase64Content` | AC-6 — décodage base64 avec post réel |
| `testParsePostWithMixedHtmlAndShortcodes` | EC-03 — HTML libre ignoré (intégration) |
| `testScenarioOutline10Modules` | AC-8 — 10 modules supported/unsupported |
<!-- END:test-coverage -->

<!-- SECTION:backlog -->
## Backlog

Aucune donnée de backlog disponible — le répertoire `backlog/` n'est pas présent dans ce projet.
<!-- END:backlog -->

<!-- SECTION:configuration -->
## Configuration

Aucun fichier `config/project.json` détecté. Le plugin ne requiert pas de configuration spécifique au-delà de l'installation Composer.

### Dépendances

| Package | Version | Usage |
|---------|---------|-------|
| `psr/log` | ^3.0 | Interface de logging PSR-3 |
| `phpunit/phpunit` | ^10.0 | Framework de tests (dev) |
| `brain/monkey` | ^2.6 | Mock des fonctions WordPress (dev) |
| `php-stubs/wordpress-stubs` | ^6.0 | Stubs WordPress pour l'analyse statique (dev) |
<!-- END:configuration -->

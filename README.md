# DiviToElementor — Migration Plugin

<!-- SECTION:overview -->
## Vue d'ensemble

Plugin WordPress (PHP 8.1+) permettant la migration complète de contenus Divi Builder vers Elementor.

Le pipeline de migration se décompose en 5 étapes :
1. **Parser** — Parseur récursif de shortcodes Divi (`[et_pb_*]`) produisant un arbre de `DiviNode`.
2. **AST** — Normalisation de l'arbre en `AstTree` avec `StyleBag` et `ContentBag` typés.
3. **Mapper** — Transformation de l'AST en structure JSON `_elementor_data` via `WidgetFactory`.
4. **Injector** — Injection des données Elementor dans les meta WordPress avec backup et rollback.
5. **Report** — Génération de rapports par post et globaux, export JSON/CSV.

Une page d'administration WordPress (`Admin`) offre la visualisation des rapports et l'export.

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
├── Admin/
│   └── MigrationAdminPage.php      # Page admin WordPress — rapports + export
├── Ast/
│   ├── AstBuilder.php              # DiviNode[] → AstTree (normalisation styles/data)
│   ├── AstNode.php                 # Nœud AST readonly (type, status, styles, data, children)
│   ├── AstTree.php                 # Conteneur racine — sérialisation JSON
│   ├── ContentBag.php              # Value object : content, src, alt, width
│   └── StyleBag.php                # Value object : background, padding, margin, text, font, color
├── Injector/
│   ├── BatchMigrator.php           # Migration par lots avec try/catch par post
│   ├── BatchResult.php             # Compteurs processed/success/failed + rapports
│   ├── InjectionResult.php         # Résultat d'injection unitaire
│   ├── Injector.php                # Backup + injection meta Elementor + invalidation cache
│   └── RollbackResult.php          # Résultat de rollback unitaire
├── Mapper/
│   ├── ElementorMapper.php         # AstTree → tableau _elementor_data (sections/columns/widgets)
│   ├── FallbackWidget.php          # Widget HTML de secours pour modules non supportés
│   ├── IdGenerator.php             # IDs hex 8 chars cryptographiquement uniques
│   └── WidgetFactory.php           # Dispatch type → widget Elementor (text, image, button, etc.)
├── Parser/
│   ├── DiviParser.php              # Parseur récursif — point d'entrée principal
│   ├── DiviNode.php                # Value object représentant un nœud de l'arbre
│   └── DiviShortcodeType.php       # Enum PHP 8.1 — catalogue des shortcodes Divi
└── Report/
    ├── GlobalReport.php            # Rapport global (total, success, partial, failed, manual_review)
    ├── PostReport.php              # Rapport par post (coverage, fallback, unsupported modules)
    ├── ReportBuilder.php           # Construction PostReport/GlobalReport depuis AST + résultats
    ├── ReportExporter.php          # Export JSON et CSV
    └── ReportStore.php             # Persistance wp_options

tests/
├── bootstrap.php
├── Unit/
│   ├── Admin/
│   │   └── AdminPageTest.php
│   ├── Ast/
│   │   ├── AstBuilderTest.php
│   │   ├── AstNodeWidthRetroTest.php
│   │   ├── AstSerializationTest.php
│   │   └── StyleBagTest.php
│   ├── Injector/
│   │   ├── BatchMigratorTest.php
│   │   ├── InjectorTest.php
│   │   └── RollbackTest.php
│   ├── Mapper/
│   │   ├── ElementorMapperTest.php
│   │   ├── FallbackWidgetTest.php
│   │   ├── IdGeneratorTest.php
│   │   └── WidgetFactoryTest.php
│   ├── Parser/
│   │   ├── DiviParserTest.php
│   │   └── DiviShortcodeTypeRetroTest.php
│   └── Report/
│       ├── ReportBuilderTest.php
│       ├── ReportExporterTest.php
│       └── ReportStoreTest.php
└── Integration/
    └── Parser/
        └── DiviParserIntegrationTest.php
```

### Flux de migration complet

1. `DiviParser::parse(int $postId)` — récupère le `post_content`, décode base64, parse les shortcodes.
2. `AstBuilder::build(DiviNode[])` — normalise en `AstTree` avec `StyleBag` et `ContentBag`.
3. `ElementorMapper::map(AstTree)` — transforme en structure `_elementor_data` Elementor.
4. `Injector::inject(int $postId, array $data)` — backup Divi, écriture meta Elementor, invalidation cache.
5. `ReportBuilder::buildForPost(...)` — génère un `PostReport` avec couverture et modules non supportés.
6. `ReportBuilder::buildGlobal(BatchResult)` — agrège en `GlobalReport` pour l'export.
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

`et_pb_text`, `et_pb_heading`, `et_pb_image`, `et_pb_button`, `et_pb_divider`, `et_pb_video`, `et_pb_code`, `et_pb_icon`, `et_pb_cta`, `et_pb_blurb`, `et_pb_tabs`, `et_pb_tab`, `et_pb_toggle`, `et_pb_accordion`, `et_pb_slider`, `et_pb_slide`, `et_pb_gallery`

### Modules non supportés (fallback HTML)

`et_pb_fullwidth_slider`, `et_pb_fullwidth_header`, `et_pb_portfolio`, `et_pb_shop`, `et_pb_countdown_timer`, `et_pb_pricing_table`

Les modules non supportés sont encapsulés dans un widget HTML Elementor de secours (`FallbackWidget`) avec le shortcode original préservé.

### Fonctionnalités du parseur

- Décodage base64 automatique du contenu Divi
- Gestion des shortcodes imbriqués (même type)
- Détection des shortcodes non fermés (statut `malformed`)
- Limite de profondeur récursive (10 niveaux)
- Protection ReDoS (troncature à 500k caractères)
- Fallback regex pour le parsing d'attributs (hors contexte WordPress)
- Logging PSR-3 (warnings pour contenu manquant, tronqué ou malformé)

### Fonctionnalités de l'injecteur

- Backup automatique du `post_content` Divi avant migration (contrat no-overwrite)
- Vérification des capabilities WordPress (`edit_post`)
- Injection atomique des meta `_elementor_data`, `_elementor_version`, `_elementor_edit_mode`
- Invalidation cache WordPress après écriture
- Migration par lots (`BatchMigrator`) avec isolation des erreurs par post
- Rollback unitaire depuis le backup

### Fonctionnalités de reporting

- Rapport par post : couverture (%), widgets convertis/fallback, modules non supportés avec position
- Rapport global : totaux, posts nécessitant une revue manuelle
- Persistance en `wp_options`
- Export JSON et CSV
- Page admin WordPress avec visualisation et boutons d'export

### Normalisation AST

- Extraction des largeurs de colonnes Divi (`4_4`, `1_2`, `1_3`, `2_3`, `1_4`, `3_4`) en pourcentage
- Normalisation des styles : `background_color`, `padding`, `margin`, `text_align`, `font_size`, `color`
- Normalisation du contenu : `content`, `src`, `alt`, `width`
- Préservation du shortcode brut pour les modules non supportés
<!-- END:features -->

<!-- SECTION:test-coverage -->
## Couverture de tests

### Tests unitaires (89 tests)

#### Parser (18 tests)

| Fichier | Tests | Couverture |
|---------|-------|-----------|
| `DiviParserTest.php` | 14 | Guard clauses, happy path, attributs, shortcodes non fermés, profondeur max, base64 |
| `DiviShortcodeTypeRetroTest.php` | 4 | Rétrocompatibilité enum shortcodes |

#### AST (17 tests)

| Fichier | Tests | Couverture |
|---------|-------|-----------|
| `AstBuilderTest.php` | 8 | Construction arbre, largeurs colonnes, styles, contenu, modules non supportés |
| `AstNodeWidthRetroTest.php` | 3 | Rétrocompatibilité largeurs de colonnes |
| `AstSerializationTest.php` | 3 | Sérialisation JSON de l'arbre |
| `StyleBagTest.php` | 3 | Normalisation des styles CSS |

#### Mapper (32 tests)

| Fichier | Tests | Couverture |
|---------|-------|-----------|
| `ElementorMapperTest.php` | 8 | Mapping section/row/column, structure _elementor_data |
| `WidgetFactoryTest.php` | 18 | Tous les types de widgets (text, image, button, divider, video, code, icon, heading) |
| `FallbackWidgetTest.php` | 3 | Widget HTML de secours, échappement XSS |
| `IdGeneratorTest.php` | 3 | Unicité, format hex, reset |

#### Injector (9 tests)

| Fichier | Tests | Couverture |
|---------|-------|-----------|
| `InjectorTest.php` | 5 | Écriture meta, backup no-overwrite, invalidation cache, vérification capabilities |
| `BatchMigratorTest.php` | 2 | Isolation erreurs par post, respect du limit |
| `RollbackTest.php` | 2 | Restauration contenu, échec sans backup |

#### Report (11 tests)

| Fichier | Tests | Couverture |
|---------|-------|-----------|
| `ReportBuilderTest.php` | 6 | Coverage %, status partial/failed, modules non supportés, manual review |
| `ReportExporterTest.php` | 2 | Export JSON valide, headers CSV |
| `ReportStoreTest.php` | 3 | Persistance wp_options, load null, JSON corrompu |

#### Admin (2 tests)

| Fichier | Tests | Couverture |
|---------|-------|-----------|
| `AdminPageTest.php` | 2 | Vérification capabilities, nonce + export |

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

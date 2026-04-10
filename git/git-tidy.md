

# Git Tidy — Plan de commits structurés

**Branche** : main

**Date** : 2026-04-10

---

## Commit 1 — domaine

```bash
git add src/Mapper/
git commit -m "domaine: add Mapper layer for Divi to Elementor conversion"
```

---

## Commit 2 — fixtures

```bash
git add tests/Unit/Mapper/ tests/Unit/Ast/AstNodeWidthRetroTest.php tests/Unit/Parser/DiviShortcodeTypeRetroTest.php
git commit -m "fixtures: add unit tests for Mapper, AST width retro and shortcode type retro"
```

---

## Vérification

```bash
git log --oneline -2
```

---
plan name: DocsActualize
plan description: Sync docs with code
plan status: active
---

## Idea
Actualize /home/ihor/appdashboard-code/docs against current codebase: rewrite stale overview/index/i18n/structure docs (00,01,02,03,04,06,07); replace map→dawarich, tft→lopaka, relocate performance to project/performance; add new docs for gitea/immich/plane SSO bridges, smart_home, public-api, telegram bot, share_file_utils; keep existing cloud/films/media_viewer/recipes/spends/steps/viewer/webssh/mqtt docs accurate after verification; update index links and app/language counts (13 apps, 6 langs).

## Implementation
- Inventory gaps vs CLAUDE.md/apps/public-api/bot/share_file_utils (done)
- Rewrite 00/01/02/03/04/06/07 for 13 apps and 6 languages
- Replace 10_map→dawarich, 16_tft→lopaka; update 12_performance for project/performance
- Verify and patch 08/09/11/13/14/15/17/05/mqtt against code
- Add 18–24 docs: gitea, immich, plane, smart_home, public-api, bot, share_file_utils
- Cross-check index links and run final dual verification

## Required Specs
<!-- SPECS_START -->
<!-- SPECS_END -->
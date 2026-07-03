# Graph Report - media_viewer  (2026-05-30)

## Corpus Check
- 15 files · ~208,068 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 43 nodes · 59 edges · 17 communities (14 shown, 3 thin omitted)
- Extraction: 80% EXTRACTED · 20% INFERRED · 0% AMBIGUOUS · INFERRED: 12 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]

## God Nodes (most connected - your core abstractions)
1. `getDB()` - 19 edges
2. `handleAlbumActions()` - 8 edges
3. `mvFindEmailBySession()` - 4 edges
4. `getCurrentUserRole()` - 4 edges
5. `getGalleryAlbumById()` - 4 edges
6. `deleteGalleryAlbum()` - 4 edges
7. `get_current_user_from_sessions()` - 3 edges
8. `getUserRoleByEmail()` - 3 edges
9. `getCurrentUserPhoto()` - 3 edges
10. `addGalleryAlbum()` - 3 edges

## Surprising Connections (you probably didn't know these)
- `handleAlbumActions()` --calls--> `getGalleryAlbumById()`  [INFERRED]
  controllers/AlbumController.php → core/db.php
- `handleAlbumActions()` --calls--> `deleteGalleryAlbum()`  [INFERRED]
  controllers/AlbumController.php → core/db.php
- `handleAlbumActions()` --calls--> `saveJsonFile()`  [INFERRED]
  controllers/AlbumController.php → core/helpers.php
- `isUserRoot()` --calls--> `getDB()`  [INFERRED]
  core/session.php → core/db.php
- `handleAlbumActions()` --calls--> `addGalleryAlbum()`  [INFERRED]
  controllers/AlbumController.php → core/db.php

## Communities (17 total, 3 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.42
Nodes (8): clearGalleryStats(), dismissNotification(), getDB(), getGalleryAlbums(), getGalleryHiddenAlbums(), getGalleryStats(), initGalleryTables(), isNotificationDismissed()

### Community 1 - "Community 1"
Cohesion: 0.48
Nodes (6): get_current_user_from_sessions(), getCurrentUserPhoto(), getCurrentUserRole(), getUserRoleByEmail(), isUserRoot(), mvFindEmailBySession()

### Community 3 - "Community 3"
Cohesion: 0.4
Nodes (5): handleAlbumActions(), addGalleryAlbum(), incrementGalleryAlbumViews(), toggleGalleryAlbumHidden(), updateGalleryAlbum()

## Knowledge Gaps
- **1 isolated node(s):** `graphify`
  These have ≤1 connection - possible missing edges or undocumented components.
- **3 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `getDB()` connect `Community 0` to `Community 1`, `Community 3`, `Community 5`?**
  _High betweenness centrality (0.270) - this node is a cross-community bridge._
- **Why does `handleAlbumActions()` connect `Community 3` to `Community 2`, `Community 4`, `Community 5`?**
  _High betweenness centrality (0.224) - this node is a cross-community bridge._
- **Why does `saveJsonFile()` connect `Community 2` to `Community 3`?**
  _High betweenness centrality (0.145) - this node is a cross-community bridge._
- **Are the 5 inferred relationships involving `getDB()` (e.g. with `get_current_user_from_sessions()` and `getUserRoleByEmail()`) actually correct?**
  _`getDB()` has 5 INFERRED edges - model-reasoned connections that need verification._
- **Are the 7 inferred relationships involving `handleAlbumActions()` (e.g. with `addGalleryAlbum()` and `getGalleryAlbumById()`) actually correct?**
  _`handleAlbumActions()` has 7 INFERRED edges - model-reasoned connections that need verification._
- **What connects `graphify` to the rest of the system?**
  _1 weakly-connected nodes found - possible documentation gaps or missing edges._
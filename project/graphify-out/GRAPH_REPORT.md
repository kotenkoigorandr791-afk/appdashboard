# Graph Report - project  (2026-06-27)

## Corpus Check
- 12 files · ~100,725 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 88 nodes · 133 edges · 19 communities (16 shown, 3 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 2 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 12|Community 12]]

## God Nodes (most connected - your core abstractions)
1. `my_htmlspecialchars()` - 8 edges
2. `buildAuthEmailBody()` - 6 edges
3. `sendTelegramMessage()` - 6 edges
4. `collectUserStorageTargets()` - 6 edges
5. `ap_normalize_i18n_lang()` - 5 edges
6. `sendVerificationEmail()` - 5 edges
7. `getTelegramDomain()` - 5 edges
8. `sendTelegramPhoto()` - 5 edges
9. `notifyTelegramNewUser()` - 5 edges
10. `ap_load_i18n_from_dir()` - 4 edges

## Surprising Connections (you probably didn't know these)
- `ap_i18n_lang_exists()` --calls--> `ap_normalize_i18n_lang()`  [INFERRED]
  core.php → i18n_helpers.php
- `ap_load_i18n_lang()` --calls--> `ap_normalize_i18n_lang()`  [INFERRED]
  core.php → i18n_helpers.php

## Communities (19 total, 3 thin omitted)

### Community 1 - "Community 1"
Cohesion: 0.26
Nodes (10): ap_i18n_lang_exists(), ap_load_i18n_lang(), ap_merge_i18n(), ap_i18n_lang_exists_in_dir(), ap_i18n_supported_langs(), ap_load_i18n_all_langs(), ap_load_i18n_file(), ap_load_i18n_from_dir() (+2 more)

### Community 2 - "Community 2"
Cohesion: 0.27
Nodes (7): apSecConvertImageToWebp(), apSecDetectMimeFromFile(), apSecExpectedHosts(), apSecRandomFilename(), apSecRequireSameOrigin(), apSecSameOrigin(), apSecStoreSupportChatMedia()

### Community 3 - "Community 3"
Cohesion: 0.36
Nodes (10): buildTelegramInlineKeyboard(), getTelegramDomain(), loadAuthorizedChats(), my_htmlspecialchars(), notifyTelegramEmailVerified(), notifyTelegramFailedAuth(), notifyTelegramNewUser(), renderApUserActionsCell() (+2 more)

### Community 4 - "Community 4"
Cohesion: 0.29
Nodes (8): collectUserStorageTargets(), countUserFiles(), deleteDirectory(), deleteUserFolders(), getUserFolderVariants(), scanFilesRecursive(), scanUserFiles(), scanUserFilesProgressive()

### Community 5 - "Community 5"
Cohesion: 0.47
Nodes (6): apEmailLogoHtml(), apEmailPublicBaseUrl(), buildAuthEmailBody(), sendEmailWithLogo(), sendPasswordResetEmail(), sendVerificationEmail()

### Community 6 - "Community 6"
Cohesion: 0.5
Nodes (4): createClientBrowserAuthRequest(), ensureClientBrowserAuthSchema(), getClientBrowserAuthRequest(), purgeClientBrowserAuthRequests()

### Community 7 - "Community 7"
Cohesion: 0.83
Nodes (3): applyViewport(), boot(), isTouchMobile()

### Community 8 - "Community 8"
Cohesion: 0.67
Nodes (3): collectServerSecurityMeta(), failedAuthNotifyPayload(), parseJsonClientContext()

## Knowledge Gaps
- **1 isolated node(s):** `graphify`
  These have ≤1 connection - possible missing edges or undocumented components.
- **3 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `ap_load_i18n_lang()` connect `Community 1` to `Community 0`?**
  _High betweenness centrality (0.064) - this node is a cross-community bridge._
- **Why does `ap_i18n_lang_exists()` connect `Community 1` to `Community 0`?**
  _High betweenness centrality (0.061) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `ap_normalize_i18n_lang()` (e.g. with `ap_load_i18n_lang()` and `ap_i18n_lang_exists()`) actually correct?**
  _`ap_normalize_i18n_lang()` has 2 INFERRED edges - model-reasoned connections that need verification._
- **What connects `graphify` to the rest of the system?**
  _1 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.12 - nodes in this community are weakly interconnected._
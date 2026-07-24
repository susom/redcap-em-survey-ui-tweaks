# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A REDCap **External Module** (EM) — "Survey UI Tweaks". It layers optional CSS/JS/behavioral tweaks onto REDCap survey pages (hide submit button, autoscroll, rename buttons, drag-and-drop matrix ranking, survey duration capture, bypass survey login, etc.). There is no build step, no test suite, and no package manager. It is PHP served directly by REDCap plus vanilla JS/jQuery injected into survey pages.

## Running / testing

REDCap loads the module from within a running REDCap instance — you cannot run it standalone. This repo lives under a REDCap docker-compose install (`redcap-docker-compose-master/www/modules-local/`). To exercise a change:

1. Enable the module in a REDCap project and configure tweaks in the module config dialog.
2. Open a survey (public link or participant link) and observe the injected behavior.
3. Per the user's global guidance: reproduce bugs end-to-end as a real end-user would (use a real survey URL), and use **Playwright** for any frontend/browser testing — not the Chrome extension.

There is no lint config beyond `.editorconfig` (4-space indent, LF, UTF-8, trailing-whitespace trimmed, final newline). Match it.

## Architecture

`SurveyUITweaks.php` is the whole backend — one class extending `\ExternalModules\AbstractExternalModule`. It hooks into REDCap's survey lifecycle and, for each enabled tweak, `echo`s a `<style>`/`<script>` block (or reads a JS file from `js/` via `file_get_contents` and inlines it) into the page.

### Hook → tweak dispatch (the core pattern)

Each REDCap hook method holds an associative array mapping a **setting key** to a **method name**, then loops calling `checkFeature($key, $func, $instrument)`:

- `redcap_every_page_before_render()` — button renaming for REDCap 12.0.0+ (must run before render to alter `$lang`).
- `redcap_survey_page_top()` — the bulk of visual tweaks (CSS/JS injected at top of survey page). Also calls `checkSurveyDuration()` and `checkMatrixRank()`.
- `redcap_every_page_top()` — the "save and return without email" tweak (fires on the `__return` page, which isn't a survey_page_top).
- `redcap_survey_complete()` — end-of-survey tweaks (hide queue, social share, `surveyLoginOnSave`).

`checkFeature()` is the dispatch heart. It resolves a global setting (`global_<key>`) vs. a per-survey override. **Instrument-level (per-survey) config takes priority over global config** (README, v1.2.0) — if an instrument override fires, the global handler is skipped. The value stored under the key is passed as the argument to the tweak method (used by rename/resize tweaks).

### Settings model (`config.json`)

Two tiers, both driven by `config.json`:
- **Global** tweaks: top-level `global_*` project settings; when a global is set, its per-survey counterpart is hidden via `branchingLogic` (`global_x == false`).
- **Per-survey** tweaks: the repeatable `survey_tweaks` sub_settings block, keyed by `survey_name`. Loaded lazily via `loadInstances()` → `getSubSettings('survey_tweaks')` (wrapped in try/catch so a project with invalid settings doesn't fatal).

Other repeatable sub_settings blocks: `sortrank` (matrix ranking config), `survey_duration_fields`.

**When adding a tweak:** add the `global_*` key and/or the per-survey sub_setting to `config.json`, add the `key => methodName` entry to the relevant hook's array in `SurveyUITweaks.php`, and write the tweak method.

### Feature notes / gotchas

- **Button renaming is REDCap-version-split.** `renameNextButton`/`renamePreviousButton` handle < 12.0.0 (JS text-replace in `survey_page_top`); `renameNextButton2`/`renamePreviousButton2` handle 12.0.0+ (set `$lang[...]` in `every_page_before_render`). Each guards with `REDCap::versionCompare`. Don't collapse them.
- **Survey login bypass** (`survey_login_on_save` + `bypassSurveyLoginForFirstSubmission`): sets REDCap's `survey_login_pid<pid>` / `survey_login_session_pid<pid>` cookies, mirroring REDCap's own login code — the hash uses `$password_algo`, `$salt`, and `$GLOBALS['salt2']`. Keep it consistent with REDCap core if that logic changes. Skips public surveys (null `participant_email`) and only bypasses first-time (unsubmitted) responses.
- **Matrix ranking** (`checkMatrixRank`): validates the matrix group is `field_type == radio` and `matrix_ranking == y`, logs config errors via `REDCap::logEvent`. Injects `js/sortable.min.js`, `js/jquery-sortable.js`, `js/matrixranking.js` **in that exact order** (noted in-code as required).
- **Survey duration** (`checkSurveyDuration`): duration fields come from config OR the `@SURVEY-DURATION` action tag in a field's annotation (regex `@SURVEY-?DURATION`). Injects `js/surveyduration.js` with the field list.
- Tweaks that hide the submit row set `opacity: 0` in CSS then restore to `1` via JS after manipulating the button — this avoids a flash of the original label/button.

### JS files (`js/`)

Inlined into the page, not served as separate assets. They read config from a global object the PHP defines just before inlining (e.g. `MatrixRanking.config`, `SurveyDuration.fields`).

## Conventions

- `emLoggerTrait` provides `emDebug`/`emError`/`emLog` (gated by the debug-logging settings). Use these, not `error_log`.
- Namespace is `Stanford\SurveyUITweaks`; use `\REDCap`, `\Survey`, etc. for core classes.
- Commit messages: do **not** add Claude as co-author (per user global guidance). Document non-trivial fixes in a markdown file.
- Historically many methods carry `TODO` notes (e.g. "change JS fix to CSS", "config from Survey Settings page"). Preserve intent when touching them.

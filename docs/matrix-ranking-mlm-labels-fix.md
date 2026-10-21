# Fix: Enhanced Ranked Matrix (drag/drop) ignores Multi-Language Management translations

**Date:** 2026-10-21
**Area:** `js/matrixranking.js` (Enhanced Ranked Matrix, the drag-and-drop tweak driven by the `sortrank` config)
**Reported symptom:** On a survey translated to Spanish with Multi-Language Management (MLM), the drag/drop
ranking items stayed in English even though every row label had a Spanish translation. The matrix header
was translated. With the module's drag/drop disabled, the default REDCap matrix showed the Spanish labels.

## Root cause

The drag/drop items never come from the page REDCap renders.

1. `checkMatrixRank()` reads the labels from `REDCap::getDataDictionary()`, which always returns the
   reference language, and passes them to the browser as `MatrixRanking.config[grp].labels`.
2. `constructNewUI()` builds each `<li>` with `.text(config.labels[i])`.
3. MLM translates survey pages client side. It replaces the contents of REDCap's own
   `[data-mlm-field][data-mlm-type="label"]` elements, and for these matrices those are the original rows
   that `hideDefault()` has parked off screen. The translation lands, just where nobody can see it.

Nothing tells the module that the language changed, so switching language with the MLM switcher also
left the items untouched. The header was unaffected because the module shows REDCap's own header row.

## Fix

Copy each row's label text from REDCap's (translated) matrix row into its drag/drop item:

- The label text in each `<li>` now sits in its own `span.mtx_rank_text`, so it can be replaced without
  touching the rank badge that `onEnd` prepends.
- `getRowLabel()` reads `tr[sq_id=X] [data-mlm-field=X][data-mlm-type="label"]` and falls back to the data
  dictionary label if that element is missing or empty.
- `refreshLabels()` updates every item in both lists (unranked and ranked).
- `watchLanguage()` subscribes `refreshLabels()` to `REDCap.MultiLanguage.onLangChanged()`. MLM calls it once
  after initializing (in REDCap 17.x it notifies even when no language change happened during init) and
  again on every language switch. If MLM had already initialized before the module subscribed, it
  refreshes once immediately.

Pages without MLM (or REDCap versions without its JS API) skip all of this, so the items keep exactly the
data dictionary labels they had before. Only `textContent` is copied, matching how the items were built
(`.text()`), so no translated HTML is injected.

Ranks are untouched: items are matched by `data-fieldname`, and the hidden radios are not clicked.

## Not covered

The module's own "matrix instructions" setting is a single free-text module setting with no MLM
translation, so it still displays in one language.

## Verification

`docs/e2e/matrix-dnd-mlm-labels.mjs` is a standalone Playwright harness. It renders REDCap-like ranked
matrix markup, the module's three JS files in their required order, and a stand-in for
`REDCap.MultiLanguage` with the same public surface and behavior. Drags are real mouse drags.

- Baseline (pre-fix `matrixranking.js`): items stay English with Spanish selected. Bug reproduced.
- No MLM on the page: items show data dictionary labels, ranks save, badges show.
- MLM initializing after the module: items show Spanish, and ranks save in drag order.
- Language switch Spanish to English and back: all items follow, badges and saved ranks are unchanged.
- MLM already initialized before the module subscribes: items show Spanish.
- Resumed (save and return) survey: previously ranked and unranked items both show Spanish.
- No JS errors in any case.

The MLM API names were checked against `Resources/js/MultiLanguageSurvey.js` in REDCap 17.2.3 and 17.5.5.

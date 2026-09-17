# Fix: Enhanced Ranked Matrix (drag/drop) breaks branching on dependent fields

**Date:** 2026-07-23
**Area:** `js/matrixranking.js` (Enhanced Ranked Matrix — the drag-and-drop tweak driven by the `sortrank` config)
**Reported symptom (office hours):** A text field below a ranking matrix, shown by branching logic only when an "other" option has a value, did not appear when the option was dragged into the ranking. It sometimes appeared after a *second* move, and even then the text box could not be typed into. With the module's drag/drop disabled (default REDCap radio matrix), the field showed as expected.

## Root cause #1 — the module stripped `doBranching()` from the radio onclick

The drag/drop UI keeps REDCap's real matrix radios on the page (moved off-screen) and drives their values programmatically. To stop REDCap's own de-dup logic from fighting the reorder, the module rewrote each radio's inline `onclick`.

REDCap builds a ranking radio's onclick (see `redcap_vXX/Classes/DataEntry.php`, ~line 7755) as:

```
matrix_rank(...);document.forms['form'].X.value=this.value;[window.calculate('X');]doBranching('X');
```

`doBranching('X')` is present whenever `X` is referenced in any field's branching logic (REDCap's `getBranchingFields()` collects every field *referenced* by branching, i.e. the source/"other" field qualifies).

The old code did:

```js
var new_onclick = $(this).attr("onclick").split(";")[1];   // keeps ONLY the value-store segment
```

That kept segment `[1]` (the value store) and **discarded both `matrix_rank(...)` (intended) and `doBranching('X')` (the bug)**. So dragging set the value but never re-evaluated branching → the dependent field never showed. The default matrix worked precisely because its native onclick still called `doBranching`. ("Can't type" was the same bug: the field was still branch-hidden/disabled because branching never ran.)

**Fix:** remove *only* the `matrix_rank(...)` call and preserve everything else (value store + `calculate()`/`doBranching()`):

```js
var new_onclick = onclick.replace(/matrix_rank\([^)]*\);?/, "");
```

`matrix_rank(...)` arguments never contain a `)`, so the regex is safe and surgical.

## Root cause #2 — dragging an option *out* left a stale value (branched field stayed visible)

`onEnd` tried to clear all ranks before re-applying the new order via:

```js
$("tr[mtxgrp='...'] .resetLinkParent .smalllink").click();   // jQuery .click()
```

Two independent problems, both pre-existing but only *visible* once branching (fix #1) works:

1. Those reset links are `<a href="javascript:;" onclick="radioResetVal(...)">`. **jQuery `.trigger('click')` does not fire an anchor's inline `onclick`** (it works for the radio re-clicks because those are `<input>`s). So the reset was a no-op.
2. Even with a native click, `radioResetVal()` (`DataEntrySurveyCommon.js`, ~line 3438) self-guards with `justClickedEnhancedChoice`: the first call sets the flag and every subsequent call within ~50 ms returns immediately. Clicking all rows in a loop would only ever reset the **first** field.

Because the re-click loop already re-applies correct ranks to everything still in the target list, the only field that truly needs clearing is the **one dragged out**. `radioResetVal()` also calls `doBranching()` itself, so clearing it updates any dependent field.

**Fix:** when the drop target is the left/source list, reset exactly the dragged field:

```js
if(($(evt.to).attr("id") || "").indexOf("target") === -1){
    var removed_field = $(evt.item).data("fieldname");
    if(removed_field){
        radioResetVal(removed_field, 'form');   // clears value + fires doBranching
    }
}
```

A single call sidesteps the `justClickedEnhancedChoice` guard. Both the initial and the resume (`saved_values`) code paths set `data-fieldname` on the `<li>`, so this works for resumed surveys too.

Note: on **surveys**, REDCap does not prompt the participant to erase the now-hidden field's value (the `simpleDialog` erase prompt is suppressed for surveys); the value clears and the field hides silently. Our reset uses REDCap's own `radioResetVal`, the exact function the default matrix reset link calls, so behavior matches the default matrix by construction.

## Root cause #3 — the revealed field could not be clicked / typed into

Once fix #1 made the branched field appear, users still couldn't click into it. `hideDefault()`
pushes the original matrix rows off-screen with:

```js
sortrank_mtx_tr.css("opacity",0).css("position","absolute").css("left","-5000px");
```

Two problems combine:

1. **`opacity:0` hides a row visually but it still captures pointer events** (unlike `display:none`
   or `pointer-events:none`).
2. Setting a `<tr>` to `position:absolute` breaks table layout, so the row's inner
   `table.headermatrix` balloons to its content width (~5300px). Its box, though shifted to a
   negative `left`, extends back across the visible area and lands at the **same vertical position**
   as the field rendered below the matrix.

The result is an invisible, ~5300px-wide element sitting on top of the branched "other, specify"
box, silently intercepting every click. (Verified with `document.elementFromPoint` over the field →
returned `td#matrixheader-format_matrix-6`; a real Playwright `.click()` failed with
"...matrix header... subtree intercepts pointer events".) In *this* project it was latent before
fix #1 only because the dependent field was branch-hidden and never appeared.

**This one is not specific to branching.** The overlay is created at `$(document).ready` and blocks
whatever lands under it. Any *always-visible* field below the matrix is unclickable from page load
onward, with no drag and no branching involved — which is how a REDCap community user hit it on the
released 1.2.2. See
[community-report-2026-09-17-textbox-below-dnd-matrix.md](community-report-2026-09-17-textbox-below-dnd-matrix.md)
for that repro, measurements, and why the symptom comes and goes with viewport width.

**Fix:** add `pointer-events:none` to the hidden rows so clicks fall through to the real field:

```js
sortrank_mtx_tr.css("opacity",0).css("position","absolute").css("left","-5000px").css("pointer-events","none");
```

`pointer-events:none` only affects real pointer hit-testing — the module's programmatic
`$(radio).click()` re-clicks are unaffected (confirmed: ranks still save). It is applied in
`hideDefault()` (JS), scoped to the configured drag matrices only; a global CSS rule on `tr[mtxgrp]`
would wrongly disable the page's non-drag matrices too.

## Verification

Reproduced and verified end-to-end with Playwright against a real survey
(`http://redcap.local/surveys/?s=rFCt5us3EvekKGVQ`, `format_matrix` ranking group with the
`format_other` → `format_other_specify` branch).

- **Baseline (before fix):** drag "Other" in → `format_other` gets value `1` but the specify field stays hidden. Confirmed.
- **After fix:**
  - Branched field shows on the **first** drag-in (deterministic — no second move needed).
  - Field accepts a **real click** and **real keystrokes** (not just a scripted `.focus()`/`fill()` —
    that earlier masked root cause #3).
  - Ranks still save to the hidden matrix (regression check on commit e10fc55).
  - Dragging the option **out** hides the field again and clears its value; remaining ranks re-sequence.
  - No JS errors, no spurious erase/confirm dialogs.

### Test harness note

**Superseded 2026-09-17:** an earlier version of this note said Playwright could not drive
SortableJS 1.9.0's native HTML5 drag-and-drop, so the tests moved the `<li>` in the DOM and invoked
the bound `onEnd` handler (`$(ul).data('sortable').options.onEnd(evt)`) directly. That workaround is
not needed. A genuine `mouse.move` → `mouse.down` → incremental `mouse.move` → `mouse.up` sequence
in Chromium does drive the sort: the item lands in the target list, badges renumber, and the rank
value appears on the hidden radio. Verified on PID 270 — see
[community-report-2026-09-17-textbox-below-dnd-matrix.md](community-report-2026-09-17-textbox-below-dnd-matrix.md)
and the reusable probe at [`e2e/matrix-dnd-clickthrough.mjs`](e2e/matrix-dnd-clickthrough.mjs).

Prefer real input for this feature specifically: scripted `.focus()`/`.fill()` bypasses hit-testing
and would have hidden root cause #3 completely.

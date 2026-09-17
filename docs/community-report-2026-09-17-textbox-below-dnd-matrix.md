# Community bug report: text box below a drag-and-drop matrix cannot be selected

**Date investigated:** 2026-09-17
**Reported by:** a REDCap community user running the module from the REDCap Community repo
**Area:** `js/matrixranking.js` — "Enhanced Drag and Drop Matrix Ranking" (`sortrank` config)

## The report

> When using drag-and-drop matrix, if a text box is the next field beneath it, the survey
> respondent cannot select the text box. […] The issue can be reproduced by ensuring the module is
> enabled with drag and drop for `[testmatrix]`, then using the public link to respond to the
> survey and attempting to enter data in the text box beneath the matrix.

The reporter attached a project XML. It was imported locally as **PID 270 "Tweak UI"** — one form
(`form_1`) with a 3-option ranking matrix `testmatrix` (`example1..3`, all `radio`,
`matrix_ranking = y`, **no branching logic anywhere**) and a plain text field named `text` labelled
"Text field (try clicking the text box and entering data)" directly beneath it.

## Verdict

**Already fixed on `master`; the fix was never released.** No new code change is required — the
action is to cut a release so the REDCap Community repo picks it up.

The fix commits are untagged, and 1.2.2 is the newest release, so **every version obtainable from
the Community repo lacks the fix** — this holds regardless of which build the reporter installed.

| | |
|---|---|
| Latest GitHub release / Community version | **1.2.2** — tag `e10fc55`, 2023-03-24 |
| Fix commits | `8a59f32`, `87b0aeb` — 2026-07-23, **untagged, unreleased** |
| Reproduced on 1.2.2 | yes (desktop widths) |
| Reproduced on `master` | no |

This is **root cause #3** of [matrix-ranking-branching-fix.md](matrix-ranking-branching-fix.md),
now seen standalone. That fix was prompted by a branching-logic report, and #3 showed up there only
as a follow-on symptom ("can't type into the field that just appeared"). PID 270 shows #3 needs no
branching at all: any always-visible field under the matrix is blocked from page load onward, with
zero drags. That is the more general statement of the bug, and it is what the community user hit.

## Mechanism (recap, with measurements from PID 270)

`hideDefault()` parks REDCap's real matrix rows off-screen so their native mechanisms keep working:

```js
sortrank_mtx_tr.css("opacity",0).css("position","absolute").css("left","-5000px");   // 1.2.2
```

Two things combine:

1. `opacity:0` hides a row visually but it still **hit-tests** (unlike `display:none` /
   `pointer-events:none`). `display:none`/`visibility:hidden` are not usable here — `checkState()`
   uses `$(tr).is(":visible")` to detect branch-hidden rows, so hiding them that way would mark the
   whole matrix as branch-hidden.
2. `position:absolute` on a `<tr>` takes it out of table layout (computed `display` becomes
   `block`), so its inner `table.headermatrix` balloons to content width. Shifted to
   `left:-5000px`, the box still extends back across the viewport.

Measured on PID 270 at 1280×900, v1.2.2:

| element | x → right | y → bottom |
|---|---|---|
| `td#matrixheader-testmatrix-3` (inside the parked header `<tr>`) | −237 → **+1039** | 468 → 497 |
| `input[name="text"]` | 686 → 994 | 473 → 497 |

The invisible header cell covers the input in both axes, so it wins the hit test:
`document.elementFromPoint()` over the middle of the text box returns
`td#matrixheader-testmatrix-3`, and Playwright's real click fails with

```
<td … id="matrixheader-testmatrix-3">3</td> from <tr mtxgrp="testmatrix" id="testmatrix-mtxhdr-tr">…</tr> subtree intercepts pointer events
```

**The fix** (on `master`) adds one declaration so pointer hit-testing skips those rows entirely,
letting clicks fall through to the real field. Programmatic `$(radio).click()` on the parked radios
is unaffected, so ranks still save:

```js
sortrank_mtx_tr.css("opacity",0).css("position","absolute").css("left","-5000px").css("pointer-events","none");
```

### The symptom is layout-dependent

The parked rows get no `top`, so they land at their static vertical position. Whether they cover the
next field depends on the surrounding layout. On PID 270 at 1280 px they do; at the iPhone 13
viewport (390 px) the same v1.2.2 code puts the header band at y 411–441 while the input sits at
y 499 — no vertical overlap, and the text box is tappable. So on 1.2.2 the bug looks intermittent
("works on my phone") even though the overlay is always live. That fragility is why the fix disables
pointer events rather than adjusting geometry.

## Verification (end-to-end, real respondent path)

Local REDCap 17.2.3, PID 270, module enabled with `sortrank` → `matrix_name = testmatrix`,
`show_rank_label` on, instructions "Drag Choices Here". Driven over the **public survey link** with
Playwright (Chromium), using real mouse/keyboard input — not scripted `.focus()`/`.fill()`, which
would mask this bug entirely. Only `js/matrixranking.js` was swapped between runs; `SurveyUITweaks.php`
stayed at `master` so the experiment isolates the interception mechanism.

| run | `matrixranking.js` | drag/drop configured | `elementFromPoint` over text box | real click | real keystrokes |
|---|---|---|---|---|---|
| A — control | master | **no** | the `input` | reaches it | saved |
| B — community | **1.2.2** | yes | `td#matrixheader-testmatrix-3` | **blocked** | **lost** (value stays `""`) |
| C — fixed | master | yes | the `input` | reaches it | saved |

On run B the text box is blocked both **before and after** dragging, and dragging itself still works
(rank values land on the hidden radios) — exactly matching the report.

Additional checks on `master`:

- `pointer-events:none` survives a drag (the `onEnd` re-click loop does not reset the inline styles).
- Full submit: ranked 3 → 1 → 2 by real drags, typed into the text box, submitted. DB after submit:
  `example1=2, example2=3, example3=1, text="E2E respondent text", form_1_complete=2`. No JS errors.
- Mobile (iPhone 13 emulation): passes on load and after a drag.

Regression probe kept at [`docs/e2e/matrix-dnd-clickthrough.mjs`](e2e/matrix-dnd-clickthrough.mjs).
It passes 12/12 checks per viewport on `master` and fails 10 on 1.2.2 — run it before any future
release that touches the ranking UI.

```bash
npx playwright install chromium   # once
SURVEY_URL='http://redcap.local/surveys/?s=<public hash>' MATRIX=testmatrix FIELD=text \
  node docs/e2e/matrix-dnd-clickthrough.mjs
```

## Local test fixture

PID 270 came from the reporter's XML; the public link and module config were added by hand:

```sql
-- public survey link (survey_id 1409 = form_1, event_id 1103), hash chars per generateRandomHash(16,false,true)
INSERT INTO redcap_surveys_participants (survey_id, event_id, participant_email, hash)
VALUES (1409, 1103, NULL, 'AF7MELDKJ7P7P3CA');

-- module config (external_module_id 83 = survey_ui_tweaks); equivalent to the config dialog
INSERT INTO redcap_external_module_settings (external_module_id, project_id, `key`, type, value) VALUES
 (83, 270, 'sortrank',            'json-array', '["true"]'),
 (83, 270, 'matrix_name',         'json-array', '["testmatrix"]'),
 (83, 270, 'show_rank_label',     'json-array', '[true]'),
 (83, 270, 'randomize_options',   'json-array', '[false]'),
 (83, 270, 'matrix_instructions', 'json-array', '["Drag Choices Here"]');
```

Public link: `http://redcap.local/surveys/?s=AF7MELDKJ7P7P3CA`

## Release action

**Recommended: cut 1.2.3 containing only `js/matrixranking.js` (plus `docs/`).** The three JS fixes
are self-contained, need no PHP change, and are verified above — that answers the report completely
with zero new exposure for existing users. Then do 1.3.0 separately, once the `salt2` cutoff below is
pinned and gated.

To be explicit about what the `salt2` finding does and does not block: it does **not** block
answering the reporter or shipping the matrix fix. It blocks shipping `0cdf217` to the Community
repo.

Releasing all of `master` instead would ship three things beyond 1.2.2, making it a **minor** bump
(new feature present) rather than a patch:

| commit | change | risk |
|---|---|---|
| `8a59f32` + `87b0aeb` | matrix drag/drop: keep `doBranching()` in the radio `onclick`, reset the dragged-out field, `pointer-events:none` on parked rows | the reported fix; verified above |
| `4ebacea` | `filter_var(..., FILTER_SANITIZE_STRING)` → `preg_replace("/[^a-zA-Z0-9]+/", "", …)` | needed on PHP ≥ 8.1, where the filter is removed/deprecated |
| `0cdf217` | new "Bypass survey login for initial submission" behaviour, **plus** a change to the existing `surveyLoginOnSave()` cookie hash: `"$pid\|$record\|$salt"` → `"$pid\|$record\|$salt\|{$GLOBALS['salt2']}"` | see below |

### The `salt2` change is REDCap-version-dependent — decide before tagging

`surveyLoginOnSave()` has to mint a cookie that REDCap core will accept, and **core changed that
formula**. Read directly out of `Surveys/index.php` in every REDCap tree on this machine (the same
expression is used to set *and* to validate the cookie, so they move together):

| REDCap | `hash($password_algo, …)` argument | line (setcookie) |
|---|---|---|
| 13.6.0 | `"$project_id\|$fetched\|$salt"` | 1233 |
| 13.8.1 | `"$project_id\|$fetched\|$salt"` | 1234 |
| 14.4.1 | `"$project_id\|$fetched\|$salt"` | 1269 |
| 14.5.3 | `"$project_id\|$fetched\|$salt"` | 1269 |
| 16.1.3 | `"$project_id\|$fetched\|$salt\|{$GLOBALS['salt2']}"` | 1365 |
| 17.1.2 | `"$project_id\|$fetched\|$salt\|{$GLOBALS['salt2']}"` | 1508 |
| 17.2.3 | `"$project_id\|$fetched\|$salt\|{$GLOBALS['salt2']}"` | 1507 |

So the cutoff is in **(14.5.3, 16.1.3]** — i.e. some REDCap 15.x release; no 15.x tree was available
here to narrow it further. The two module versions are each correct on only one side of it:

- **1.2.2** (no `salt2`) → correct on ≤ 14.5.3, **broken** on ≥ 16.1.3. This is presumably why
  `0cdf217` was written.
- **`master`** (with `salt2`) → correct on 17.2.3, and would **newly break** `survey_login_on_save`
  for any Community site still on a pre-cutoff REDCap. `config.json` declares
  `redcap-version-min: 10.8.2`, so those sites can install it.

`$GLOBALS['salt2']` itself is not the discriminator — core auto-creates it when missing
(`Classes/System.php:668-671`), so it is non-empty on old versions too; they simply do not include
it in this hash.

Serving both sides needs a version gate, the same pattern the button-rename tweaks already use
(`renameNextButton` vs `renameNextButton2` guarded by `REDCap::versionCompare`):

```php
// <CUTOFF> = first REDCap version whose Surveys/index.php includes salt2; in (14.5.3, 16.1.3]
$hash_input = REDCap::versionCompare(REDCAP_VERSION, '<CUTOFF>', '>=')
    ? "$project_id|$record|$salt|{$GLOBALS['salt2']}"
    : "$project_id|$record|$salt";
```

Pin `<CUTOFF>` by grepping that one line in a 15.x tree (or raise `redcap-version-min` past it and
accept dropping older sites).

Two housekeeping items to fold into whichever release is cut:

- `docs/` has been untracked since the July 2026 fix — commit it, so that fix's rationale is finally
  in the repo.
- `README.md`'s version list stops at 1.2.0 (1.2.1 and 1.2.2 are missing) and never mentions the
  drag-and-drop ranking or survey-login tweaks.

## Draft reply to the reporter

> Thanks for the detailed report and the XML — I imported it and reproduced the problem exactly as
> you described, on the public link, with no drags needed.
>
> The cause is the module's drag-and-drop tweak: it keeps REDCap's real ranking matrix on the page
> and parks it off-screen, but the parked header row stays "clickable" and its invisible box lands on
> top of whatever field follows the matrix, so your text box never receives the click.
>
> This is already fixed in the module's source, but the fix landed after the last release, so no
> build currently in the REDCap Community repo has it. I'm cutting a release now and will follow up
> here when it's available.
>
> One note in case it helps you work around it meanwhile: whether the invisible row lands on the
> field depends on layout, so the same survey can be broken at desktop width and fine on a phone —
> it isn't intermittent, just position-dependent.

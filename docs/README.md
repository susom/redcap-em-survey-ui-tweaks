# Survey UI Tweaks — engineering notes

| doc | what it covers |
|---|---|
| [matrix-ranking-branching-fix.md](matrix-ranking-branching-fix.md) | The three root causes behind the 2026-07-23 Enhanced Ranked Matrix (drag/drop) fixes: stripped `doBranching()`, stale value on drag-out, and the invisible header overlay that swallows clicks. |
| [community-report-2026-09-17-textbox-below-dnd-matrix.md](community-report-2026-09-17-textbox-below-dnd-matrix.md) | Triage of the community report "text box beneath the drag-and-drop matrix cannot be selected". Already fixed on `master` but unreleased (Community has 1.2.2); includes the A/B/C repro, measurements, and release notes. |
| [e2e/matrix-dnd-clickthrough.mjs](e2e/matrix-dnd-clickthrough.mjs) | Playwright regression probe: fields below a drag/drop ranking matrix must stay clickable, on load and after a drag, desktop and mobile. |

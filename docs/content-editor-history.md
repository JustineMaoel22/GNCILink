# Content Editor undo and redo

The preview toolbar provides Undo and Redo. Use Ctrl/Cmd+Z to undo,
Ctrl+Y or Ctrl/Cmd+Shift+Z to redo. History covers the page's pending text,
formatting, media, icons, ordering, added/removed elements and layout changes.
Continuous typing in one field is grouped into short actions. Starting a new
edit after undo clears the redo branch. The toolbar disables unavailable actions.

History is local to the open preview, retains up to 50 changes, and is cleared
when the page is reloaded or switched. A restored private draft becomes the
starting point. Save draft preserves history for the current session; undoing
after saving changes the working preview and requires another explicit save.
Undo and redo never save, submit, approve or publish content.

Independent More settings forms and embedded post editors keep their existing
save and native input behavior. Actions that already commit to the server keep
that behavior and establish a new history baseline, including successful parts
of a partial publish. History cannot reverse an already committed server action.
Popups and ordinary form controls keep native text undo; applying a popup's
changes adds them to preview history. Preview mode disables history controls.

Implementation: `assets/js/editor-history.js` retains in-memory state and DOM
nodes, preserving removed content and its event handlers without reloading or
executing stored HTML. Adapters use their existing `draftState`/`draftRebind`
interface. Upload Blob references and replacement preview URLs remain available
for redo. No database migration or additional dependency is needed.

Image-loader completion and scroll-reveal completion are preserved when restoring
nodes. Undo must not remove `is-loaded`/`is-visible` or reintroduce a completed
hero's skeleton: cached media does not emit another load event to reveal itself.
Deferred homepage backgrounds and videos are resolved from their saved source
when restored. Their intersection observers stop watching after the first load,
so restoring a pre-scroll snapshot must not leave them waiting for another load.

Verification: `node tests/editor-history-browser-test.cjs` uses authenticated local
Chrome and Docker, modifies only the working preview, and checks text history,
shortcuts and redo branches on 18 editor routes, formatting, image insertion and
deletion, history after an explicit (intercepted) draft save, native popup input
shortcuts and responsive toolbar controls. Isolated browser checks also verify
revoked image preview recovery, Blob identity, typing groups and the 50-change
limit. PHP/JavaScript syntax checks and diff whitespace checks passed. No live
publication was performed during verification.

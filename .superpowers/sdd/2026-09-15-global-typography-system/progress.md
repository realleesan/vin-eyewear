# Global typography system — progress ledger

This ledger was restored on 2026-09-16 because the referenced prior worktree
and its original ledger were absent. Commit history is the provenance below.

Task 1: complete (commit 6d54119)
Task 2: complete (commits bf4643e..ec1249a)
Task 3: complete (commits ec1249a..360dff1, review clean)
Task 4: complete (storefront page CSS migration, review clean)

## Task 3 review package

- Brief: migrate storefront primitives in `assets/css/oa.css` and shared
  components to semantic typography tokens without changing selectors or UI.
- Implementer report: commit `360dff1` ("Chuẩn hoá typography thành phần
  storefront"); no standalone historical report was present in the restored
  worktree.
- Review diff: `ec1249a..360dff1`.
- Reviewer verdict: clean. `git diff --check` and
  `php tools/verify-typography_self_test.php` passed; the full guardrail
  reported only files intentionally deferred to Tasks 4–6.

## Task 4 implementation report and review

- Migrated the listed 14 storefront stylesheets from literal and compatibility
  `font-size` values to canonical semantic `--fs-*` tokens. Contact and
  Booking no longer define their own font-size scales; page-title clamps use
  the central `--fs-title` token.
- Hardened `tools/verify-typography.php` so a declaration at the end of a
  minified rule (`}` without a final semicolon) is inspected too.
- Focused verification: PHP lint and typography checker self-test passed;
  `git diff --check` passed. The full guardrail failed only for the explicitly
  deferred Task 5–6 admin CSS, with no Task 4 target errors.
- Reviewer verdict: clean after removal of the redundant Contact media query
  that contained only font-size overrides.

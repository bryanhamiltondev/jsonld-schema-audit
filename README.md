jsonld-schema-audit

<img width="170" height="20" alt="image" src="https://github.com/user-attachments/assets/a54d4426-8e79-4903-afd6-2959b66ce411" />


 (https://github.com/bryanhamiltondev/jsonld-schema-audit/actions/workflows/audit.yml)

A zero-dependency PHP CLI tool that audits HTML for JSON-LD structured data -
the tool that took The DJ Calendar (https://thedjcalendar.com) from hundreds
of Rich Results warnings to zero across ~1,000 pages.

Why it exists

The dominant structured-data failure on large sites is silent: event nodes
reference venues, performers, or webpages via @id without the referenced
node ever being embedded in the page's @graph. Google's Rich Results Test
reports the symptom ("references missing @id") page by page, but nothing
tells you the root cause or catches regressions.

This tool does all three things nothing else does in one pass:

1. Type validation - required properties per schema.org type
   (MusicEvent, Place, MusicGroup, Organization, FAQPage, ...)
2. @id resolution - every internal #fragment reference must point to
   a node that actually exists in the same graph. The checker runs two
   passes: first it indexes every node carrying an @id, then it walks
   every remaining property - so a reference is only "resolved" if the
   node it points to is defined, not merely mentioned.
3. Parse detection - unparsable JSON-LD blocks are surfaced as errors,
   not silently skipped

Usage

php audit.php path/to/html/            # audit a directory (*.html, *.php)
php audit.php page.html                # audit one file
php audit.php ./ --report=out.txt      # write errors to a file

Exit code 0 means clean, 1 means errors found - drop it into CI and
broken schema can never merge.

Example

$ php audit.php tests/fixtures/broken.html
=== SCHEMA AUDIT: 2 error(s) across 1 block(s) ===

tests/fixtures/broken.html  [JSON-LD block 1]
  [MusicEvent] missing required property "performer" on https://example.com/event/2#event
  [MusicEvent] property "@id" references unresolved @id "https://example.com/venue/ghost#venue"

Clean pages are silent:

$ php audit.php tests/fixtures/good.html
=== SCHEMA AUDIT: clean. 0 errors across 1 file(s). ===

CI

This repo audits itself. The GitHub Actions workflow (.github/workflows/audit.yml)
runs on every push and asserts both behaviors:

- the good fixture must pass the audit
- the broken fixture must fail the audit

If the tool ever stops catching the failure it was built for, CI goes red.

Requirements

- PHP 7.4+ (no extensions, no composer - one file, drop it anywhere)

License

MIT

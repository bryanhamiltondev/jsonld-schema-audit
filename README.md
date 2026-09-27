# jsonld-schema-audit

A zero-dependency PHP CLI tool that audits HTML for JSON-LD structured data -
the tool that took [The DJ Calendar](https://thedjcalendar.com) from hundreds
of Rich Results warnings to **zero** across ~1,000 pages.

## Why it exists

The dominant structured-data failure on large sites is silent: event nodes
reference venues, performers, or webpages via `@id` without the referenced
node ever being embedded in the page's `@graph`. Google's Rich Results Test
reports the symptom ("references missing @id") page by page, but nothing
tells you the root cause or catches regressions.

This tool does both:

1. **Type validation** - required properties per schema.org type
   (MusicEvent, Place, MusicGroup, Organization, FAQPage, ...)
2. **@id resolution** - every internal `#fragment` reference must point to
   a node that actually exists in the same graph
3. **Parse detection** - unparsable JSON-LD blocks are surfaced, not skipped

## Usage

```bash
php audit.php path/to/html/            # audit a directory
php audit.php page.html                # audit one file
php audit.php ./ --report=out.txt      # write errors to a file
```

Exit code `0` means clean, `1` means errors found - drop it into CI and
broken schema can never merge.

## Example

```
$ php audit.php tests/fixtures/broken.html
=== SCHEMA AUDIT: 2 error(s) across 1 block(s) ===

tests/fixtures/broken.html  [JSON-LD block 1]
  [MusicEvent] missing required property "performer" on https://example.com/event/2#event
  [MusicEvent] property "@id" references unresolved @id "https://example.com/venue/ghost#venue"
```

## Requirements

- PHP 7.4+ (no extensions, no composer)

## License

MIT

<?php
/**
 * jsonld-schema-audit
 *
 * A zero-dependency PHP CLI tool that audits HTML files for JSON-LD
 * structured data. It validates required properties per schema.org type
 * and verifies that every internal @id reference resolves to a node
 * actually present in the page's @graph.
 *
 * Built to catch the failure mode that quietly breaks Rich Results:
 * event/organization nodes referencing entities (venues, performers,
 * webpages) via @id without ever embedding the referenced node.
 *
 * Usage:
 *   php audit.php <directory-or-file> [--report=report.txt]
 *
 * Exit codes: 0 = clean, 1 = errors found, 2 = usage/runtime error.
 *
 * @author Bryan Hamilton
 * @license MIT
 */

if (PHP_SAPI !== 'cli') {
    exit("This tool must be run from the command line.\n");
}

const KNOWN_TYPES = [
    'MusicEvent'     => ['name', 'startDate', 'location', 'performer'],
    'MusicGroup'     => ['name'],
    'Place'          => ['name'],
    'Organization'   => ['name', 'url'],
    'WebPage'        => ['name'],
    'FAQPage'        => ['mainEntity'],
    'BreadcrumbList' => ['itemListElement'],
    'Person'         => ['name'],
];

$opts   = getopt('', ['report::']);
$target = isset($argv[1]) ? $argv[1] : null;

if ($target === null || !file_exists($target)) {
    fwrite(STDERR, "Usage: php audit.php <directory-or-file> [--report=report.txt]\n");
    exit(2);
}

if (is_dir($target)) {
    $files = array_merge(
        glob(rtrim($target, '/') . '/*.html'),
        glob(rtrim($target, '/') . '/*.php')
    );
    if (!is_array($files)) { $files = []; }
} else {
    $files = [$target];
}

$totalErrors = 0;
$report      = [];

foreach ($files as $file) {
    $html = file_get_contents($file);
    if ($html === false) { continue; }

    $blocks = extract_jsonld($html);
    foreach ($blocks as $i => $block) {
        $errors = audit_graph($block);
        if ($errors) {
            $totalErrors += count($errors);
            $report[] = $file . "  [JSON-LD block " . ($i + 1) . "]\n  "
                . implode("\n  ", $errors);
        }
    }
}

if ($totalErrors === 0) {
    echo "=== SCHEMA AUDIT: clean. 0 errors across " . count($files) . " file(s). ===\n";
} else {
    echo "=== SCHEMA AUDIT: " . $totalErrors . " error(s) across "
        . count($report) . " block(s) ===\n\n";
    foreach ($report as $r) { echo $r . "\n\n"; }
}

if (!empty($opts['report']) && $totalErrors > 0) {
    file_put_contents($opts['report'], implode("\n\n", $report));
}

exit($totalErrors > 0 ? 1 : 0);

/* ------------------------------------------------------------------ */

/**
 * Extract every JSON-LD block from an HTML document.
 *
 * @return array[] Each element is a flat list of graph nodes.
 */
function extract_jsonld($html)
{
    $blocks = [];
    preg_match_all(
        '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
        $html,
        $matches
    );

    foreach ($matches[1] as $raw) {
        $decoded = json_decode(trim($raw), true);
        if (!is_array($decoded)) {
            $blocks[] = [['__parse_error__' => 'unparsable JSON-LD block']];
            continue;
        }
        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            $blocks[] = $decoded['@graph'];
        } else {
            $blocks[] = [$decoded];
        }
    }

    return $blocks;
}

/**
 * Audit one graph: required properties per type + @id resolution.
 *
 * @return string[] Error lines (empty = clean).
 */
function audit_graph(array $nodes)
{
    $errors    = [];
    $nodesById = [];

    // First pass: index every node carrying an @id (these are definitions).
    foreach ($nodes as $node) {
        if (is_array($node) && isset($node['@id']) && is_string($node['@id'])) {
            $nodesById[$node['@id']] = true;
        }
    }

    // Second pass: validate each node.
    foreach ($nodes as $node) {
        if (!is_array($node)) { continue; }

        if (isset($node['__parse_error__'])) {
            $errors[] = '[JSON] ' . $node['__parse_error__'];
            continue;
        }

        $type = isset($node['@type']) ? $node['@type'] : null;
        if ($type === null) { continue; }
        $types     = is_array($type) ? $type : [$type];
        $typeLabel = implode('/', $types);

        // Required properties.
        foreach ($types as $t) {
            if (!isset(KNOWN_TYPES[$t])) { continue; }
            foreach (KNOWN_TYPES[$t] as $required) {
                if (!isset($node[$required]) || $node[$required] === '' || $node[$required] === []) {
                    $id = isset($node['@id']) ? ' on ' . $node['@id'] : '';
                    $errors[] = '[' . $t . '] missing required property "' . $required . '"' . $id;
                }
            }
        }

        // Reference resolution: every nested "@id" value must point to a
        // node defined somewhere in this graph. The node's own top-level
        // @id is a definition, not a reference, so it is excluded.
        $copy = $node;
        unset($copy['@id']);
        array_walk_recursive($copy, function ($value, $key) use (&$errors, $nodesById, $typeLabel) {
            if (!is_string($value) || $key === '@context') { return; }
            if (preg_match('/^(?:https?:\/\/[^\\s#]+)?#[A-Za-z0-9_-]+$/', $value)
                && !isset($nodesById[$value])) {
                $errors[] = '[' . $typeLabel . '] property "' . $key
                    . '" references unresolved @id "' . $value . '"';
            }
        });
    }

    return $errors;
}

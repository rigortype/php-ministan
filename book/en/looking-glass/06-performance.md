# S6 — Performance

> *The code for this chapter lives in the snapshot [`impls/looking-glass/06-performance`](../../../impls/looking-glass/06-performance) — a slice of the live `dev/` tree taken at `git tag seasoned-06`.*

We’ve been stacking up precision in the type system. This chapter is a breather — we step away from types for a moment and look at the one thing that decides how fast a real tool *feels* in your hands: the **result cache**. On a few thousand files, re-analyzing everything on every change is a non-starter — in CI or on your laptop, a tool that keeps you waiting is one nobody runs. Before we move on to the finishing work in S7, let’s lay the groundwork for speed.

## How the cache works

We lean on one plain fact — **if a file’s contents haven’t changed, neither has its result**. So we key the result on a **hash of the contents** and hand back the saved one next time
([`ResultCache`](../../../impls/looking-glass/06-performance/src/Cache/ResultCache.php)):

```php
private function pathFor(string $code): string
{
    return $this->directory . '/' . sha1($this->salt . "\0" . $code) . '.json';
}
```

The key is the **salt**. When the analyzer’s logic or level changes, the results change too, so we mix the version and the level into the salt. The “version” here is a **schema-version constant string** that ministan carries and that you bump by hand whenever the analysis logic changes. As a result, raising the level or updating ministan **invalidates the cache automatically**. The file path is **not** part of the key — the same contents produce the same result wherever they live.

> Turn that around and you can see the gap: merely adding a custom rule doesn’t bump this version, so the cache can go stale. Real PHPStan weaves the PHPStan version *and* a hash of your configuration (the NEON) into its salt, precisely so this case isn’t missed.

## Wiring it into the analyzer

`Analyser` consults the cache before analyzing, and returns the hit if there is one
([`Analyser`](../../../impls/looking-glass/06-performance/src/Analyser/Analyser.php)):

```php
if ($this->cache !== null) {
    $cached = $this->cache->load($code);
    if ($cached !== null) {
        // The cache holds only (message, line). Re-attach the current file name.
        return array_map(fn ($e) => new Error($e['message'], $file, $e['line']), $cached);
    }
}
$errors = $this->computeErrors($code, $file);
$this->cache?->save($code, /* simplified errors */);
```

The cache stores only the message and the line, and re-attaches the file name on the way out. So a file with identical contents at a different path is handled correctly.

## Run it

```console
$ dev/bin/ministan analyse --cache src   # First run: compute and cache
$ dev/bin/ministan analyse --cache src   # Second run: unchanged files come from the cache
```

Only the files you changed are re-analyzed; the rest come back in an instant.

> Simplifications: we skip parallel execution (splitting across processes) and cache invalidation driven by cross-file dependencies. Because ministan analyzes files independently (external symbols go through native reflection), the content hash alone keeps things consistent. Invalidation that propagates a changed class definition out to its callers is the domain of a full-blown `DependencyTracker`.

## Summary

- Cache results keyed on a content hash, and skip re-analyzing files that haven’t changed.
- Mix the version and level into the salt, so a logic change invalidates the cache automatically.
- Parallel execution and cross-file dependency invalidation belong to a full implementation (`DependencyTracker`).

In the next chapter, S7 — the final chapter of the advanced volume — we tighten the **precision** of inference and checking: the result type of a `match` expression, supertype absorption in unions, type widening over loops, and named arguments.

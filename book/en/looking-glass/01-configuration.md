# S1 — Configuration and extension

> *The code for this chapter lives in the snapshot [`impls/looking-glass/01-configuration`](../../../impls/looking-glass/01-configuration) — a slice of the live `dev/` tree taken at `git tag seasoned-01`.*

**Welcome to ministan Through PHP’s Looking-Glass — the advanced volume.** The second book starts
here. We take the ministan whose core we threaded through in the basics — parse → scope → type
inference → narrowing → rules → report — and, on top of it, add the precision and machinery that
pay off in the field. (We assume you’ve finished the basics volume.) For what S1 through S7 cover,
see “[The map — two volumes](../README.md)” in the front matter. This volume is harder
by design, and the first step toward everyday use is configuration — the doorway to it.

In the basics we ran everything from CLI flags alone. In practice you want the level, the paths, the
errors to ignore, and your own custom rules collected in a **config file**. Following PHPStan, we
adopt **NEON**.

## Why NEON

Matching the feel of `phpstan.neon` means a PHPStan user can read ours at a glance. We pull in
`nette/neon` and, in
[`ConfigurationLoader`](../../../impls/looking-glass/01-configuration/src/Configuration/ConfigurationLoader.php),
copy it into a
[`Configuration`](../../../impls/looking-glass/01-configuration/src/Configuration/Configuration.php):

```neon
parameters:
    level: 6
    paths:
        - src
    ignoreErrors:
        - '#Call to an undefined method#'
rules:
    - App\Rules\MyRule
```

```php
$data = Neon::decode((string) file_get_contents($file));
$parameters = (array) ($data['parameters'] ?? []);
return new Configuration(
    (int) ($parameters['level'] ?? RuleRegistryFactory::DEFAULT_LEVEL),
    $this->stringList($parameters['paths'] ?? []),
    $this->stringList($parameters['ignoreErrors'] ?? []),
    $this->stringList($data['rules'] ?? []),
);
```

## The CLI overrides the config

By default `AnalyseCommand` reads `ministan.neon` and lets CLI arguments override it (`--level` and
explicit paths take precedence over the NEON file). Stacking these **layers of configuration**
cleanly is what makes an analysis tool pleasant to use:

```php
$config = $this->loadConfiguration($configFile);
$level  = $cliLevel ?? $config->level;
$paths  = $cliPaths !== [] ? $cliPaths : $config->paths;
```

## ignoreErrors — silencing by pattern

Where a baseline ignores *this specific spot*, `ignoreErrors` silences *this **kind** of message*
with a regular expression
([`IgnoredErrorHelper`](../../../impls/looking-glass/01-configuration/src/Configuration/IgnoredErrorHelper.php)):

```php
foreach ($this->patterns as $pattern) {
    if (@preg_match($pattern, $message) === 1) {
        return true;
    }
}
```

```console
$ dev/bin/ministan analyse --configuration=examples/looking-glass/ministan.neon
[OK] No errors   # ignoreErrors swallowed the undefined-method finding
```

## The extension point — custom rules

You can register the classes listed under `rules:` in the config.
`RuleRegistryFactory::createForLevel()` appends these **extra rules** to the built-in ones:

```php
public function createForLevel(int $level, array $extraRules = []): RuleRegistry
{
    // …select the built-in rules by level…
    foreach ($extraRules as $rule) {
        $active[] = $rule;
    }
    return new RuleRegistry($active);
}
```

`AnalyseCommand` instantiates each from its class name (as long as it’s autoloadable and implements
`Rule`) and registers it. With that, a user can add rules expressing their own project conventions —
this extension point is exactly why PHPStan’s ecosystem is so rich.

## Summary

- Configuration is read as NEON (`nette/neon`) and gathered into a `Configuration`.
- The layers are arranged so the CLI overrides the NEON file.
- `ignoreErrors` silences by pattern; a baseline silences by spot.
- We opened an extension point: `rules:` registers custom rules.

In the next chapter, S2, we go deeper on **array types** — the `array{...}` constant array shape, the
return types of array access, and element-type inference over `foreach`.

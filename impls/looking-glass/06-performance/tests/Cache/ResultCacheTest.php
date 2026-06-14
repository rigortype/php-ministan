<?php

declare(strict_types=1);

namespace Ministan\Tests\Cache;

use Ministan\Analyser\Analyser;
use Ministan\Cache\ResultCache;
use Ministan\Rules\RuleRegistryFactory;
use PHPUnit\Framework\TestCase;

final class ResultCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ministan-cache-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            foreach (glob($this->dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->dir);
        }
    }

    public function testStoreAndLoad(): void
    {
        $cache = new ResultCache($this->dir, 'salt');

        self::assertNull($cache->load('<?php $x = 1;'));

        $cache->save('<?php $x = 1;', [['message' => 'boom', 'line' => 1]]);

        self::assertSame([['message' => 'boom', 'line' => 1]], $cache->load('<?php $x = 1;'));
    }

    public function testSaltInvalidatesCache(): void
    {
        (new ResultCache($this->dir, 'level=0'))->save('code', [['message' => 'm', 'line' => 1]]);

        // 別 salt（例: レベル変更）では同じ内容でもヒットしない。
        self::assertNull((new ResultCache($this->dir, 'level=9'))->load('code'));
    }

    public function testAnalyserReturnsSameResultFromCache(): void
    {
        $cache = new ResultCache($this->dir, 'v1');
        $analyser = new Analyser((new RuleRegistryFactory())->createForLevel(0), $cache);
        $fixture = __DIR__ . '/../fixtures/undefined-method.php';

        $cold = $analyser->analyseFile($fixture); // 計算してキャッシュ
        $warm = $analyser->analyseFile($fixture); // キャッシュから

        self::assertCount(1, $cold);
        self::assertSame(
            array_map(static fn ($e): string => $e->message, $cold),
            array_map(static fn ($e): string => $e->message, $warm),
        );
        self::assertNotEmpty(glob($this->dir . '/*'));
    }
}

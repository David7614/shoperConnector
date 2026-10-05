<?php
namespace app\services;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Yii;

/**
 * S3-compatible object storage for XML feeds (Stackhero MinIO).
 * Required env vars: STACKHERO_MINIO_HOST, STACKHERO_MINIO_ROOT_ACCESS_KEY, STACKHERO_MINIO_ROOT_SECRET_KEY
 * Optional env vars: MINIO_BUCKET (default: feeds)
 */
class FeedStorageService
{
    private S3Client $s3;
    private string $bucket;

    /** @var array<string,true>|null  Request-level cache of existing keys */
    private static ?array $keysCache = null;

    public function __construct(S3Client $s3, string $bucket)
    {
        $this->s3 = $s3;
        $this->bucket = $bucket;
    }

    public static function isConfigured(): bool
    {
        return (bool) getenv('STACKHERO_MINIO_HOST');
    }

    public static function create(): self
    {
        $host   = getenv('STACKHERO_MINIO_HOST');
        $key    = getenv('STACKHERO_MINIO_ROOT_ACCESS_KEY');
        $secret = getenv('STACKHERO_MINIO_ROOT_SECRET_KEY');
        $bucket = getenv('MINIO_BUCKET') ?: 'feeds';
        $region = 'us-east-1';

        if (!$host || !$key || !$secret) {
            throw new \RuntimeException('MinIO not configured. Set STACKHERO_MINIO_HOST, STACKHERO_MINIO_ROOT_ACCESS_KEY, STACKHERO_MINIO_ROOT_SECRET_KEY env vars.');
        }

        $endpoint = 'https://' . $host;

        $s3 = new S3Client([
            'version'                  => 'latest',
            'region'                   => $region,
            'endpoint'                 => $endpoint,
            'use_path_style_endpoint'  => true,
            'credentials'              => [
                'key'    => $key,
                'secret' => $secret,
            ],
        ]);

        $instance = new self($s3, $bucket);
        $instance->ensureBucket();
        return $instance;
    }

    private function ensureBucket(): void
    {
        if (!$this->s3->doesBucketExist($this->bucket)) {
            $this->s3->createBucket(['Bucket' => $this->bucket]);
        }
    }

    public function exists(string $key): bool
    {
        return $this->s3->doesObjectExist($this->bucket, $key);
    }

    /**
     * Cached version of exists() — loads all feed keys once per request.
     * Use this when checking many keys in a loop (e.g. admin user list).
     */
    public function existsCached(string $key): bool
    {
        if (self::$keysCache === null) {
            $this->warmCache();
        }
        return isset(self::$keysCache[$key]);
    }

    /**
     * Invalidate the request-level cache (call after put/delete in the same request).
     */
    public function invalidateCache(): void
    {
        self::$keysCache = null;
    }

    private function warmCache(): void
    {
        self::$keysCache = [];
        $types = ['product', 'order', 'customer', 'category'];

        foreach ($types as $type) {
            $params = ['Bucket' => $this->bucket, 'Prefix' => $type . '/'];

            do {
                $result = $this->s3->listObjectsV2($params);
                foreach ($result['Contents'] ?? [] as $object) {
                    self::$keysCache[$object['Key']] = true;
                }
                $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
            } while ($result['IsTruncated']);
        }
    }

    /**
     * Upload a single page chunk. Key format: {baseKey}.chunk.{05d-padded-index}
     */
    public function putChunk(string $baseKey, int $chunk, string $content): void
    {
        $this->s3->putObject([
            'Bucket' => $this->bucket,
            'Key'    => sprintf('%s.chunk.%05d', $baseKey, $chunk),
            'Body'   => $content,
        ]);
    }

    /**
     * List, concat and delete all chunks for a base key.
     * Logs a warning if found count differs from $expectedCount.
     */
    public function collectAndDeleteChunks(string $baseKey, int $expectedCount = -1): string
    {
        $prefix = $baseKey . '.chunk.';
        $params = ['Bucket' => $this->bucket, 'Prefix' => $prefix];
        $keys   = [];

        do {
            $result = $this->s3->listObjectsV2($params);
            foreach ($result['Contents'] ?? [] as $obj) {
                $keys[] = $obj['Key'];
            }
            $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
        } while (!empty($result['IsTruncated']));

        sort($keys);

        if ($expectedCount > 0 && count($keys) !== $expectedCount) {
            echo "WARNING: expected {$expectedCount} chunks, found " . count($keys) . " — feed may be incomplete" . PHP_EOL;
            for ($i = 0; $i < $expectedCount; $i++) {
                $expected = sprintf('%s.chunk.%05d', $baseKey, $i);
                if (!in_array($expected, $keys)) {
                    echo "  missing chunk: {$expected}" . PHP_EOL;
                }
            }
        }

        $buffer = '';
        foreach ($keys as $key) {
            $obj     = $this->s3->getObject(['Bucket' => $this->bucket, 'Key' => $key]);
            $buffer .= (string) $obj['Body'];
            $this->s3->deleteObject(['Bucket' => $this->bucket, 'Key' => $key]);
        }

        return $buffer;
    }

    /**
     * Check whether any chunks exist for a base key (used instead of exists() for temp files).
     */
    public function chunksExist(string $baseKey): bool
    {
        $result = $this->s3->listObjectsV2([
            'Bucket'  => $this->bucket,
            'Prefix'  => $baseKey . '.chunk.',
            'MaxKeys' => 1,
        ]);
        return !empty($result['Contents']);
    }

    public function chunkExists(string $baseKey, int $chunk): bool
    {
        return $this->s3->doesObjectExist($this->bucket, sprintf('%s.chunk.%05d', $baseKey, $chunk));
    }

    public function deleteChunks(string $baseKey): void
    {
        $prefix = $baseKey . '.chunk.';
        $params = ['Bucket' => $this->bucket, 'Prefix' => $prefix];

        do {
            $result = $this->s3->listObjectsV2($params);
            foreach ($result['Contents'] ?? [] as $obj) {
                $this->s3->deleteObject(['Bucket' => $this->bucket, 'Key' => $obj['Key']]);
            }
            $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
        } while (!empty($result['IsTruncated']));
    }

    public function stream(string $key, int $chunkSize = 1048576): void
    {
        $result = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
            '@http'  => ['stream' => true],
        ]);
        $body = $result['Body'];
        while (!$body->eof()) {
            echo $body->read($chunkSize);
            if (ob_get_level()) ob_flush();
            flush();
        }
    }

    /**
     * Rozmiar i data modyfikacji obiektu - bez pobierania tresci.
     */
    public function stat(string $key): array
    {
        $head = $this->s3->headObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);

        return [
            'size'  => (int) $head['ContentLength'],
            'mtime' => (string) $head['LastModified'],
        ];
    }

    /**
     * Zlicza wystapienia w obiekcie bez wciagania go do pamieci.
     * Feedy potrafia miec setki MB, a memory_limit na dynie to 128M.
     */
    public function countOccurrences(string $key, string $needle, int $chunkSize = 1048576): int
    {
        $result = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
            '@http'  => ['stream' => true],
        ]);

        $body = $result['Body'];

        $reader = (static function () use ($body, $chunkSize): \Generator {
            while (!$body->eof()) {
                yield $body->read($chunkSize);
            }
        })();

        return self::countInChunks($reader, $needle);
    }

    /**
     * Jak countOccurrences(), ale wynik jest zapamietywany. Klucz cache zawiera
     * rozmiar i date modyfikacji obiektu, wiec po przebudowie feedu licznik
     * przeliczy sie sam.
     */
    public function countOccurrencesCached(string $key, string $needle): int
    {
        $cache = Yii::$app->has('cache') ? Yii::$app->cache : null;

        if (!$cache) {
            return $this->countOccurrences($key, $needle);
        }

        $stat     = $this->stat($key);
        $cacheKey = ['feed-elements', $key, $needle, $stat['size'], $stat['mtime']];

        $cached = $cache->get($cacheKey);
        if ($cached !== false) {
            return (int) $cached;
        }

        $count = $this->countOccurrences($key, $needle);
        $cache->set($cacheKey, $count);

        return $count;
    }

    /**
     * Odpowiednik countOccurrences() dla pliku na dysku.
     */
    public static function countOccurrencesInFile(string $file, string $needle, int $chunkSize = 1048576): int
    {
        $handle = @fopen($file, 'rb');
        if (!$handle) {
            return 0;
        }

        $reader = (static function () use ($handle, $chunkSize): \Generator {
            while (!feof($handle)) {
                yield fread($handle, $chunkSize);
            }
        })();

        $count = self::countInChunks($reader, $needle);
        fclose($handle);

        return $count;
    }

    /**
     * Odpowiednik countOccurrencesCached() dla pliku na dysku.
     */
    public static function countOccurrencesInFileCached(string $file, string $needle): int
    {
        $cache = Yii::$app->has('cache') ? Yii::$app->cache : null;

        if (!$cache || !is_file($file)) {
            return self::countOccurrencesInFile($file, $needle);
        }

        $cacheKey = ['feed-elements-file', $file, $needle, filesize($file), filemtime($file)];

        $cached = $cache->get($cacheKey);
        if ($cached !== false) {
            return (int) $cached;
        }

        $count = self::countOccurrencesInFile($file, $needle);
        $cache->set($cacheKey, $count);

        return $count;
    }

    /**
     * Ogon o dlugosci needle-1 przenosimy do kolejnej paczki, zeby nie zgubic
     * wystapienia rozjechanego na granicy chunkow. Podwojnie nie policzy, bo
     * w ogonie nie zmiesci sie cale needle.
     *
     * @param iterable<string|false> $chunks
     */
    private static function countInChunks(iterable $chunks, string $needle): int
    {
        $overlap = strlen($needle) - 1;
        $count   = 0;
        $tail    = '';

        foreach ($chunks as $data) {
            if ($data === '' || $data === false) {
                break;
            }
            $buffer = $tail . $data;
            $count += substr_count($buffer, $needle);
            $tail   = $overlap > 0 ? substr($buffer, -$overlap) : '';
        }

        return $count;
    }

    public function get(string $key): string
    {
        $result = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);
        return (string) $result['Body'];
    }

    public function put(string $key, string $content, string $contentType = 'application/octet-stream'): void
    {
        $this->s3->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'Body'        => $content,
            'ContentType' => $contentType,
        ]);
    }

    public function putFromFile(string $key, string $filePath, string $contentType = 'application/octet-stream'): void
    {
        $this->s3->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'SourceFile'  => $filePath,
            'ContentType' => $contentType,
        ]);
    }

    public function collectChunksToFile(string $baseKey, string $destFile, int $expectedCount, string $prefix = '', string $suffix = ''): bool
    {
        $keys   = [];
        $params = ['Bucket' => $this->bucket, 'Prefix' => $baseKey . '.chunk.'];
        do {
            $result = $this->s3->listObjectsV2($params);
            foreach ($result['Contents'] ?? [] as $obj) {
                $keys[] = $obj['Key'];
            }
            $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
        } while (!empty($result['IsTruncated']));
        sort($keys);

        if ($expectedCount > 0 && count($keys) !== $expectedCount) {
            echo "WARNING: expected {$expectedCount} chunks, found " . count($keys) . PHP_EOL;
            return false;
        }

        $fp = fopen($destFile, 'w');
        if (!$fp) return false;

        if ($prefix !== '') fwrite($fp, $prefix);
        foreach ($keys as $key) {
            $obj  = $this->s3->getObject(['Bucket' => $this->bucket, 'Key' => $key, '@http' => ['stream' => true]]);
            $body = $obj['Body'];
            while (!$body->eof()) {
                fwrite($fp, $body->read(65536));
            }
            $this->s3->deleteObject(['Bucket' => $this->bucket, 'Key' => $key]);
        }
        if ($suffix !== '') fwrite($fp, $suffix);
        fclose($fp);
        return true;
    }

    public function append(string $key, string $additionalContent): void
    {
        $existing = $this->exists($key) ? $this->get($key) : '';
        $this->put($key, $existing . $additionalContent);
    }

    public function delete(string $key): void
    {
        try {
            $this->s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
        } catch (S3Exception $e) {
            // ignore if not found
        }
    }
}

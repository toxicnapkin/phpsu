<?php
declare(strict_types=1);

namespace PHPSu\Config\Compression;

final class GzipCompression implements CompressionInterface
{
    public function getCompressCommand(): string
    {
        return ' | gzip';
    }

    public function getUnCompressCommand(): string
    {
        return 'gunzip | ';
    }
}

<?php
declare(strict_types=1);

namespace PHPSu\Command;

use PHPSu\Config\AppInstance;
use PHPSu\Config\Compression\CompressionInterface;
use PHPSu\Config\Compression\EmptyCompression;
use PHPSu\Config\Database;
use PHPSu\Config\DatabaseUrl;
use PHPSu\Config\GlobalConfig;
use PHPSu\Config\SshConfig;
use PHPSu\Helper\StringHelper;
use Symfony\Component\Console\Output\OutputInterface;

final class DatabaseCommand implements CommandInterface
{
    /** @var string */
    private $name;
    /** @var SshConfig */
    private $sshConfig;
    /** @var string[] */
    private $excludes = [];

    /** @var string */
    private $fromUrl;
    /** @var string */
    private $fromHost;

    /** @var string */
    private $toUrl;
    /** @var string */
    private $toHost;

    /** @var int */
    private $verbosity = OutputInterface::VERBOSITY_NORMAL;

    /** @var CompressionInterface */
    private $compression;

    public function __construct()
    {
        $this->setCompression(new EmptyCompression());
    }

    /**
     * @param GlobalConfig $global
     * @param string $fromInstanceName
     * @param string $toInstanceName
     * @param string $currentHost
     * @param bool $all
     * @param int $verbosity
     * @return DatabaseCommand[]
     */
    public static function fromGlobal(
        GlobalConfig $global,
        string $fromInstanceName,
        string $toInstanceName,
        string $currentHost,
        bool $all,
        int $verbosity
    ): array {
        $fromInstance = $global->getAppInstance($fromInstanceName);
        $toInstance = $global->getAppInstance($toInstanceName);

        $compression = static::getCompressionOverlap($fromInstance, $toInstance);

        $result = [];
        foreach ($global->getDatabases() as $databaseName => $database) {
            $fromDatabase = $database;
            if ($fromInstance->hasDatabase($databaseName)) {
                $fromDatabase = $fromInstance->getDatabase($databaseName);
            }
            $toDatabase = $database;
            if ($toInstance->hasDatabase($databaseName)) {
                $toDatabase = $toInstance->getDatabase($databaseName);
            }
            $result[] = static::fromAppInstances($fromInstance, $toInstance, $fromDatabase, $toDatabase, $currentHost, $all, $verbosity, $compression);
        }
        foreach ($fromInstance->getDatabases() as $databaseName => $fromDatabase) {
            if ($toInstance->hasDatabase($databaseName)) {
                $toDatabase = $toInstance->getDatabase($databaseName);
                $result[] = static::fromAppInstances($fromInstance, $toInstance, $fromDatabase, $toDatabase, $currentHost, $all, $verbosity, $compression);
            }
        }
        return $result;
    }

    public static function fromAppInstances(
        AppInstance $from,
        AppInstance $to,
        Database $fromDatabase,
        Database $toDatabase,
        string $currentHost,
        bool $all,
        int $verbosity,
        CompressionInterface $compression
    ): DatabaseCommand {
        $result = new static();
        $result->setName('database:' . $fromDatabase->getName());
        $result->setFromHost($from->getHost() === $currentHost ? '' : $from->getHost());
        $result->setToHost($to->getHost() === $currentHost ? '' : $to->getHost());
        $result->setFromUrl($fromDatabase->getUrl());
        $result->setToUrl($toDatabase->getUrl());
        $result->setVerbosity($verbosity);
        $result->setCompression($compression);
        if ($all === false) {
            $result->setExcludes(array_unique(array_merge($fromDatabase->getExcludes(), $toDatabase->getExcludes())));
        }
        return $result;
    }

    private static function getCompressionOverlap(AppInstance $fromInstance, AppInstance $toInstance): CompressionInterface
    {
        foreach ($fromInstance->getCompressions() as $fromCompression) {
            foreach ($toInstance->getCompressions() as $toCompression) {
                if ($fromCompression == $toCompression) {
                    return $fromCompression;
                }
            }
        }
        return new EmptyCompression();
    }


    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): DatabaseCommand
    {
        $this->name = $name;
        return $this;
    }

    public function getSshConfig(): SshConfig
    {
        return $this->sshConfig;
    }

    public function setSshConfig(SshConfig $sshConfig): DatabaseCommand
    {
        $this->sshConfig = $sshConfig;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getExcludes(): array
    {
        return $this->excludes;
    }

    /**
     * @param string[] $excludes
     * @return DatabaseCommand
     */
    public function setExcludes(array $excludes): DatabaseCommand
    {
        $this->excludes = $excludes;
        return $this;
    }

    public function getFromUrl(): string
    {
        return $this->fromUrl;
    }

    public function setFromUrl(string $fromUrl): DatabaseCommand
    {
        $this->fromUrl = $fromUrl;
        return $this;
    }

    public function getFromHost(): string
    {
        return $this->fromHost;
    }

    public function setFromHost(string $fromHost): DatabaseCommand
    {
        $this->fromHost = $fromHost;
        return $this;
    }

    public function getToUrl(): string
    {
        return $this->toUrl;
    }

    public function setToUrl(string $toUrl): DatabaseCommand
    {
        $this->toUrl = $toUrl;
        return $this;
    }

    public function getToHost(): string
    {
        return $this->toHost;
    }

    public function setToHost(string $toHost): DatabaseCommand
    {
        $this->toHost = $toHost;
        return $this;
    }

    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    public function setVerbosity(int $verbosity): DatabaseCommand
    {
        $this->verbosity = $verbosity;
        return $this;
    }

    public function getCompression(): CompressionInterface
    {
        return $this->compression;
    }

    public function setCompression(CompressionInterface $compression): DatabaseCommand
    {
        $this->compression = $compression;
        return $this;
    }

    public function generate(): string
    {
        $hostsDifferentiate = $this->getFromHost() !== $this->getToHost();
        $from = new DatabaseUrl($this->getFromUrl());
        $to = new DatabaseUrl($this->getToUrl());

        $dumpCmd = 'mysqldump ' . StringHelper::optionStringForVerbosity($this->getVerbosity()) . '--opt --skip-comments --single-transaction --lock-tables=false ' . $this->generateCliParameters(
            $from,
            false
        ) . $this->excludeParts($from->getDatabase());
        $dumpCmd .= $this->getDatabaseCreateStatement($to->getDatabase());
        $compressCmd = $this->getCompression()->getCompressCommand();
        $unCompressCmd = $this->getCompression()->getUnCompressCommand();
        $importCmd = 'mysql ' . $this->generateCliParameters($to, true);
        if ($hostsDifferentiate) {
            if ($this->getFromHost()) {
                $sshCommand = new SshCommand();
                $sshCommand->setSshConfig($this->getSshConfig());
                $sshCommand->setInto($this->getFromHost());
                $sshCommand->setVerbosity($this->getVerbosity());
                $dumpCmd = $sshCommand->generate($dumpCmd . $compressCmd);
            } else {
                $dumpCmd = $dumpCmd . $compressCmd;
            }
            if ($this->getToHost()) {
                $sshCommand = new SshCommand();
                $sshCommand->setSshConfig($this->getSshConfig());
                $sshCommand->setInto($this->getToHost());
                $sshCommand->setVerbosity($this->getVerbosity());
                $importCmd = $sshCommand->generate($unCompressCmd . $importCmd);
            } else {
                $importCmd = $unCompressCmd . $importCmd;
            }
            return $dumpCmd . ' | ' . $importCmd;
        }
        $sshCommand = new SshCommand();
        $sshCommand->setSshConfig($this->getSshConfig());
        $sshCommand->setInto($this->getFromHost());
        $sshCommand->setVerbosity($this->getVerbosity());
        return $sshCommand->generate($dumpCmd . ' | ' . $importCmd);
    }

    /**
     * @param DatabaseUrl $databaseUrl
     * @param bool $excludeDatabase
     * @return string
     */
    private function generateCliParameters(DatabaseUrl $databaseUrl, bool $excludeDatabase): string
    {
        $result = [];
        $result[] = '-h' . escapeshellarg($databaseUrl->getHost());
        if ($databaseUrl->getPort() !== 3306) {
            $result[] = '-P' . $databaseUrl->getPort();
        }
        $result[] = '-u' . escapeshellarg($databaseUrl->getUser());
        $result[] = '-p' . escapeshellarg($databaseUrl->getPassword());
        if (!$excludeDatabase) {
            $result[] = '' . escapeshellarg($databaseUrl->getDatabase());
        }
        return implode(' ', $result);
    }

    private function excludeParts(string $database): string
    {
        $excludeOptions = [];
        foreach ($this->getExcludes() as $exclude) {
            $excludeOptions[] = '--ignore-table=' . escapeshellarg($database . '.' . $exclude);
        }
        if ($excludeOptions) {
            return ' ' . implode(' ', $excludeOptions);
        }
        return '';
    }

    private function getDatabaseCreateStatement(string $targetDatabase): string
    {
        $escapedTargetDatabase = '`' . str_replace('`', '``', $targetDatabase) . '`';
        $intermediateSql = sprintf('CREATE DATABASE IF NOT EXISTS %s;USE %s;', $escapedTargetDatabase, $escapedTargetDatabase);
        return ' | (echo ' . escapeshellarg($intermediateSql) . ' && cat)';
    }
}

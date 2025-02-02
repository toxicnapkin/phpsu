<?php
declare(strict_types=1);

namespace PHPSu;

use PHPSu\Config\GlobalConfig;
use PHPSu\Options\SshOptions;
use PHPSu\Options\SyncOptions;
use Symfony\Component\Console\Output\OutputInterface;

interface ControllerInterface
{
    public function ssh(OutputInterface $output, GlobalConfig $config, SshOptions $options): int;

    /**
     * @return void
     */
    public function sync(OutputInterface $output, GlobalConfig $config, SyncOptions $options);

    /**
     * @return void
     */
    public function testSshConnection(OutputInterface $output, GlobalConfig $config, SyncOptions $options);
}

<?php
declare(strict_types=1);

namespace PHPSu\Tests;

use PHPSu\Config\ConfigurationLoader;
use PHPSu\Config\GlobalConfig;
use PHPSu\Controller;
use PHPSu\Options\SshOptions;
use PHPSu\Options\SyncOptions;
use PHPSu\Tests\TestHelper\BufferedConsoleOutput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class ControllerTest extends TestCase
{
    public function testEmptyConfigSshDryRun()
    {
        $output = new BufferedOutput();
        $config = new GlobalConfig();
        $config->addAppInstance('production', 'serverEu', '/var/www/prod');
        $config->addAppInstance('local');
        $controller = new Controller();
        $controller->ssh($output, $config, (new SshOptions('production'))->setDryRun(true));
        $this->assertSame("ssh -F '.phpsu/config/ssh_config' 'serverEu' -t 'cd '\''/var/www/prod'\''; bash --login'\n", $output->fetch());
    }

    public function testEmptyConfigSyncDryRun()
    {
        $output = new BufferedOutput();
        $config = new GlobalConfig();
        $config->addAppInstance('production', 'serverEu', '/var/www/prod');
        $config->addAppInstance('local');
        $controller = new Controller();
        $controller->sync($output, $config, (new SyncOptions('production'))->setDryRun(true)->setAll(true));
        $this->assertSame('', $output->fetch());
    }

    public function testFilesystemAndDatabase()
    {
        $config = new GlobalConfig();
        $config->addFilesystem('fileadmin', 'fileadmin');
        $config->addDatabase('database', 'mysql://test:aaaaaaaa@127.0.0.1/testdb');
        $config->addSshConnection('projectEu', 'ssh://project@project.com');
        $config->addAppInstance('testing', 'projectEu', '/srv/www/project/test.project');
        $config->addAppInstance('local', '', './testInstance')
            ->addDatabase('database', 'mysql://root:root@127.0.0.1/test1234');

        $output = new BufferedOutput();
        $controller = new Controller();
        $controller->sync($output, $config, (new SyncOptions('testing'))->setDryRun(true));
        $lines = [
            'filesystem:fileadmin',
            "rsync -az -e 'ssh -F '\''.phpsu/config/ssh_config'\''' 'projectEu:/srv/www/project/test.project/fileadmin/' './testInstance/fileadmin/'",
            'database:database',
            "ssh -F '.phpsu/config/ssh_config' 'projectEu' 'mysqldump --opt --skip-comments --single-transaction --lock-tables=false -h'\''127.0.0.1'\'' -u'\''test'\'' -p'\''aaaaaaaa'\'' '\''testdb'\'' | (echo '\''CREATE DATABASE IF NOT EXISTS `test1234`;USE `test1234`;'\'' && cat)' | mysql -h'127.0.0.1' -u'root' -p'root'",
            '',
        ];
        $this->assertSame($lines, explode("\n", $output->fetch()));
    }

    public function testExcludeShouldBePresentInRsyncCommand()
    {
        $config = new GlobalConfig();
        $config->addFilesystem('fileadmin', 'fileadmin')->addExclude('*.mp4')->addExclude('*.mp3')->addExcludes(['*.zip', '*.rar']);
        $config->addDatabase('database', 'mysql://test:aaaaaaaa@127.0.0.1/testdb');
        $config->addSshConnection('projectEu', 'ssh://project@project.com');
        $config->addAppInstance('testing', 'projectEu', '/srv/www/project/test.project');
        $config->addAppInstance('local', '', './testInstance')
            ->addDatabase('database', 'mysql://root:root@127.0.0.1/test1234');

        $output = new BufferedOutput();
        $controller = new Controller();
        $controller->sync($output, $config, (new SyncOptions('testing'))->setDryRun(true));
        $lines = [
            'filesystem:fileadmin',
            "rsync -az --exclude='*.mp4' --exclude='*.mp3' --exclude='*.zip' --exclude='*.rar' -e 'ssh -F '\''.phpsu/config/ssh_config'\''' 'projectEu:/srv/www/project/test.project/fileadmin/' './testInstance/fileadmin/'",
            'database:database',
            "ssh -F '.phpsu/config/ssh_config' 'projectEu' 'mysqldump --opt --skip-comments --single-transaction --lock-tables=false -h'\''127.0.0.1'\'' -u'\''test'\'' -p'\''aaaaaaaa'\'' '\''testdb'\'' | (echo '\''CREATE DATABASE IF NOT EXISTS `test1234`;USE `test1234`;'\'' && cat)' | mysql -h'127.0.0.1' -u'root' -p'root'",
            '',
        ];
        $this->assertSame($lines, explode("\n", $output->fetch()));
    }

    public function testExcludeShouldBePresentInDatabaseCommand()
    {
        $config = new GlobalConfig();
        $config->addDatabase('database', 'mysql://test:aaaaaaaa@127.0.0.1/testdb')->addExclude('table1')->addExclude('table2')->addExcludes(['table3', 'table4']);
        $config->addSshConnection('projectEu', 'ssh://project@project.com');
        $config->addAppInstance('testing', 'projectEu', '/srv/www/project/test.project');
        $config->addAppInstance('local', '', './testInstance')
            ->addDatabase('database', 'mysql://root:root@127.0.0.1/test1234')->addExclude('table1')->addExclude('table1');

        $output = new BufferedOutput();
        $controller = new Controller();
        $controller->sync($output, $config, (new SyncOptions('testing'))->setDryRun(true));
        $lines = [
            'database:database',
            "ssh -F '.phpsu/config/ssh_config' 'projectEu' 'mysqldump --opt --skip-comments --single-transaction --lock-tables=false -h'\''127.0.0.1'\'' -u'\''test'\'' -p'\''aaaaaaaa'\'' '\''testdb'\'' --ignore-table='\''testdb.table1'\'' --ignore-table='\''testdb.table2'\'' --ignore-table='\''testdb.table3'\'' --ignore-table='\''testdb.table4'\'' | (echo '\''CREATE DATABASE IF NOT EXISTS `test1234`;USE `test1234`;'\'' && cat)' | mysql -h'127.0.0.1' -u'root' -p'root'",
            '',
        ];
        $this->assertSame($lines, explode("\n", $output->fetch()));
    }

    public function testAllOptionShouldOverwriteExcludes()
    {
        $config = new GlobalConfig();
        $config->addFilesystem('fileadmin', 'fileadmin')->addExclude('*.mp4')->addExclude('*.mp3')->addExcludes(['*.zip', '*.rar']);
        $config->addDatabase('database', 'mysql://test:aaaaaaaa@127.0.0.1/testdb')->addExclude('table1')->addExclude('table2')->addExcludes(['table3', 'table4']);
        $config->addSshConnection('projectEu', 'ssh://project@project.com');
        $config->addAppInstance('testing', 'projectEu', '/srv/www/project/test.project');
        $config->addAppInstance('local', '', './testInstance')
            ->addDatabase('database', 'mysql://root:root@127.0.0.1/test1234')->addExclude('table1')->addExclude('table1');

        $output = new BufferedOutput();
        $controller = new Controller();
        $controller->sync($output, $config, (new SyncOptions('testing'))->setDryRun(true)->setAll(true));
        $lines = [
            'filesystem:fileadmin',
            "rsync -az -e 'ssh -F '\''.phpsu/config/ssh_config'\''' 'projectEu:/srv/www/project/test.project/fileadmin/' './testInstance/fileadmin/'",
            'database:database',
            "ssh -F '.phpsu/config/ssh_config' 'projectEu' 'mysqldump --opt --skip-comments --single-transaction --lock-tables=false -h'\''127.0.0.1'\'' -u'\''test'\'' -p'\''aaaaaaaa'\'' '\''testdb'\'' | (echo '\''CREATE DATABASE IF NOT EXISTS `test1234`;USE `test1234`;'\'' && cat)' | mysql -h'127.0.0.1' -u'root' -p'root'",
            '',
        ];
        $this->assertSame($lines, explode("\n", $output->fetch()));
    }

    public function testNoDbOptionShouldRemoveDatabaseCommand()
    {
        $config = new GlobalConfig();
        $config->addFilesystem('fileadmin', 'fileadmin')->addExclude('*.mp4')->addExclude('*.mp3')->addExcludes(['*.zip', '*.rar']);
        $config->addDatabase('database', 'mysql://test:aaaaaaaa@127.0.0.1/testdb')->addExclude('table1')->addExclude('table2')->addExcludes(['table3', 'table4']);
        $config->addSshConnection('projectEu', 'ssh://project@project.com');
        $config->addAppInstance('testing', 'projectEu', '/srv/www/project/test.project');
        $config->addAppInstance('local', '', './testInstance')
            ->addDatabase('database', 'mysql://root:root@127.0.0.1/test1234')->addExclude('table1')->addExclude('table1');

        $output = new BufferedOutput();
        $controller = new Controller();
        $controller->sync($output, $config, (new SyncOptions('testing'))->setDryRun(true)->setAll(true)->setNoDatabases(true));
        $lines = [
            'filesystem:fileadmin',
            "rsync -az -e 'ssh -F '\''.phpsu/config/ssh_config'\''' 'projectEu:/srv/www/project/test.project/fileadmin/' './testInstance/fileadmin/'",
            '',
        ];
        $this->assertSame($lines, explode("\n", $output->fetch()));
    }

    public function testNoFileOptionShouldRemoveDatabaseCommand()
    {
        $config = new GlobalConfig();
        $config->addFilesystem('fileadmin', 'fileadmin')->addExclude('*.mp4')->addExclude('*.mp3')->addExcludes(['*.zip', '*.rar']);
        $config->addDatabase('database', 'mysql://test:aaaaaaaa@127.0.0.1/testdb')->addExclude('table1')->addExclude('table2')->addExcludes(['table3', 'table4']);
        $config->addSshConnection('projectEu', 'ssh://project@project.com');
        $config->addAppInstance('testing', 'projectEu', '/srv/www/project/test.project');
        $config->addAppInstance('local', '', './testInstance')
            ->addDatabase('database', 'mysql://root:root@127.0.0.1/test1234')->addExclude('table1')->addExclude('table1');

        $output = new BufferedOutput();
        $controller = new Controller();
        $controller->sync($output, $config, (new SyncOptions('testing'))->setDryRun(true)->setAll(true)->setNoFiles(true));
        $lines = [
            'database:database',
            "ssh -F '.phpsu/config/ssh_config' 'projectEu' 'mysqldump --opt --skip-comments --single-transaction --lock-tables=false -h'\''127.0.0.1'\'' -u'\''test'\'' -p'\''aaaaaaaa'\'' '\''testdb'\'' | (echo '\''CREATE DATABASE IF NOT EXISTS `test1234`;USE `test1234`;'\'' && cat)' | mysql -h'127.0.0.1' -u'root' -p'root'",
            '',
        ];
        $this->assertSame($lines, explode("\n", $output->fetch()));
    }

    public function testUseCaseWithoutGlobalDatabase()
    {
        $config = new GlobalConfig();
        $config->addSshConnection('projectEu', 'ssh://project@project.com');
        $config->addAppInstance('testing', 'projectEu', '/srv/www/project/test.project')
            ->addDatabase('database', 'mysql://test:aaaaaaaa@127.0.0.1/testdb');
        $config->addAppInstance('local', '', './testInstance')
            ->addDatabase('database', 'mysql://root:root@127.0.0.1/test1234')->addExclude('table1')->addExclude('table1');

        $output = new BufferedOutput();
        $controller = new Controller();
        $controller->sync($output, $config, (new SyncOptions('testing'))->setDryRun(true)->setAll(true)->setNoFiles(true));
        $lines = [
            'database:database',
            "ssh -F '.phpsu/config/ssh_config' 'projectEu' 'mysqldump --opt --skip-comments --single-transaction --lock-tables=false -h'\''127.0.0.1'\'' -u'\''test'\'' -p'\''aaaaaaaa'\'' '\''testdb'\'' | (echo '\''CREATE DATABASE IF NOT EXISTS `test1234`;USE `test1234`;'\'' && cat)' | mysql -h'127.0.0.1' -u'root' -p'root'",
            '',
        ];
        $this->assertSame($lines, explode(PHP_EOL, $output->fetch()));
    }

    public function testUseCaseDatabaseOnlyDefinedOnOneEnd()
    {
        $config = new GlobalConfig();
        $config->addSshConnection('projectEu', 'ssh://project@project.com');
        $config->addDatabase('database', 'mysql://root:root@127.0.0.1/test1234');
        $testingApp = $config->addAppInstance('testing', 'projectEu', '/srv/www/project/test.project');
        $testingApp->addDatabase('database', 'mysql://test:aaaaaaaa@127.0.0.1/testdb');
        $testingApp->addDatabase('database2', 'mysql://test:aaaaaaaa@127.0.0.1/testdb2');
        $config->addAppInstance('local', '', './testInstance')
            ->addDatabase('database2', 'mysql://root:root@127.0.0.1/test1234_2');

        $output = new BufferedOutput();
        $controller = new Controller();
        $controller->sync($output, $config, (new SyncOptions('testing'))->setDryRun(true)->setAll(true)->setNoFiles(true));
        $lines = [
            'database:database',
            "ssh -F '.phpsu/config/ssh_config' 'projectEu' 'mysqldump --opt --skip-comments --single-transaction --lock-tables=false -h'\''127.0.0.1'\'' -u'\''test'\'' -p'\''aaaaaaaa'\'' '\''testdb'\'' | (echo '\''CREATE DATABASE IF NOT EXISTS `test1234`;USE `test1234`;'\'' && cat)' | mysql -h'127.0.0.1' -u'root' -p'root'",
            'database:database2',
            "ssh -F '.phpsu/config/ssh_config' 'projectEu' 'mysqldump --opt --skip-comments --single-transaction --lock-tables=false -h'\''127.0.0.1'\'' -u'\''test'\'' -p'\''aaaaaaaa'\'' '\''testdb2'\'' | (echo '\''CREATE DATABASE IF NOT EXISTS `test1234_2`;USE `test1234_2`;'\'' && cat)' | mysql -h'127.0.0.1' -u'root' -p'root'",
            '',
        ];
        $this->assertSame($lines, explode("\n", $output->fetch()));
    }

    public function testPhpApiReadmeExample()
    {
        $oldCwd = getcwd();
        chdir(__DIR__ . '/fixtures');
        $config = (new ConfigurationLoader())->getConfig();
        chdir($oldCwd);

        $log = new BufferedOutput();
        $syncOptions = new SyncOptions('production');
        $syncOptions->setDryRun(true);
        $phpsu = new Controller();
        $phpsu->sync($log, $config, $syncOptions);

        $this->assertSame('filesystem:var/storage' . PHP_EOL . 'rsync -az \'testProduction/var/storage/\' \'testLocal/var/storage/\'' . PHP_EOL, $log->fetch());
    }

    public function testSyncOutputHasSectionsWithEmptyConfigAndConsoleOutput()
    {
        $config = new GlobalConfig();
        $config->addAppInstance('production', 'localhost', __DIR__);
        $config->addAppInstance('local');
        $controller = new Controller();
        $syncOptions = new SyncOptions('production');
        $syncOptions->setNoDatabases(true);
        $syncOptions->setNoFiles(true);
        $output = new BufferedConsoleOutput();
        $controller->sync($output, $config, $syncOptions);
        rewind($output->getStream());
        $this->assertSame("--------------------\n", stream_get_contents($output->getStream()), 'Asserting result empty since config is empty as well');
    }

    public function testSyncOutputHasSectionsWithEmptyConfigAndBufferedOutput()
    {
        $config = new GlobalConfig();
        $config->addAppInstance('production', 'localhost', __DIR__);
        $config->addAppInstance('local');
        $controller = new Controller();
        $syncOptions = new SyncOptions('local');
        $syncOptions->setNoDatabases(true);
        $syncOptions->setNoFiles(true);
        $syncOptions->setDestination('production');
        $output = new BufferedOutput();
        $controller->sync($output, $config, $syncOptions);
        $this->assertSame('', $output->fetch(), 'Excepting sync to do nothing');
    }

    public function testSshOutputPassthruExecution()
    {
        $controller = new Controller();
        $config = new GlobalConfig();
        $config->addAppInstance('production', '127.0.0.1', __DIR__);
        $config->addAppInstance('local');
        $sshOptions = (new SshOptions('typo'))->setDestination('local');
        $output = new BufferedOutput();
        $this->expectExceptionMessage('the found host and the current Host are the same');
        $controller->ssh($output, $config, $sshOptions);
    }
}

<?php
declare(strict_types=1);

namespace PHPSu\Tests\Config;

use PHPSu\Config\SshUrl;
use PHPUnit\Framework\TestCase;

final class SshUrlTest extends TestCase
{
    public function testInvalidUrl()
    {
        $this->expectExceptionMessageRegExp('/SshUrl could not been parsed/');
        new SshUrl('://:/:/:/:/');
    }

    public function testInvalidUser()
    {
        $this->expectExceptionMessageRegExp('/User must be set/');
        new SshUrl('test');
    }

    public function testInvalidPort()
    {
        $dsn = new SshUrl('user@test');
        $this->expectExceptionMessage('port must be between 0 and 65535');
        $dsn->setPort(0);
    }

    public function testInvalidPort2()
    {
        $dsn = new SshUrl('user@test');
        $this->expectExceptionMessage('port must be between 0 and 65535');
        $dsn->setPort(65535);
    }

    public function testMinimumValidPort()
    {
        $dsn = new SshUrl('user@test');
        $dsn->setPort(1);
        $this->assertSame(1, $dsn->getPort());
    }

    public function testMaximumValidPort()
    {
        $dsn = new SshUrl('user@test');
        $dsn->setPort(65534);
        $this->assertSame(65534, $dsn->getPort());
    }

    public function testSshWithoutSchema()
    {
        $dsn = new SshUrl('user@host');
        $this->assertSame('user', $dsn->getUser());
        $this->assertSame('', $dsn->getPassword());
        $this->assertSame('host', $dsn->getHost());
        $this->assertSame(22, $dsn->getPort());
        $this->assertSame('ssh://user@host', $dsn->__toString());
    }

    public function testSshWithSchema()
    {
        $dsn = new SshUrl('ssh://user@host');
        $this->assertSame('user', $dsn->getUser());
        $this->assertSame('', $dsn->getPassword());
        $this->assertSame('host', $dsn->getHost());
        $this->assertSame(22, $dsn->getPort());
        $this->assertSame('ssh://user@host', $dsn->__toString());
    }

    public function testSshWithSchemaPort2206()
    {
        $dsn = new SshUrl('ssh://user@host:2206');
        $this->assertSame('user', $dsn->getUser());
        $this->assertSame('', $dsn->getPassword());
        $this->assertSame('host', $dsn->getHost());
        $this->assertSame(2206, $dsn->getPort());
        $this->assertSame('ssh://user@host:2206', $dsn->__toString());
    }

    public function testSshWithPassword()
    {
        $dsn = new SshUrl('ssh://user:password@host');
        $this->assertSame('user', $dsn->getUser());
        $this->assertSame('password', $dsn->getPassword());
        $this->assertSame('host', $dsn->getHost());
        $this->assertSame(22, $dsn->getPort());
        $this->assertSame('ssh://user:password@host', $dsn->__toString());
    }

    public function testSshWithIp()
    {
        $dsn = new SshUrl('user@192.168.0.1');
        $this->assertSame('user', $dsn->getUser());
        $this->assertSame('', $dsn->getPassword());
        $this->assertSame('192.168.0.1', $dsn->getHost());
        $this->assertSame(22, $dsn->getPort());
        $this->assertSame('ssh://user@192.168.0.1', $dsn->__toString());
    }

    public function testSshUrlGetter()
    {
        $dsn = new SshUrl('user@192.168.0.1');
        $this->assertSame('user', $dsn->getUser());
        $this->assertSame('', $dsn->getPassword());
        $this->assertSame('192.168.0.1', $dsn->getHost());
        $this->assertSame(22, $dsn->getPort());
        $this->assertSame('ssh://user@192.168.0.1', $dsn->__toString());
        $dsn->setUser('user2');
        $dsn->setPassword('pw2');
        $dsn->setHost('host2');
        $dsn->setPort(2298);
        $this->assertSame('user2', $dsn->getUser());
        $this->assertSame('pw2', $dsn->getPassword());
        $this->assertSame('host2', $dsn->getHost());
        $this->assertSame(2298, $dsn->getPort());
        $this->assertSame('ssh://user2:pw2@host2:2298', $dsn->__toString());
    }
}

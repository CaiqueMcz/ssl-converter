<?php

namespace CaiqueMcz\SslConverter\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use CaiqueMcz\SslConverter\Exceptions\ConversionException;
use CaiqueMcz\SslConverter\Utils\ProcessUtil;

class ProcessUtilTest extends TestCase
{
    public function testRunReturnsSuccessForValidCommand()
    {
        $process = new ProcessUtil(['echo', 'test']);
        $result = $process->run();

        $this->assertSame($process, $result);
        $this->assertTrue($process->isSuccessful());
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testGetOutputReturnsCommandOutput()
    {
        $process = new ProcessUtil(['echo', 'hello']);
        $process->run();

        $output = trim($process->getOutput());
        $this->assertEquals('hello', $output);
    }

    public function testRunReturnsFailureForInvalidCommand()
    {
        $process = new ProcessUtil(['invalid-command-xyz']);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertNotEquals(0, $process->getExitCode());
    }

    public function testGetErrorOutputReturnsStderr()
    {
        $process = new ProcessUtil(['sh', '-c', 'echo error >&2']);
        $process->run();

        $errorOutput = trim($process->getErrorOutput());
        $this->assertEquals('error', $errorOutput);
    }

    public function testMustRunThrowsExceptionOnFailure()
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessageMatches('/failed/i');

        $process = new ProcessUtil(['false']);
        $process->mustRun();
    }

    public function testMustRunReturnsSelfOnSuccess()
    {
        $process = new ProcessUtil(['true']);
        $result = $process->mustRun();

        $this->assertSame($process, $result);
        $this->assertTrue($process->isSuccessful());
    }

    public function testIsSuccessfulReturnsTrueForExitCode0()
    {
        $process = new ProcessUtil(['true']);
        $process->run();

        $this->assertTrue($process->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseForNonZeroExitCode()
    {
        $process = new ProcessUtil(['false']);
        $process->run();

        $this->assertFalse($process->isSuccessful());
    }

    public function testGetExitCodeReturnsCorrectValue()
    {
        $process = new ProcessUtil(['sh', '-c', 'exit 42']);
        $process->run();

        $this->assertEquals(42, $process->getExitCode());
    }

    public function testExceptionMessageContainsCommandInfo()
    {
        try {
            $process = new ProcessUtil(['sh', '-c', 'echo output && echo error >&2 && exit 1']);
            $process->mustRun();
            $this->fail('Expected exception was not thrown');
        } catch (ConversionException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('failed', $message);
            $this->assertStringContainsString('Exit Code: 1', $message);
            $this->assertStringContainsString('output', $message);
            $this->assertStringContainsString('error', $message);
        }
    }
}
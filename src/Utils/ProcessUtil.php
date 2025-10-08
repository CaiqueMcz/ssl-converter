<?php

namespace SslConverter\Utils;

use SslConverter\Exceptions\ConversionException;

class ProcessUtil
{
    private array $command;
    private ?string $output = null;
    private ?string $errorOutput = null;
    private ?int $exitCode = null;

    public function __construct(array $command)
    {
        $this->command = $command;
    }

    public function run()
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open($this->command, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            throw new ConversionException('Failed to start process');
        }

        fclose($pipes[0]);

        $this->output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $this->errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $this->exitCode = proc_close($process);

        return $this;
    }

    public function mustRun()
    {
        $this->run();

        if (!$this->isSuccessful()) {
            throw new ConversionException(
                sprintf(
                    'The command "%s" failed.' . "\n\nExit Code: %d\n\nOutput:\n%s\n\nError Output:\n%s",
                    $this->getCommandLine(),
                    $this->exitCode,
                    $this->output,
                    $this->errorOutput
                )
            );
        }

        return $this;
    }

    public function isSuccessful()
    {
        return $this->exitCode === 0;
    }

    public function getOutput()
    {
        return $this->output ?? '';
    }

    public function getErrorOutput()
    {
        return $this->errorOutput ?? '';
    }

    public function getExitCode()
    {
        return $this->exitCode;
    }

    private function getCommandLine()
    {
        return implode(' ', array_map(function ($arg) {
            return escapeshellarg($arg);
        }, $this->command));
    }
}
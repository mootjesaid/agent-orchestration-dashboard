<?php

namespace App\Actions\Repositories;

use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

class GitBroker
{
    private const OUTPUT_LIMIT = 4_194_304;

    public function prepare(string $repository, string $workspaceRoot, string $base, string $branch): string
    {
        $repository = $this->directory($repository);
        $workspaceRoot = $this->workspaceRoot($workspaceRoot);
        $base = $this->baseHash($base);
        $this->validateBranch($repository, $branch);

        $top = $this->directory(trim($this->git($repository, ['rev-parse', '--show-toplevel'])));
        if (! $this->samePath($top, $repository)) {
            throw new DomainException('Select the repository root.');
        }

        $common = $this->commonDirectory($repository);
        foreach ([$repository, $common] as $protectedRoot) {
            if ($this->within($workspaceRoot, $protectedRoot) || $this->within($protectedRoot, $workspaceRoot)) {
                throw new DomainException('Select a dedicated workspace root separate from the repository and its Git metadata.');
            }
        }

        return Cache::lock('git-broker:'.hash('sha256', $this->pathKey($common)), 120)->block(3, function () use ($repository, $common, $workspaceRoot, $base, $branch): string {
            if (! $this->samePath($this->commonDirectory($repository), $common)) {
                throw new DomainException('The repository metadata location changed.');
            }

            if (trim($this->git($repository, ['cat-file', '-t', $base])) !== 'commit') {
                throw new DomainException('The approved base must directly identify a local commit.');
            }

            if (trim($this->git($repository, ['for-each-ref', '--count=1', '--format=%(refname)', '--contains='.$base, 'refs/heads'])) === '') {
                throw new DomainException('The approved base must be reachable from a local branch.');
            }

            $workspace = $workspaceRoot.DIRECTORY_SEPARATOR.'task-'.Str::uuid();
            if (! @mkdir($workspace, 0700)) {
                throw new DomainException('The broker could not reserve a new task directory.');
            }

            try {
                $this->git($workspaceRoot, ['clone', '--no-local', '--no-checkout', '--no-recurse-submodules', '--', $repository, $workspace], 30);
                $this->git($workspace, ['remote', 'remove', 'origin']);
                $this->standaloneDirectory($workspace);
                $this->git($workspace, ['switch', '--no-guess', '-c', $branch, $base]);
                $this->assertExpectedHead($workspace, $base, $branch);
            } catch (DomainException $exception) {
                throw new DomainException($exception->getMessage()." The workspace was retained at $workspace", previous: $exception);
            }

            return $workspace;
        });
    }

    /**
     * @return array{diff: string, untracked: list<string>}
     */
    public function inspect(string $workspaceRoot, string $workspace, string $base, string $branch): array
    {
        $workspaceRoot = $this->workspaceRoot($workspaceRoot);
        $workspace = $this->directory($workspace);
        $base = $this->baseHash($base);

        if (! $this->samePath(dirname($workspace), $workspaceRoot) || ! Str::isUuid(substr(basename($workspace), 5)) || ! str_starts_with(basename($workspace), 'task-')) {
            throw new DomainException('Select a task clone directly under the approved workspace root.');
        }

        $this->validateBranch($workspace, $branch);
        $this->standaloneDirectory($workspace);
        $this->assertExpectedHead($workspace, $base, $branch);

        $scratch = $workspaceRoot.DIRECTORY_SEPARATOR.'inspect-'.Str::uuid();
        if (! @mkdir($scratch, 0700)) {
            throw new DomainException('The broker could not reserve an inspection directory.');
        }

        $index = $scratch.DIRECTORY_SEPARATOR.'index';
        try {
            $this->git($workspace, ['read-tree', $base], index: $index);
            $diff = $this->git($workspace, ['diff', '--binary', '--no-ext-diff', '--no-textconv', '--submodule=short', $base, '--'], index: $index);
            $paths = $this->git($workspace, ['ls-files', '--others', '--exclude-standard', '-z', '--'], index: $index);
            $this->assertExpectedHead($workspace, $base, $branch);
        } finally {
            foreach ([$index, $index.'.lock'] as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }
            rmdir($scratch);
        }

        return ['diff' => $diff, 'untracked' => $paths === '' ? [] : explode("\0", substr($paths, 0, -1))];
    }

    private function assertExpectedHead(string $workspace, string $base, string $branch): void
    {
        if (trim($this->git($workspace, ['symbolic-ref', 'HEAD'])) !== 'refs/heads/'.$branch
            || trim($this->git($workspace, ['rev-parse', '--verify', 'HEAD'])) !== $base) {
            throw new DomainException('The task clone no longer has the expected branch and base.');
        }
    }

    private function standaloneDirectory(string $workspace): void
    {
        $metadata = $workspace.DIRECTORY_SEPARATOR.'.git';
        $objects = $metadata.DIRECTORY_SEPARATOR.'objects';
        if (! is_dir($metadata) || ! $this->samePath($this->directory($metadata), $metadata)
            || ! $this->samePath($this->commonDirectory($workspace), $metadata)
            || ! is_dir($objects) || ! $this->samePath($this->directory($objects), $objects)
            || ! $this->samePath($this->directory(trim($this->git($workspace, ['rev-parse', '--show-toplevel']))), $workspace)
            || file_exists($metadata.'/objects/info/alternates')
            || trim($this->git($workspace, ['remote'])) !== '') {
            throw new DomainException('The workspace must have private Git metadata and no alternates or remotes.');
        }
    }

    private function commonDirectory(string $repository): string
    {
        return $this->directory(trim($this->git($repository, ['rev-parse', '--path-format=absolute', '--git-common-dir'])));
    }

    private function baseHash(string $base): string
    {
        if (! preg_match('/\A(?:[0-9a-f]{40}|[0-9a-f]{64})\z/i', $base)) {
            throw new DomainException('Supply the exact full commit hash.');
        }

        return strtolower($base);
    }

    private function validateBranch(string $directory, string $branch): void
    {
        if (! preg_match('~\Afeature/[a-zA-Z0-9][a-zA-Z0-9/._-]*\z~', $branch)) {
            throw new DomainException('Supply a feature branch under feature/.');
        }

        $this->git($directory, ['check-ref-format', 'refs/heads/'.$branch]);
    }

    private function directory(string $path): string
    {
        $directory = realpath($path);
        if ($directory === false || ! is_dir($directory)) {
            throw new DomainException('Select an existing directory.');
        }

        return $directory;
    }

    private function workspaceRoot(string $path): string
    {
        $root = $this->directory($path);
        $application = $this->directory(base_path());

        if ($this->within($root, $application) || $this->within($application, $root)) {
            throw new DomainException('Select a dedicated workspace root separate from the orchestrator application.');
        }

        return $root;
    }

    private function within(string $path, string $root): bool
    {
        $path = $this->pathKey($path);
        $root = $this->pathKey($root);

        return $path === $root || str_starts_with($path, $root.'/');
    }

    private function samePath(string $left, string $right): bool
    {
        return $this->pathKey($left) === $this->pathKey($right);
    }

    private function pathKey(string $path): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower(str_replace('\\', '/', $path));
        }

        return rtrim($path, '/');
    }

    /**
     * @param  non-empty-list<string>  $arguments
     */
    private function git(string $directory, array $arguments, int $timeout = 5, ?string $index = null): string
    {
        $environment = [];
        foreach (array_keys(getenv()) as $name) {
            if (str_starts_with(strtoupper($name), 'GIT_')) {
                $environment[$name] = false;
            }
        }

        $environment = array_replace($environment, [
            'GIT_ALLOW_PROTOCOL' => $arguments[0] === 'clone' ? 'file' : '',
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_NO_LAZY_FETCH' => '1',
            'GIT_NO_REPLACE_OBJECTS' => '1',
            'GIT_OPTIONAL_LOCKS' => '0',
            'GIT_TERMINAL_PROMPT' => '0',
        ]);
        if ($index !== null) {
            $environment['GIT_INDEX_FILE'] = $index;
        }

        $command = ['git', '-c', 'core.hooksPath=/dev/null', '-c', 'protocol.allow=never', '-c', 'core.fsmonitor=false', '-c', 'core.sparseCheckout=false', ...$arguments];
        $process = new Process($command, $directory, $environment, timeout: $timeout);
        $bytes = 0;

        try {
            $exit = $process->run(function (string $type, string $chunk) use (&$bytes): void {
                $bytes += strlen($chunk);
                if ($bytes > self::OUTPUT_LIMIT) {
                    throw new DomainException('Git output exceeded the broker limit.');
                }
            });

            if ($exit !== 0) {
                throw new DomainException("Git rejected the {$arguments[0]} operation.");
            }

            return $process->getOutput();
        } catch (RuntimeException $exception) {
            throw new DomainException('Git could not complete the broker operation.', previous: $exception);
        } finally {
            $process->stop(0);
        }
    }
}

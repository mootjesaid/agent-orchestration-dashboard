<?php

namespace Tests\Feature\Actions\Repositories;

use App\Actions\Repositories\GitBroker;
use DomainException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GitBrokerTest extends TestCase
{
    private ?string $fixture = null;

    protected function tearDown(): void
    {
        if ($this->fixture !== null && realpath($this->fixture) === $this->fixture) {
            (new Filesystem)->deleteDirectory($this->fixture);
        }

        parent::tearDown();
    }

    public function test_artisan_prepares_a_private_clone_and_inspects_changes_without_touching_the_source(): void
    {
        [$repository, $workspaceRoot, $base] = $this->fixture();
        file_put_contents($repository.'/tracked.txt', "source dirt\n");
        file_put_contents($repository.'/source-only.txt', 'source untracked');
        $sourceConfig = file_get_contents($repository.'/.git/config');

        $this->artisan('git-broker:prepare', [
            'repository' => $repository, 'workspaceRoot' => $workspaceRoot, 'base' => $base, 'branch' => 'feature/sample',
        ])->assertSuccessful();

        $workspaces = glob($workspaceRoot.'/task-*', GLOB_ONLYDIR);
        $this->assertIsArray($workspaces);
        $this->assertCount(1, $workspaces);
        $workspace = $workspaces[0];
        $this->assertSame("original\n", str_replace("\r\n", "\n", (string) file_get_contents($workspace.'/tracked.txt')));
        $this->assertFileDoesNotExist($workspace.'/source-only.txt');
        $this->assertDirectoryExists($workspace.'/.git');
        $this->assertFileDoesNotExist($workspace.'/.git/objects/info/alternates');
        $this->assertSame('', $this->git($workspace, ['remote']));
        $this->assertSame('feature/sample', $this->git($workspace, ['branch', '--show-current']));

        $this->git($workspace, ['update-index', '--assume-unchanged', 'tracked.txt']);
        file_put_contents($workspace.'/tracked.txt', "task change\n");
        file_put_contents($workspace.'/new space.txt', 'untracked');

        $snapshot = (new GitBroker)->inspect($workspaceRoot, $workspace, $base, 'feature/sample');
        $this->assertStringContainsString('+task change', $snapshot['diff']);
        $this->assertSame(['new space.txt'], $snapshot['untracked']);
        $this->artisan('git-broker:inspect', [
            'workspaceRoot' => $workspaceRoot, 'workspace' => $workspace, 'base' => $base, 'branch' => 'feature/sample',
        ])->expectsOutput(json_encode($snapshot, JSON_THROW_ON_ERROR))->assertSuccessful();

        file_put_contents($workspace.'/tracked.txt', "legacy \xE9\n");
        $legacy = (new GitBroker)->inspect($workspaceRoot, $workspace, $base, 'feature/sample');
        $this->assertStringContainsString("legacy \xE9", $legacy['diff']);
        $this->artisan('git-broker:inspect', [
            'workspaceRoot' => $workspaceRoot, 'workspace' => $workspace, 'base' => $base, 'branch' => 'feature/sample',
        ])->expectsOutput(json_encode([
            'encoding' => 'base64',
            'diff' => base64_encode($legacy['diff']),
            'untracked' => [base64_encode('new space.txt')],
        ], JSON_THROW_ON_ERROR))->assertSuccessful();

        $this->assertSame("source dirt\n", file_get_contents($repository.'/tracked.txt'));
        $this->assertSame($sourceConfig, file_get_contents($repository.'/.git/config'));
        $this->assertSame('main', $this->git($repository, ['branch', '--show-current']));
        $this->assertSame($base, $this->git($repository, ['rev-parse', 'HEAD']));
    }

    public function test_prepare_rejects_a_protected_branch_before_creating_a_clone(): void
    {
        [$repository, $workspaceRoot, $base] = $this->fixture();

        try {
            (new GitBroker)->prepare($repository, $workspaceRoot, $base, 'main');
            $this->fail('Expected the primary branch to be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('feature branch', $exception->getMessage());
        }

        $this->assertSame([], glob($workspaceRoot.'/task-*'));
    }

    public function test_prepare_rejects_a_workspace_root_inside_the_repository(): void
    {
        [$repository, , $base] = $this->fixture();
        $nested = $repository.'/nested';
        mkdir($nested);

        try {
            (new GitBroker)->prepare($repository, $nested, $base, 'feature/sample');
            $this->fail('Expected overlapping directories to be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('dedicated workspace root', $exception->getMessage());
        }

        $this->assertSame([], glob($nested.'/task-*'));
    }

    public function test_inspect_rejects_an_unexpected_branch_and_keeps_the_clone(): void
    {
        [$repository, $workspaceRoot, $base] = $this->fixture();
        $broker = new GitBroker;
        $workspace = $broker->prepare($repository, $workspaceRoot, $base, 'feature/sample');
        $this->git($workspace, ['branch', '-m', 'feature/renamed']);
        file_put_contents($workspace.'/preserved.txt', 'dirty');

        try {
            $broker->inspect($workspaceRoot, $workspace, $base, 'feature/sample');
            $this->fail('Expected the branch identity change to be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('expected branch and base', $exception->getMessage());
        }

        $this->assertSame('dirty', file_get_contents($workspace.'/preserved.txt'));
    }

    public function test_prepare_rejects_a_base_without_a_local_branch_before_reserving_a_clone(): void
    {
        [$repository, $workspaceRoot, $base] = $this->fixture();
        $this->git($repository, ['switch', '--detach', $base]);
        $this->git($repository, ['-c', 'user.name=Broker Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '--allow-empty', '-m', 'Detached base']);
        $detached = $this->git($repository, ['rev-parse', 'HEAD']);
        $this->git($repository, ['switch', 'main']);

        try {
            (new GitBroker)->prepare($repository, $workspaceRoot, $detached, 'feature/sample');
            $this->fail('Expected an unreferenced base to be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('reachable from a local branch', $exception->getMessage());
        }

        $this->assertSame([], glob($workspaceRoot.'/task-*'));
    }

    /**
     * @return array{string, string, string}
     */
    private function fixture(): array
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'git-broker-test-'.Str::uuid();
        mkdir($root, 0700);
        $this->fixture = realpath($root) ?: throw new DomainException('Unable to create fixture.');
        $repository = $this->fixture.'/source space';
        $workspaces = $this->fixture.'/workspace space';
        mkdir($repository);
        mkdir($workspaces);
        $this->git($repository, ['init', '--initial-branch=main']);
        file_put_contents($repository.'/tracked.txt', "original\n");
        $this->git($repository, ['add', '--', 'tracked.txt']);
        $this->git($repository, ['-c', 'user.name=Broker Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-m', 'Fixture base']);

        return [$repository, $workspaces, $this->git($repository, ['rev-parse', 'HEAD'])];
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(string $directory, array $arguments): string
    {
        $process = new Process(['git', '-c', 'core.hooksPath=/dev/null', '-c', 'commit.gpgsign=false', ...$arguments], $directory, timeout: 10);

        return trim($process->mustRun()->getOutput());
    }
}

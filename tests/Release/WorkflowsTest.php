<?php

declare(strict_types=1);

use Akira\Efatura\Tests\Support\Workflows;

it('pins every third party action to a full commit SHA', function (string $workflow): void {
    $actions = array_filter(Workflows::actions($workflow), static fn (string $action): bool => ! str_starts_with($action, './'));

    expect($actions)->not->toBeEmpty()
        ->each->toMatch('/^[\w.-]+\/[\w.\/-]+@[a-f\d]{40}$/')
        ->and(Workflows::source($workflow))->toMatch('/uses: [^\s@]+@[a-f\d]{40} # v\d/');
})->with(fn (): array => Workflows::names());

it('declares the permissions of every workflow', function (string $workflow): void {
    expect(Workflows::read($workflow))->toHaveKey('permissions');
})->with(fn (): array => Workflows::names());

it('runs the test suite on main with read only access', function (): void {
    $tests = Workflows::read('tests');

    expect($tests['permissions'])->toBe(['contents' => 'read'])
        ->and($tests['on']['push']['branches'])->toBe(['main'])
        ->and($tests['on']['pull_request']['branches'])->toBe(['main'])
        ->and($tests['on'])->toHaveKey('workflow_call')
        ->and($tests['jobs']['test']['strategy']['matrix']['stability'])->toBe(['prefer-lowest', 'prefer-stable'])
        ->and(array_column($tests['jobs']['test']['steps'], 'run'))->toContain('composer test');
});

it('publishes a release only after the tag check and both test variants pass', function (): void {
    $jobs = Workflows::read('release')['jobs'];

    expect($jobs['gates']['uses'])->toBe('./.github/workflows/tests.yml')
        ->and($jobs['gates']['needs'])->toBe('verify')
        ->and($jobs['release']['needs'])->toBe(['verify', 'gates'])
        ->and($jobs['release'])->not->toHaveKey('if');
});

it('commits the changelog before announcing the release, and never fails the release on the announcement', function (): void {
    $steps = array_column(Workflows::read('release')['jobs']['release']['steps'], null, 'name');
    $names = array_keys($steps);

    expect(array_search('Commit CHANGELOG', $names, true))->toBeLessThan(array_search('Send GitHub Release to Discord', $names, true))
        ->and($steps['Send GitHub Release to Discord']['continue-on-error'] ?? null)->toBeTrue()
        ->and(collect($steps)->except('Send GitHub Release to Discord')->pluck('continue-on-error')->filter()->all())->toBe([]);
});

it('grants each release job only the access it needs', function (): void {
    $release = Workflows::read('release');

    expect($release['permissions'])->toBe([])
        ->and($release['jobs']['verify']['permissions'])->toBe(['contents' => 'read'])
        ->and($release['jobs']['gates']['permissions'])->toBe(['contents' => 'read'])
        ->and($release['jobs']['release']['permissions'])->toBe(['contents' => 'write']);
});

it('refuses a tag outside main and never replaces a published release', function (): void {
    $source = Workflows::source('release');

    expect($source)->toContain('git merge-base --is-ancestor "${GITHUB_SHA}" origin/main')
        ->toContain('gh release view "${TAG_NAME}"')
        ->toContain('gh release create "${TAG_NAME}"')
        ->toContain('git-cliff --config cliff.toml')
        ->toContain('SethCohen/github-releases-to-discord@')
        ->not->toContain('gh release delete')
        ->not->toContain('--clobber');
});

it('lets dependabot merge only through the pull request scopes it needs', function (): void {
    $workflow = Workflows::read('dependabot-auto-merge');

    expect($workflow['jobs']['dependabot']['if'])->toBe('${{ github.event.pull_request.user.login == \'dependabot[bot]\' }}')
        ->and($workflow['permissions'])->toBe([
            'pull-requests' => 'write',
            'contents'      => 'write',
        ]);
});

<?php

use Symfony\Component\Process\Process;

it('reports tenant isolation violations', function () {
    $process = new Process([
        PHP_BINARY,
        base_path('vendor/bin/phpstan'),
        'analyse',
        base_path('tests/Fixtures/StaticAnalysis/UnsafeTenantAccess.php'),
        '--configuration='.base_path('phpstan.neon'),
        '--no-progress',
        '--error-format=raw',
    ]);
    $process->setTimeout(60);
    $process->run();

    $output = $process->getOutput().$process->getErrorOutput();

    expect($process->getExitCode())->not->toBe(0)
        ->and($output)->toContain('must use HasTenantScope')
        ->and($output)->toContain("DB::table('users')")
        ->and($output)->toContain('withoutGlobalScopes() is forbidden');
});

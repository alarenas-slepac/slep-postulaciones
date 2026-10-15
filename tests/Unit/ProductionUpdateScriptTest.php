<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProductionUpdateScriptTest extends TestCase
{
    private string $directory;
    private string $bash;

    protected function setUp(): void
    {
        parent::setUp();
        $candidates = PHP_OS_FAMILY === 'Windows'
            ? ['C:/Program Files/Git/bin/bash.exe', 'C:/laragon/bin/git/bin/bash.exe']
            : ['/bin/bash', '/usr/bin/bash'];
        $this->bash = collect($candidates)->first(fn ($path) => is_file($path)) ?? '';
        if ($this->bash === '') {
            $this->markTestSkipped('Se requiere Bash para probar el script de actualización en un entorno temporal.');
        }

        $this->directory = sys_get_temp_dir().'/sga-deploy-script-tests-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/repo con espacios/.git', 0777, true);
        $source = file_get_contents(dirname(__DIR__, 2).'/scripts/production/actualizar_y_desplegar_produccion.sh');
        $source = str_replace("\r\n", "\n", $source);
        $replacements = 0;
        // Sólo se ejecuta una copia con rutas temporales; jamás el script productivo.
        $source = str_replace([
            'REPO="/home/slepac/repositories/slep-postulaciones"',
            'DEPLOY_SCRIPT="/home/slepac/bin/deploy_slep_postulaciones.sh"',
        ], [
            'REPO="'.$this->posix($this->directory).'/repo con espacios"',
            'DEPLOY_SCRIPT="'.$this->posix($this->directory).'/deploy-fake.sh"',
        ], $source, $replacements);
        $this->assertSame(2, $replacements);
        file_put_contents($this->directory.'/update.sh', $source);
        file_put_contents($this->directory.'/deploy-fake.sh', "#!/bin/bash\nprintf 'DEPLOY\\n' >> \"\$TEST_GIT_LOG\"\n");
        chmod($this->directory.'/deploy-fake.sh', 0755);
        file_put_contents($this->directory.'/git-fake.sh', <<<'BASH'
git() {
    printf '%s\n' "$*" >> "$TEST_GIT_LOG"
    if [[ "$1" != '-c' || "$2" != 'core.preloadIndex=false' || "$3" != '-c' || "$4" != 'index.threads=1' || "$5" != '-c' || "$6" != 'pack.threads=1' ]]; then
        echo 'ERROR: Git debe ejecutarse sin procesamiento paralelo.' >&2
        return 97
    fi
    shift 6
    case "$1" in
        branch) printf '%s\n' "${TEST_BRANCH:-main}" ;;
        diff)
            local status="${TEST_WORKTREE_STATUS:-0}"
            if [[ "$*" == *'--cached'* ]]; then status="${TEST_INDEX_STATUS:-0}"; fi
            if [[ "$status" -gt 1 ]]; then
                echo 'fatal: unable to create threaded lstat: Resource temporarily unavailable' >&2
            fi
            return "$status"
            ;;
        fetch) return "${TEST_FETCH_STATUS:-0}" ;;
        pull) return "${TEST_PULL_STATUS:-0}" ;;
        *) return 98 ;;
    esac
}
BASH);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            // Limpieza limitada al directorio aleatorio creado por esta prueba.
            $prefix = str_replace('\\', '/', sys_get_temp_dir()).'/sga-deploy-script-tests-';
            if (! str_starts_with(str_replace('\\', '/', $this->directory), $prefix)) {
                throw new \RuntimeException('Directorio temporal inesperado.');
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function test_repositorio_limpio_actualiza_y_despliega_con_git_sin_paralelismo(): void
    {
        $process = $this->execute();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $calls = file($this->directory.'/git.log', FILE_IGNORE_NEW_LINES);
        $prefix = '-c core.preloadIndex=false -c index.threads=1 -c pack.threads=1 ';
        $this->assertSame([
            $prefix.'branch --show-current',
            $prefix.'diff --quiet --no-ext-diff --no-textconv',
            $prefix.'diff --quiet --no-ext-diff --no-textconv --cached',
            $prefix.'fetch origin main',
            $prefix.'pull --ff-only origin main',
            'DEPLOY',
        ], $calls);
    }

    public static function diferenciasYErrores(): array
    {
        return [
            'cambios en archivos' => [1, 0, 1, true],
            'cambios en staging' => [0, 1, 1, true],
            'error lstat en archivos' => [128, 0, 128, false],
            'error lstat en staging' => [0, 128, 128, false],
            'otro error de git' => [42, 0, 42, false],
        ];
    }

    #[DataProvider('diferenciasYErrores')]
    public function test_distingue_cambios_reales_de_errores_y_detiene_el_despliegue(int $worktree, int $index, int $exit, bool $dirty): void
    {
        $process = $this->execute(['TEST_WORKTREE_STATUS' => (string) $worktree, 'TEST_INDEX_STATUS' => (string) $index]);
        $this->assertSame($exit, $process->getExitCode());
        $output = $process->getErrorOutput();
        if ($dirty) {
            $this->assertStringContainsString('existen modificaciones locales versionadas', $output);
            $this->assertStringNotContainsString('Git no pudo comprobar', $output);
        } else {
            $this->assertStringContainsString('Git no pudo comprobar', $output);
            $this->assertStringContainsString('código '.$exit, $output);
            $this->assertStringNotContainsString('existen modificaciones locales versionadas', $output);
        }
        $calls = file_get_contents($this->directory.'/git.log');
        $this->assertStringNotContainsString('fetch origin', $calls);
        $this->assertStringNotContainsString('pull --ff-only', $calls);
        $this->assertStringNotContainsString('DEPLOY', $calls);
    }

    public function test_no_actualiza_ni_despliega_una_rama_distinta_de_main(): void
    {
        $process = $this->execute(['TEST_BRANCH' => 'otra-rama']);
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString("rama 'otra-rama'", $process->getErrorOutput());
        $calls = file_get_contents($this->directory.'/git.log');
        $this->assertStringNotContainsString('diff --quiet', $calls);
        $this->assertStringNotContainsString('DEPLOY', $calls);
    }

    public static function fallosActualizacion(): array
    {
        return [['TEST_FETCH_STATUS', 'fetch origin main'], ['TEST_PULL_STATUS', 'pull --ff-only origin main']];
    }

    #[DataProvider('fallosActualizacion')]
    public function test_no_despliega_si_falla_la_actualizacion(string $setting, string $command): void
    {
        $process = $this->execute([$setting => '128']);
        $this->assertSame(128, $process->getExitCode());
        $calls = file_get_contents($this->directory.'/git.log');
        $this->assertStringContainsString($command, $calls);
        $this->assertStringNotContainsString('DEPLOY', $calls);
        $this->assertStringNotContainsString('existen modificaciones locales versionadas', $process->getErrorOutput());
    }

    private function execute(array $settings = []): Process
    {
        $process = new Process([$this->bash, $this->posix($this->directory).'/update.sh'], dirname(__DIR__, 2), array_merge([
            'BASH_ENV' => $this->posix($this->directory).'/git-fake.sh',
            'TEST_GIT_LOG' => $this->posix($this->directory).'/git.log',
            'TEST_BRANCH' => 'main', 'TEST_WORKTREE_STATUS' => '0', 'TEST_INDEX_STATUS' => '0',
            'TEST_FETCH_STATUS' => '0', 'TEST_PULL_STATUS' => '0',
        ], $settings), timeout: 15);
        $process->run();

        return $process;
    }

    private function posix(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return preg_replace_callback('/^([A-Za-z]):/', fn ($match) => '/'.strtolower($match[1]), $path);
    }
}

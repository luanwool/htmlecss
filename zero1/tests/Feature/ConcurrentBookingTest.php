<?php

namespace Tests\Feature;

use App\Exceptions\BookingException;
use App\Models\Appointment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

/**
 * Aceitação 7 — requisições CONCORRENTES reais: processos separados disputam o mesmo horário.
 * Exige MySQL/MariaDB (SELECT … FOR UPDATE) e a extensão pcntl. Rode com:
 *   DB_CONNECTION=mysql DB_DATABASE=zero1_test ... php artisan test --group=concurrency
 *
 * @group concurrency
 */
class ConcurrentBookingTest extends TestCase
{
    use BuildsScenario;

    protected bool $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requer MySQL/MariaDB e pcntl.');
        }
        Artisan::call('migrate:fresh', ['--seed' => true]);
        Notification::fake();
        $this->freezeAt();
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Artisan::call('migrate:fresh', ['--seed' => true]); // devolve o banco limpo para os demais testes
        }
        parent::tearDown();
    }

    /** @return array<int, string> resultado de cada processo: "ok:<barber_id>" ou "fail:<motivo>" */
    private function race(int $workers, callable $attempt): array
    {
        $dir = sys_get_temp_dir().'/zero1-race-'.uniqid();
        mkdir($dir);
        $startAt = microtime(true) + 0.6;
        DB::disconnect();
        $pids = [];
        for ($i = 0; $i < $workers; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                DB::purge();
                DB::reconnect();
                while (microtime(true) < $startAt) {
                    usleep(500);
                }
                try {
                    $a = $attempt($i);
                    file_put_contents("{$dir}/{$i}", 'ok:'.$a->barber_id);
                } catch (BookingException $e) {
                    file_put_contents("{$dir}/{$i}", 'fail:'.$e->reason);
                } catch (\Throwable $e) {
                    file_put_contents("{$dir}/{$i}", 'error:'.$e->getMessage());
                }
                posix_kill(getmypid(), SIGKILL); // encerra sem rodar o shutdown do PHPUnit
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        DB::reconnect();
        $results = array_map(fn ($f) => file_get_contents($f), glob("{$dir}/*"));
        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);

        return $results;
    }

    public function test_only_one_of_many_concurrent_requests_gets_the_same_barber_slot(): void
    {
        $corte = $this->service('Corte', 30);
        $x = $this->barber('X', [$corte]);
        $customers = collect(range(1, 8))->map(fn () => $this->customer());

        $results = $this->race(8, fn ($i) => $this->book($customers[$i], $corte, $x, '2026-03-05 17:00'));

        $ok = array_filter($results, fn ($r) => str_starts_with($r, 'ok:'));
        $this->assertCount(1, $ok, 'Resultados: '.implode(', ', $results));
        $this->assertSame([], array_filter($results, fn ($r) => str_starts_with($r, 'error:')), implode(', ', $results));
        $this->assertSame(1, Appointment::where('barber_id', $x->id)->count());
    }

    public function test_overlapping_different_durations_race(): void
    {
        $corte = $this->service('Corte', 30);
        $combo = $this->service('Combo', 60);
        $x = $this->barber('X', [$corte, $combo]);
        $customers = collect(range(1, 6))->map(fn () => $this->customer());
        $times = ['17:00', '17:15', '17:30', '17:00', '17:45', '16:45'];

        $this->race(6, fn ($i) => $this->book($customers[$i], $i % 2 ? $combo : $corte, $x, '2026-03-05 '.$times[$i]));

        $appts = Appointment::where('barber_id', $x->id)->orderBy('starts_at')->get();
        for ($i = 1; $i < $appts->count(); $i++) {
            $this->assertTrue($appts[$i]->starts_at->gte($appts[$i - 1]->occupied_until), 'Nenhuma sobreposição persistida');
        }
        $this->assertGreaterThanOrEqual(1, $appts->count());
    }

    public function test_any_available_race_assigns_each_free_barber_at_most_once(): void
    {
        $corte = $this->service('Corte', 30);
        $this->barber('X', [$corte]);
        $this->barber('Y', [$corte]);
        $customers = collect(range(1, 6))->map(fn () => $this->customer());

        $results = $this->race(6, fn ($i) => $this->book($customers[$i], $corte, null, '2026-03-05 17:00'));
        $ok = array_values(array_filter($results, fn ($r) => str_starts_with($r, 'ok:')));

        $this->assertCount(2, $ok, implode(', ', $results));
        $this->assertCount(2, array_unique($ok), 'Barbeiros distintos');
    }
}

<?php

namespace Tests;

use DateTime;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PDO;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->registerSqliteCustomFunctions();
    }

    /**
     * Registrar funciones de compatibilidad MySQL en conexiones SQLite en memoria para tests.
     */
    protected function registerSqliteCustomFunctions(): void
    {
        $bind = function ($connection) {
            if (!$connection instanceof Connection || $connection->getDriverName() !== 'sqlite') {
                return;
            }

            try {
                /** @var PDO $pdo */
                $pdo = $connection->getPdo();
            } catch (Throwable) {
                return;
            }

            $functions = [
                'floor' => fn($val) => is_null($val) ? null : (int) floor((float) $val),
                'ceil' => fn($val) => is_null($val) ? null : (int) ceil((float) $val),
                'ceiling' => fn($val) => is_null($val) ? null : (int) ceil((float) $val),
                'concat' => fn(...$args) => implode('', array_filter($args, fn($a) => !is_null($a))),
                'least' => fn(...$args) => empty($args) ? null : min(array_map('floatval', $args)),
                'greatest' => fn(...$args) => empty($args) ? null : max(array_map('floatval', $args)),
                'now' => fn() => date('Y-m-d H:i:s'),
                'curdate' => fn() => date('Y-m-d'),
                'if' => fn($cond, $t, $f) => $cond ? $t : $f,
                'datediff' => function ($d1, $d2) {
                    if (!$d1 || !$d2) {
                        return null;
                    }
                    $date1 = new DateTime((string) $d1);
                    $date2 = new DateTime((string) $d2);
                    $diff = $date2->diff($date1);
                    return $diff->invert ? -$diff->days : $diff->days;
                },
                'timestampdiff' => function ($unit, $d1, $d2) {
                    if (!$d1 || !$d2) {
                        return 0;
                    }
                    $date1 = new DateTime((string) $d1);
                    $date2 = new DateTime((string) $d2);
                    $diff = $date1->diff($date2);
                    $unit = strtoupper(trim((string) $unit));
                    return match ($unit) {
                        'YEAR' => $diff->invert ? -$diff->y : $diff->y,
                        'MONTH' => ($diff->invert ? -1 : 1) * ($diff->y * 12 + $diff->m),
                        'DAY' => $diff->invert ? -$diff->days : $diff->days,
                        'HOUR' => ($diff->invert ? -1 : 1) * ($diff->days * 24 + $diff->h),
                        'MINUTE' => ($diff->invert ? -1 : 1) * (($diff->days * 24 + $diff->h) * 60 + $diff->i),
                        'SECOND' => ($diff->invert ? -1 : 1) * ((($diff->days * 24 + $diff->h) * 60 + $diff->i) * 60 + $diff->s),
                        default => $diff->invert ? -$diff->days : $diff->days,
                    };
                },
                'date_format' => function ($date, $format) {
                    if (!$date) {
                        return null;
                    }
                    $phpFormat = str_replace(
                        ['%Y', '%y', '%m', '%c', '%d', '%e', '%H', '%h', '%i', '%s'],
                        ['Y',  'y',  'm',  'n',  'd',  'j',  'H',  'h',  'i',  's'],
                        (string) $format
                    );
                    return (new DateTime((string) $date))->format($phpFormat);
                },
                'year' => function ($date) {
                    return $date ? (int) (new DateTime((string) $date))->format('Y') : null;
                },
                'month' => function ($date) {
                    return $date ? (int) (new DateTime((string) $date))->format('m') : null;
                },
                'day' => function ($date) {
                    return $date ? (int) (new DateTime((string) $date))->format('d') : null;
                },
                'dayofweek' => function ($date) {
                    return $date ? (int) (new DateTime((string) $date))->format('w') + 1 : null;
                },
                'hour' => function ($date) {
                    return $date ? (int) (new DateTime((string) $date))->format('H') : null;
                },
                'minute' => function ($date) {
                    return $date ? (int) (new DateTime((string) $date))->format('i') : null;
                },
                'second' => function ($date) {
                    return $date ? (int) (new DateTime((string) $date))->format('s') : null;
                },
            ];

            foreach ($functions as $name => $fn) {
                $pdo->sqliteCreateFunction($name, $fn);
            }

            $connection->beforeExecuting(function (&$query) {
                if (!is_string($query)) {
                    return;
                }

                if (stripos($query, 'TIMESTAMPDIFF') !== false) {
                    $query = preg_replace(
                        '/\bTIMESTAMPDIFF\s*\(\s*(YEAR|MONTH|DAY|HOUR|MINUTE|SECOND)\s*,/i',
                        "TIMESTAMPDIFF('$1',",
                        $query
                    );
                }

                if (stripos($query, 'DATE_SUB') !== false) {
                    $query = preg_replace(
                        '/\bDATE_SUB\s*\(\s*([^,]+?)\s*,\s*INTERVAL\s+([0-9]+)\s+([a-zA-Z]+)\s*\)/i',
                        "datetime($1, '-$2 $3')",
                        $query
                    );
                }

                if (stripos($query, 'DATE_ADD') !== false) {
                    $query = preg_replace(
                        '/\bDATE_ADD\s*\(\s*([^,]+?)\s*,\s*INTERVAL\s+([0-9]+)\s+([a-zA-Z]+)\s*\)/i',
                        "datetime($1, '+$2 $3')",
                        $query
                    );
                }
            });
        };

        Event::listen(ConnectionEstablished::class, function ($event) use ($bind) {
            $bind($event->connection);
        });

        try {
            $bind(DB::connection());
        } catch (Throwable) {
            // Conexión no inicializada todavía
        }
    }
}


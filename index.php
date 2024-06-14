<?php

/**
 * This class can be edited to configure the health check application.
 *
 * Note that this is the only file you should edit yourself!
 */
class Config
{
    public static function features(): array
    {
        // Comment out the features you don't want to use here.

        return [
            'server_time',
            'server_name',
            'server_software',
            'server_os_family',
            'server_os_version',
            'ping_time_ms',
            'execution_time_ms',
            'uptime',
            'load_average',
            'cpu',
        ];
    }
}

/**
 * Main helper class for the application.
 */
class SimpleServerHealth
{
    public static function data(): array
    {
        return [
            'server_time' => date('Y-m-d H:i:s T (e)'),
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'server_os_family' => php_uname('s'),
            'server_os_version' => php_uname('r'),
            'ping_time_ms' => TimeBuffer::pingPlaceholder(),
            'execution_time_ms' => TimeBuffer::timePlaceholder(),
            'uptime' => self::uptime(),
            'load_average' => self::loadAverage(),
            'cpu' => self::getCPUInfo(),
        ];
    }

    /** @return array|string */
    protected static function uptime()
    {
        $uptime = shell_exec('uptime -s');

        if (! $uptime) {
            return 'Unknown';
        }

        $uptime = new DateTimeImmutable(trim($uptime));
        $now = new DateTimeImmutable();

        $diff = $now->diff($uptime);

        return [
            'days' => $diff->d,
            'hours' => $diff->h,
            'minutes' => $diff->i,
            'human' => $diff->format('%a days, %h hours, %i minutes'),
        ];
    }

    /** @return array|string */
    protected static function loadAverage()
    {
        $loadAverage = function_exists('sys_getloadavg') ? sys_getloadavg() : null;

        if (! $loadAverage) {
            return 'Unknown';
        }

        return [
            '1min' => $loadAverage[0],
            '5min' => $loadAverage[1],
            '15min' => $loadAverage[2],
        ];
    }

    /**
     * This method was reworked from 'PHP Server Status Dashboard'. The original information is seen below.
     *
     * @return array|string
     *
     * @link https://github.com/eworksmedia/php-server-status-dashboard/blob/master/server/classes/Server.class.php
     *
     * @author http://www.e-worksmedia.com
     * @license BSD 3-Clause
     */
    public static function getCPUInfo()
    {
        exec('cat /proc/cpuinfo', $raw);

        if (empty($raw)) {
            return 'Unknown';
        }

        $cpus = [];
        $iteration = 0;
        $coreSpeeds = [];
        $brands = [];

        for ($i = 0; $i < count($raw); $i++) {
            if (empty($raw[$i])) {
                $iteration++;

                continue;
            }
            $parts = explode(':', $raw[$i]);
            $cpus[$iteration][str_replace(' ', '_', trim($parts[0]))] = trim($parts[1]);

            if (strpos($raw[$i], 'cpu MHz') !== false) {
                $coreSpeeds[] = trim($parts[1]);
            }

            if (strpos($raw[$i], 'model name') !== false) {
                $brands[] = trim($parts[1]);
            }
        }

        for ($i = 0; $i < count($cpus); $i++) {
            ksort($cpus[$i]);
        }

        exec('cat /proc/loadavg', $loadRaw);
        $loadParts = explode(' ', $loadRaw[0]);
        $load = $loadParts[0] * 100 / count($cpus);

        $coreSpeedAverage = count($coreSpeeds) > 0 ? array_sum($coreSpeeds) / count($coreSpeeds) : 0;

        if (count($brands) > 0) {
            if (count(array_unique($brands)) === 1) {
                $brand = $brands[0];
            } else {
                $brand = 'Multiple: '.implode(', ', array_unique($brands));
            }
        } else {
            $brand = 'Unknown';
        }

        $cpuData = [];
        foreach ($cpus as $cpu) {
            $cpuData[] = [
                'core_id' => $cpu['processor'],
                'cpu_mhz' => $cpu['cpu_MHz'],
                'model_name' => $cpu['model_name'],
            ];
        }

        return [
            'brand' => $brand,
            'used' => number_format($load, 2),
            'idle' => number_format(100 - $load, 2),
            'core_speed_avg_mhz' => number_format($coreSpeedAverage, 2),
            'cores' => count($cpus),
            'cpus' => $cpuData,
        ];
    }
}

/**
 * The main application entry point, responsible for delivering the response.
 */
class Main extends App
{
    public const APP_VERSION = '0.1.0';

    public function handle(): Response
    {
        return new Response(200, 'OK', $this->getResponseData());
    }

    protected function getResponseData(): array
    {
        TimeBuffer::ping();

        $data = SimpleServerHealth::data();

        if (isset($data['ping_time_ms'])) {
            $data['ping_time_ms'] = TimeBuffer::getPingTime();
        }

        if (isset($data['execution_time_ms'])) {
            $data['execution_time_ms'] = TimeBuffer::getExecutionTime();
        }

        $features = Config::features();

        if (empty($features)) {
            return $data;
        }

        $filteredData = [];

        foreach ($features as $feature) {
            if (array_key_exists($feature, $data)) {
                $filteredData[$feature] = $data[$feature];
            }
        }

        return $filteredData;
    }
}

/**
 * A simple class to buffer time values to get more accurate results.
 */
class TimeBuffer
{
    protected static float $pingTime;

    public static function ping(): void
    {
        static::$pingTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    }

    public static function pingPlaceholder(): string
    {
        return '%% ping time %%';
    }

    public static function timePlaceholder(): string
    {
        return '%% execution time %%';
    }

    public static function getPingTime(): float
    {
        return round(static::$pingTime * 1000, 8);
    }

    public static function getExecutionTime(): float
    {
        return round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'] ?? 0) * 1000, 8);
    }
}

// Convert PHP warnings to exceptions.
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Boot the application and return the response.
try {
    Piko::boot(new Main());
} catch (Throwable $exception) {
    // If the `APP_DEBUG` environment variable is set, show the error message.
    if (!getenv('APP_DEBUG')) {
        // If client is requesting JSON, return the error message.
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return new Response(500, 'Internal Server Error', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }

        // Otherwise, show the error message in the browser.
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>Internal Server Error</h1>';
        echo '<p>'.$exception->getMessage().'</p>';
        echo '<p>'.$exception->getFile().' on line '.$exception->getLine().'</p>';
        echo '<pre>'.$exception->getTraceAsString().'</pre>';

        return;
    }

    return new Response(500, 'Internal Server Error', [
        'error' => 'Please see the server logs for more information.',
    ]);
}

// Debug utility
function dd($data)
{
    var_dump($data);

    exit(1);
}

// Below is vendor code bundled with the project

// -- Start Pikoserve --

/**
 * @author Caen De Silva <caen@desilva.se>
 *
 * @link https://github.com/caendesilva/pikoserve
 *
 * @version 1.1.0
 *
 * @license MIT
 */
class Piko
{
    public const VERSION = '1.1.0';

    public static function boot(App $main, ?Closure $callback = null)
    {
        header('Content-Type: application/json');

        $main->handle();

        if ($callback) {
            $callback($main);
        }
    }
}

abstract class App
{
    abstract public function handle(): Response;
}

class Response
{
    public function __construct(int $statusCode = 200, string $statusMessage = 'OK', array $data = [])
    {
        header("HTTP/1.1 $statusCode $statusMessage");

        $response = array_merge([
            'statusCode' => $statusCode,
            'statusMessage' => $statusMessage,
        ], $data);

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

class Request
{
    public string $method;

    public string $path;

    public array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;

        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    }

    public function __get(string $key): ?string
    {
        return $this->data[$key] ?? null;
    }

    public static function get(): Request
    {
        return new self($_REQUEST);
    }

    public static function array(): array
    {
        return (array) static::get();
    }
}

// -- End Pikoserve --

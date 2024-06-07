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
            //
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
            'ping_time_ms' => TimeBuffer::ping(),
            'uptime' => self::uptime(),
        ];
    }

    protected static function uptime(): string
    {
        $uptime = shell_exec('uptime');

        return $uptime ? trim($uptime) : 'Unknown';
    }
}

/**
 * A simple class to buffer time values to get more accurate results.
 */
class TimeBuffer
{
    public static function ping(): string
    {
        return '%% ping time %%';
    }

    public static function getPingTime(): float
    {
        return round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'] ?? 0) * 1000, 8);
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
        return new Response(200, 'OK', SimpleServerHealth::data());
    }
}

Piko::boot(new Main());

// Below is vendor code bundled with the project

// -- Start Pikoserve --

/**
 * @package caendesilva/pikoserve
 * @author Caen De Silva <caen@desilva.se>
 * @link https://github.com/caendesilva/pikoserve
 * @version 1.1.0
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
        return (array)static::get();
    }
}

// -- End Pikoserve --

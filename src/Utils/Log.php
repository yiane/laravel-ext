<?php
/**
 * Created by IntelliJ IDEA.
 * User: yang
 * Date: 2018/1/3
 * Time: 16:37
 */

namespace Yr\Utils;

use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;

/**
 * 日志记录，调用方式 Log::order($info), 记录日志内容则会生成order-Y-m-d.log日志文件
 *                  Log::order($info,"warning"), 该记录文件行开头为order.WARNING,支持以下日志级别
 *                  Log::order($info,[],"warning"), 第二个参数记录日志详情信息数组
 * 八种日志级别：emergency, alert, critical, error,warning, notice, info 和 debug
 * 兼容\Illuminate\Support\Facades\Log使用方式，支持自定义文件记录日志
 *
 * @method static void emergency($message, array $context = array())
 * @method static void alert($message, array $context = array())
 * @method static void critical($message, array $context = array())
 * @method static void error($message, array $context = array())
 * @method static void warning($message, array $context = array())
 * @method static void notice($message, array $context = array())
 * @method static void info($message, array $context = array())
 * @method static void debug($message, array $context = array())
 *
 */
class Log
{

    /**
     * Logger instance.
     *
     * @var Array LoggerInterface
     */
    protected static $loggers = [];

    /**
     * @return array
     */
    public static function getLevels()
    {
        return MonologLogger::getLevels();
    }

    /**
     * @param $level_name
     * @return bool
     */
    public static function checkLevel($level_name)
    {
        $levels = MonologLogger::getLevels();
        return isset($levels[strtoupper($level_name)]);
    }

    /**
     * Return the logger instance.
     * @param string $file_name
     * @param string $type
     * @return MonologLogger
     * @throws Exception
     */
    public static function getLogger($file_name = 'laravel', $type = 'daily')
    {
        return static::$loggers[$file_name] ?? static::createLogger($file_name, MonologLogger::DEBUG, $type);
    }


    /**
     * Set logger.
     * @param LoggerInterface $logger
     * @param string $file_name
     */
    public static function setLogger(LoggerInterface $logger, $file_name = 'laravel')
    {
        static::$loggers[$file_name] = $logger;
    }

    /**
     * Tests if logger exists.
     * @param string $file_name
     * @return bool
     */
    public static function hasLogger($file_name = 'laravel')
    {
        return isset(static::$loggers[$file_name]) ? true : false;
    }

    /**
     * Make a log instance.
     * @param null $file_name
     * @param int $level
     * @param string $type
     * @param int $max_files
     * @return MonologLogger
     * @throws Exception
     */
    public static function createLogger($file_name = null, $level = MonologLogger::DEBUG, $type = 'daily', $max_files = 30)
    {
        $file_name = $file_name ?? 'laravel';
        $file = storage_path() . '/logs/' . $file_name . '.log';

        if (empty(static::$loggers[$file_name])) {
            $handler = $type === 'single' ? new StreamHandler($file, $level) : new RotatingFileHandler($file, $max_files, $level);

            $handler->setFormatter(
                new LineFormatter(null, null, false, true)
            );
            $logger = new MonologLogger($file_name);
            $logger->pushHandler($handler);
            static::$loggers[$file_name] = $logger;
        } else {
            $logger = static::$loggers[$file_name];
        }

        return $logger;
    }


    /**
     * Forward call.
     * @param $method
     * @param $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        try {
            if (static::checkLevel($method)) {
                static::adapterArgs($method, $args);
                return forward_static_call_array([static::getLogger(), $method], $args);
            } else {
                $act = "info";//默认以info形式记录
                static::adapter($act, $args);
                return forward_static_call_array([static::getLogger($method), $act], $args);
            }
        } catch (Exception $e) {

        }

    }

    /**
     * Forward call.
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        try {
            if (static::checkLevel($method)) {
                static::adapterArgs($method, $args);

                return call_user_func_array([static::getLogger(), $method], $args);
            } else {
                $act = "info";//默认以info形式记录
                static::adapter($act, $args);

                return call_user_func_array([static::getLogger($method), $act], $args);
            }
        } catch (Exception $e) {

        }
    }

    /**
     * @param $act
     * @param $args
     */
    public static function adapter(&$act, &$args)
    {
        if (count($args) > 1) {
            $end = array_pop($args);
            if (is_array($end)) {
                $args = [
                    $args[0],
                    $end
                ];
            } elseif (static::checkLevel($end)) {
                $act = $end;
            }

        }

        static::adapterArgs($act, $args);
    }

    /**
     * @param $act
     * @param $args
     */
    public static function adapterArgs($act, &$args)
    {
        if (strtoupper($act) == 'ERROR' && (!isset($args[1]) || empty($args[1]))) {
            $arr = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
            foreach ($arr as $key => $row) {
                !isset($row["type"]) and $row["type"] = " ";
                if (isset($row["file"])) {
                    $args[1][] = "#$key " . $row["file"] . "(" . $row["line"] . ")" . $row["type"] . $row["function"];
                } else {
                    $args[1][] = "#$key " . $row["class"] . $row["type"] . $row["function"];
                }
            }
        }

    }
}

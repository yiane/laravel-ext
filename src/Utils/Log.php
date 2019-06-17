<?php
/**
 * Created by IntelliJ IDEA.
 * User: yang
 * Date: 2018/1/3
 * Time: 16:37
 */

namespace Yr\Utils;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as MonologLogger;
class Log
{

    private static $loggers = [];

    /**
     * The Log levels.
     * MonologLogger 支持的日志等级
     * @var array
     */
    protected static $levels = [
        'debug'     => MonologLogger::DEBUG,
        'info'      => MonologLogger::INFO,
        'notice'    => MonologLogger::NOTICE,
        'warning'   => MonologLogger::WARNING,
        'error'     => MonologLogger::ERROR,
        'critical'  => MonologLogger::CRITICAL,
        'alert'     => MonologLogger::ALERT,
        'emergency' => MonologLogger::EMERGENCY,
    ];

    /**
     * 获取一个实例
     * @param $type
     * @param string $name The logging channel
     * @param int $maxFiles The maximal amount of files to keep (0 means unlimited)
     * @return mixed
     */
    public static function getLogger($type, $name = "local", $maxFiles = 0)
    {
        if (empty(self::$loggers[$type])) {
            // @todo: 暂时根据文件存储，后续升级
            $handle = new RotatingFileHandler(
                storage_path().'/logs/'. $type .'.log',
                $maxFiles
            );
            $handle->setFormatter(new LineFormatter());
            self::$loggers[$type] = new MonologLogger($name,[$handle]);
        }

        $log = self::$loggers[$type];
        return $log;
    }

    /**
     *
     * 日志记录，调用方式 \App\Log::order($info), 记录日志内容则会生成order-Y-m-d.log日志文件
     *                  \App\Log::order($info,"warning"), 该记录文件行开头为order.WARNING,支持以下日志级别
     *                  \App\Log::order($info,[],"warning"), 第二个参数记录日志详情信息数组
     * 八种日志级别：emergency, alert, critical, error,warning, notice, info 和 debug
     * 兼容\Illuminate\Support\Facades\Log使用方式，支持自定义文件记录日志
     * @param string $name
     * @param $arguments
     */
    public static function __callStatic(string $name, $arguments){
        if(is_array($arguments) && !empty($arguments)){
            $type = $name;
            $act = "info";//默认以info形式记录
            $context = [];
            if(isset(static::$levels[$name])){//兼容框架调用,记录日志到laravel文件
                $type = "laravel";
                $act = strtolower($name);
                (isset($arguments[1]) && is_array($arguments[1]) ) and $context = $arguments[1];
            }else{
                if(count($arguments) > 1){
                    $end = array_pop($arguments);
                    if(is_array($end)){
                        $context = $end;
                    }else{
                        $end = strtolower($end);
                        if(isset(static::$levels[$end])){
                            $act = strtolower($end);
                        }
                        if(count($arguments) > 1 && is_array($arguments[1])){
                            $context = $arguments[1];
                        }
                    }
                }
            }
            try{
                if(env("APP_LOG_LEVEL") == "debug" && $act == "error" && empty($context)){//测试日志记录堆栈信息
                    $arr = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
                    foreach ($arr as $key => $row){
                        !isset($row["type"]) and $row["type"] = " ";
                        if(isset($row["file"])){
                            $context[] = "#$key " . $row["file"] . "(".$row["line"].")".$row["type"].$row["function"];
                        }else{
                            $context[] = "#$key " . $row["class"] . $row["type"] . $row["function"];
                        }

                    }
                }
                static::getLogger($type)->{$act}($arguments[0],$context);
            }catch (\Exception $e){

            }

        }
    }


}

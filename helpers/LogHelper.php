<?php
/**
 * LogHelper - Advanced Logging Utility
 * 
 * Provides flexible and extensible logging mechanisms for the Blackwall module
 */
class LogHelper {
    /**
     * Logging levels
     */
    const LEVEL_DEBUG   = 100;
    const LEVEL_INFO    = 200;
    const LEVEL_WARNING = 300;
    const LEVEL_ERROR   = 400;
    const LEVEL_CRITICAL = 500;

    /**
     * @var string Path to the log file
     */
    private static $logPath;

    /**
     * @var int Minimum logging level
     */
    private static $logLevel;

    /**
     * Initialize logging configuration
     * 
     * @param string|null $logPath Custom log file path
     * @param int $minLogLevel Minimum log level to record
     */
    public static function configure($logPath = null, $minLogLevel = self::LEVEL_DEBUG) {
        self::$logPath = $logPath ?? dirname(__FILE__) . '/logs/blackwall_module.log';
        self::$logLevel = $minLogLevel;

        // Ensure log directory exists
        $logDir = dirname(self::$logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }

    /**
     * Log a debug message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool
     */
    public static function debug($message, array $context = []) {
        return self::log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool
     */
    public static function info($message, array $context = []) {
        return self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a warning message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool
     */
    public static function warning($message, array $context = []) {
        return self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an error message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool
     */
    public static function error($message, array $context = []) {
        return self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a critical error message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool
     */
    public static function critical($message, array $context = []) {
        return self::log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Core logging method
     * 
     * @param int $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool
     */
    private static function log($level, $message, array $context = []) {
        // Check if this log level should be recorded
        if ($level < self::$logLevel) {
            return false;
        }

        // Prepare log entry
        $logLevels = [
            self::LEVEL_DEBUG   => 'DEBUG',
            self::LEVEL_INFO    => 'INFO',
            self::LEVEL_WARNING => 'WARNING',
            self::LEVEL_ERROR   => 'ERROR',
            self::LEVEL_CRITICAL => 'CRITICAL'
        ];

        $logMessage = sprintf(
            "[%s] [%s] %s %s\n",
            date('Y-m-d H:i:s'),
            $logLevels[$level] ?? 'UNKNOWN',
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        // Attempt to write to log file
        try {
            $result = @file_put_contents(
                self::$logPath, 
                $logMessage, 
                FILE_APPEND | LOCK_EX
            );

            return $result !== false;
        } catch (Exception $e) {
            // Fallback to error_log if file writing fails
            error_log($logMessage);
            return false;
        }
    }

    /**
     * Utility method to log exceptions
     * 
     * @param \Exception $e Exception to log
     * @param string $message Optional custom message
     */
    public static function logException(\Exception $e, $message = null) {
        $context = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];

        self::error(
            $message ?? $e->getMessage(), 
            $context
        );
    }
}

// Initialize with default configuration
LogHelper::configure();

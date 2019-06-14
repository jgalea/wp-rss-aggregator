<?php

namespace RebelCode\Wpra\Core;

use Dhii\I18n\StringTranslatingTrait;
use Exception;
use Throwable;

/**
 * Handles errors.
 *
 * @since [*next-version*]
 */
class ErrorHandler
{
    /*
     * Provides string translating functionality.
     *
     * @since [*next-version*]
     */
    use StringTranslatingTrait;

    /**
     * The callback to invoke.
     *
     * @since [*next-version*]
     * 
     * @var callable
     */
    protected $callback;
    
    /*
     * The previous exception handler.
     *
     * @since [*next-version*]
     */
    protected $previous;

    /**
     * The root directory for which to limit exception handling.
     *
     * @since [*next-version*]
     *
     * @var string
     */
    protected $rootDir;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param string   $rootDir  The root directory for which to limit exception handling.
     * @param callable $callback The callback to invoke when an exception is handled. The callback will receive the
     *                           exception or PHP7 {@see \Throwable} as argument.
     */
    public function __construct($rootDir, $callback)
    {
        $this->rootDir = $rootDir;
        $this->callback = $callback;
        $this->previous = null;
    }

    /**
     * Registers the handler.
     *
     * @since [*next-version*]
     */
    public function register()
    {
        $this->previous = set_exception_handler($this);
    }

    /**
     * De-registers the handler.
     *
     * @since [*next-version*]
     */
    public function deregister()
    {
        set_exception_handler($this->previous);
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function __invoke()
    {
        if ($this->previous) {
            call_user_func_array($this->previous, func_get_args());
        }

        $throwable = func_get_arg(0);

        if (!($throwable instanceof Exception) && !($throwable instanceof Throwable)) {
            return;
        }

        if ($this->errorOriginInRootDir($throwable->getFile())) {
            $this->handleError($throwable);

            return;
        }

        // Detect an exception thrown from within the root directory
        foreach ($throwable->getTrace() as $trace) {
            if ($this->errorOriginInRootDir($trace['file'])) {
                $this->handleError($throwable);
            }
        }
    }

    protected function errorOriginInRootDir($path)
    {
        return stripos($path, $this->rootDir) === 0;
    }

    /**
     * @param Exception|Throwable $throwable
     */
    protected function handleError($throwable)
    {
        if (defined('REST_REQUEST')) {
            wp_send_json_error(['error' => $throwable->getMessage(), 'trace' => $throwable->getTrace()], 500);

            return;
        }
        
        if (is_callable($this->callback)) {
            call_user_func_array($this->callback, [$throwable]);
            
            return;
        }

        return;
    }
}

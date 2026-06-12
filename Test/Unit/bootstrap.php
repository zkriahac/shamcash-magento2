<?php
/**
 * Standalone unit-test bootstrap.
 *
 * Lets the framework-light units (matching rules, response parsing, DTOs,
 * config parsing, retry logic) be tested without a full Magento install: it
 * registers a PSR-4 autoloader for the module and declares minimal stand-ins
 * for the handful of Magento/PSR symbols those classes reference. Inside a real
 * Magento test run these symbols already exist and the stubs are skipped.
 */
declare(strict_types=1);

namespace {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'ShamCash\\Payment\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = dirname(__DIR__, 2) . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

namespace Magento\Framework {
    if (!class_exists(Phrase::class, false)) {
        class Phrase
        {
            /** @param array<int,mixed> $arguments */
            public function __construct(private string $text = '', private array $arguments = [])
            {
            }

            public function render(): string
            {
                $out = $this->text;
                $i = 1;
                foreach ($this->arguments as $argument) {
                    $out = str_replace('%' . $i, (string)$argument, $out);
                    $i++;
                }
                return $out;
            }

            public function __toString(): string
            {
                return $this->render();
            }
        }
    }
}

namespace Magento\Framework\Exception {
    if (!class_exists(LocalizedException::class, false)) {
        class LocalizedException extends \Exception
        {
            public function __construct(\Magento\Framework\Phrase $phrase, ?\Throwable $cause = null, int $code = 0)
            {
                parent::__construct((string)$phrase, $code, $cause);
            }
        }
    }
}

namespace Magento\Framework\App\Config {
    if (!interface_exists(ScopeConfigInterface::class, false)) {
        interface ScopeConfigInterface
        {
            public function getValue($path, $scopeType = 'default', $scopeCode = null);

            public function isSetFlag($path, $scopeType = 'default', $scopeCode = null);
        }
    }
}

namespace Magento\Framework\Encryption {
    if (!interface_exists(EncryptorInterface::class, false)) {
        interface EncryptorInterface
        {
            public function encrypt($data);

            public function decrypt($data);
        }
    }
}

namespace Magento\Store\Model {
    if (!interface_exists(ScopeInterface::class, false)) {
        interface ScopeInterface
        {
            public const SCOPE_STORE = 'store';
            public const SCOPE_STORES = 'stores';
        }
    }
}

namespace Psr\Log {
    if (!interface_exists(LoggerInterface::class, false)) {
        interface LoggerInterface
        {
            public function emergency($message, array $context = []): void;

            public function alert($message, array $context = []): void;

            public function critical($message, array $context = []): void;

            public function error($message, array $context = []): void;

            public function warning($message, array $context = []): void;

            public function notice($message, array $context = []): void;

            public function info($message, array $context = []): void;

            public function debug($message, array $context = []): void;

            public function log($level, $message, array $context = []): void;
        }
    }
}

namespace {
    if (!function_exists('__')) {
        /**
         * Minimal stand-in for Magento's translation helper.
         */
        function __($text, ...$arguments): \Magento\Framework\Phrase
        {
            if (count($arguments) === 1 && is_array($arguments[0])) {
                $arguments = $arguments[0];
            }
            return new \Magento\Framework\Phrase((string)$text, $arguments);
        }
    }
}

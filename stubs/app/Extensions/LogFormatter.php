<?php

namespace App\Extensions;

use Monolog\Formatter\LineFormatter;

class LogFormatter
{
    public function __invoke($monolog): void
    {
        $format = "[%datetime%] %level_name%: %message%\n%context%\n%extra%\n";
        $formatter = new LineFormatter(
            format: $format,
            dateFormat: 'H:i',
            allowInlineLineBreaks: true,
            ignoreEmptyContextAndExtra: true,
            includeStacktraces: false,
        );

        foreach ($monolog->getHandlers() as $handler) {
            $handler->setFormatter($formatter);
        }
    }
}

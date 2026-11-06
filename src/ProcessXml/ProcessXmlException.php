<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ProcessXml;

use RuntimeException;

/**
 * Twinfield did not process (all of) the request: the root element has `result="0"`.
 *
 * In a bulk request other elements may have been processed; see `$result`.
 */
final class ProcessXmlException extends RuntimeException
{
    public function __construct(
        public readonly ProcessXmlResult $result,
    ) {
        $errors = $result->errors();

        if ($errors === []) {
            parent::__construct(sprintf(
                'Twinfield did not process the <%s> request and gave no message.',
                $result->root()->localName,
            ));

            return;
        }

        $message = sprintf('%s: %s', $errors[0]->path, $errors[0]->text);

        if (count($errors) > 1) {
            $message .= sprintf(' (and %d more errors)', count($errors) - 1);
        }

        parent::__construct($message);
    }
}

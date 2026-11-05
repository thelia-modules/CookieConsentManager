<?php

declare(strict_types=1);

namespace CookieConsentManager\Transfer;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ConfigSchemaValidator
{
    public function __construct(
        #[Autowire(value: __DIR__.'/../Config/schema/ccm-config-1.schema.json')]
        private string $schemaPath,
    ) {
    }

    /**
     * @return list<string> one line per violation, empty when valid
     */
    public function violations(string $json): array
    {
        $data = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
        $schema = $this->strictAnchors(json_decode((string) file_get_contents($this->schemaPath), false, 512, \JSON_THROW_ON_ERROR));

        $validator = new Validator();
        $validator->validate($data, $schema, Constraint::CHECK_MODE_NORMAL);

        return array_map(
            static fn (array $error): string => ('' === $error['property'] ? '(root)' : $error['property']).': '.$error['message'],
            $validator->getErrors(),
        );
    }

    /** The schema stays portable (`$`); the validator matches it with PCRE, where `$` also accepts a final line feed. */
    private function strictAnchors(mixed $node): mixed
    {
        if (\is_array($node)) {
            return array_map($this->strictAnchors(...), $node);
        }
        if (!$node instanceof \stdClass) {
            return $node;
        }

        $strict = new \stdClass();
        foreach (get_object_vars($node) as $name => $value) {
            if ('pattern' === $name && \is_string($value)) {
                $value = $this->endAnchor($value);
            }
            if ('patternProperties' === $name && $value instanceof \stdClass) {
                $renamed = new \stdClass();
                foreach (get_object_vars($value) as $pattern => $schema) {
                    $renamed->{$this->endAnchor((string) $pattern)} = $this->strictAnchors($schema);
                }
                $strict->{$name} = $renamed;

                continue;
            }
            $strict->{$name} = $this->strictAnchors($value);
        }

        return $strict;
    }

    private function endAnchor(string $pattern): string
    {
        return preg_replace('/(?<!\\\\)\$$/D', '\\z', $pattern) ?? $pattern;
    }
}

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
        $schema = json_decode((string) file_get_contents($this->schemaPath), false, 512, \JSON_THROW_ON_ERROR);

        $validator = new Validator();
        $validator->validate($data, $schema, Constraint::CHECK_MODE_NORMAL);

        return array_map(
            static fn (array $error): string => ('' === $error['property'] ? '(root)' : $error['property']).': '.$error['message'],
            $validator->getErrors(),
        );
    }
}

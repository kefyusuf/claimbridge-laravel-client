<?php

declare(strict_types=1);

namespace ClaimBridge\Medula\Client;

use ClaimBridge\Medula\Spec\Bundle;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

/**
 * Optional L6 rules layer: validates outgoing payloads with the
 * generated Laravel rule classes (one per input DVO) — adding the
 * guide's max:N lengths and in:... membership lists on top of what the
 * default PayloadValidator checks. Uses a bare Illuminate Validation
 * Factory so the framework-agnostic core stays Laravel-independent.
 */
final class RulesValidator implements PayloadValidatorInterface
{
    private readonly Factory $factory;

    public function __construct(private readonly Bundle $bundle)
    {
        $this->factory = new Factory(
            new Translator(new ArrayLoader, 'en'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws PayloadValidationException
     */
    public function validate(string $methodId, array $payload): void
    {
        $ruleClass = $this->resolveRuleClass($methodId);
        if ($ruleClass === null) {
            return; // no generated rule class for this input DVO
        }

        $rules = $ruleClass::rules();
        if (! is_array($rules)) {
            throw new \RuntimeException(
                "generated rules class {$ruleClass} must expose rules(): array",
            );
        }

        $validator = $this->factory->make($payload, $rules);
        if (! $validator->fails()) {
            return;
        }

        /** @var string $first */
        $first = array_key_first($validator->failed());

        throw new PayloadValidationException(
            $first,
            "Laravel rules validation failed - {$first}",
        );
    }

    /**
     * Resolves the generated rule class for the method's input DVO:
     * ClaimBridge\Medula\Rules\<PascalDomain>\<InputDvo>Rules — null when
     * the method has no input contract or no generated class.
     */
    private function resolveRuleClass(string $methodId): ?string
    {
        $method = $this->bundle->methodById($methodId);
        if ($method === null) {
            throw new \RuntimeException("unknown method: {$methodId}");
        }

        $contract = $method['contract'] ?? null;
        $inputDvoId = is_array($contract) ? ($contract['inputDvo'] ?? null) : null;
        if (! is_string($inputDvoId) || $inputDvoId === '') {
            return null;
        }

        $domain = $this->bundle->dvoById($inputDvoId)['domain'] ?? null;
        if (! is_string($domain) || $domain === '') {
            return null;
        }

        $pascalDomain = str_replace(
            ' ',
            '',
            ucwords(str_replace('-', ' ', $domain)),
        );
        $ruleClass = "ClaimBridge\\Medula\\Rules\\{$pascalDomain}\\{$inputDvoId}Rules";

        return class_exists($ruleClass) ? $ruleClass : null;
    }
}

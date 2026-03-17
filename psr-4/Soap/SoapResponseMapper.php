<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Soap;

use DOMElement;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use SolveBeam\Twinfield\Soap\SoapName;

final class SoapResponseMapper
{
    public function mapFromResponseElement(string $responseClass, DOMElement $element): object
    {
        if (!class_exists($responseClass)) {
            throw new LogicException(sprintf('Response class "%s" does not exist.', $responseClass));
        }

        $reflection = new ReflectionClass($responseClass);
		$attrs = $reflection->getAttributes(SoapName::class);

		if ($attrs === []) {
			throw new LogicException(sprintf(
				'Class "%s" must be annotated with #[SoapName].',
				$responseClass
			));
		}

		$responseAttr = $attrs[0]->newInstance();
		$responseElement = $this->findFirstElementByLocalName($element, $responseAttr->name);
        if (!$responseElement instanceof DOMElement) {
            throw new LogicException(sprintf(
                'Response element "%s" not found in SOAP response.',
                $responseAttr->element
            ));
        }

        return $this->mapObject($responseClass, $responseElement);
    }

    private function mapObject(string $className, DOMElement $element): object
    {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            // No constructor, create empty instance
            return $reflection->newInstance();
        }

        $constructorArgs = [];
        foreach ($constructor->getParameters() as $param) {
            $paramName = $param->getName();
            $value = $this->findMapForParameter($param, $element);
            $constructorArgs[$paramName] = $value;
        }

        return $reflection->newInstanceArgs(array_values($constructorArgs));
    }

    private function findMapForParameter(ReflectionParameter $param, DOMElement $element): mixed
    {
        $type = $param->getType();
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        $xmlElementName = $this->getXmlElementName($param);  // Will throw if not defined
        $childElement = $this->findFirstElementByLocalName($element, $xmlElementName);

        // If it's a class type (not builtin), recursively map
        if (!$type->isBuiltin()) {
            if ($childElement === null && $param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            if ($childElement === null) {
                if ($type->allowsNull()) {
                    return null;
                }

                throw new LogicException(sprintf(
                    'Required element "%s" not found for parameter "%s" in XML.',
                    $xmlElementName,
                    $param->getName()
                ));
            }

            return $this->mapObject($type->getName(), $childElement);
        }

        // Primitive type
        if ($childElement === null) {
            return $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
        }

        $value = trim($childElement->textContent);

        return $this->coerceValue($value, $type);
    }

    private function getXmlElementName(ReflectionParameter $param): string
    {
        // SoapName attribute is REQUIRED
        $attrs = $param->getAttributes(SoapName::class);
        if ($attrs === []) {
            throw new LogicException(sprintf(
                'Parameter "%s" must be annotated with #[SoapName(...)] to map SOAP response elements explicitly.',
                $param->getName()
            ));
        }

        return $attrs[0]->newInstance()->name;
    }

    private function findFirstElementByLocalName(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function coerceValue(string $value, ReflectionNamedType $type): mixed
    {
        $typeName = $type->getName();

        return match ($typeName) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => $value !== '' && $value !== '0' && strtolower($value) !== 'false',
            'string' => $value,
            default => $value,
        };
    }
}

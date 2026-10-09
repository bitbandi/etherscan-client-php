<?php

declare(strict_types=1);

namespace Amashukov\Etherscan\Vo;

use Amashukov\Etherscan\EtherscanException;

final readonly class Row
{
    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(
        private array $values,
        private string $kind,
    ) {}

    public function string(string $key): string
    {
        $value = $this->values[$key] ?? null;
        if (!is_string($value)) {
            throw new EtherscanException(sprintf('Etherscan returned a %s without a string "%s".', $this->kind, $key));
        }

        return $value;
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }

    public function integer(string $key): int
    {
        $value = $this->string($key);
        if (1 !== preg_match('/^\d+$/', $value)) {
            throw new EtherscanException(sprintf('Etherscan returned a %s whose "%s" is not a whole number: "%s".', $this->kind, $key, $value));
        }

        return (int) $value;
    }

    /**
     * @return numeric-string
     */
    public function amount(string $key): string
    {
        $value = $this->string($key);
        if (1 !== preg_match('/^\d+$/', $value) || !is_numeric($value)) {
            throw new EtherscanException(sprintf('Etherscan returned a %s whose "%s" is not a whole amount: "%s".', $this->kind, $key, $value));
        }

        return $value;
    }

    public function address(string $key): string
    {
        return strtolower($this->string($key));
    }

    public function optionalAddress(string $key): ?string
    {
        $value = $this->optionalString($key);

        return null === $value ? null : strtolower($value);
    }

    public function flag(string $key): bool
    {
        return '1' === $this->optionalString($key);
    }
}

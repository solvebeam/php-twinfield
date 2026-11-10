<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use InvalidArgumentException;

/**
 * A budget to create or update (REST API). Immutable: every method returns a new input.
 *
 *   $input = BudgetInput::create('2027', 'Budget 2027')->description('First draft');
 *
 * For an existing budget, start from `$budget->edit()`; the code can't change.
 */
final readonly class BudgetInput
{
    private function __construct(
        public string $code,
        public string $name,
        public ?string $description,
        public bool $useSystemPeriods,
    ) {
        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('Budget code and name must not be empty.');
        }
    }

    public static function create(string $code, string $name): self
    {
        return new self($code, $name, null, false);
    }

    public function name(string $name): self
    {
        return new self($this->code, $name, $this->description, $this->useSystemPeriods);
    }

    public function description(?string $description): self
    {
        return new self($this->code, $this->name, $description === '' ? null : $description, $this->useSystemPeriods);
    }

    /**
     * Also budget the system periods (0 and 55 and up).
     */
    public function useSystemPeriods(bool $use = true): self
    {
        return new self($this->code, $this->name, $this->description, $use);
    }
}

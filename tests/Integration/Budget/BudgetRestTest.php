<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Budget;

use SolveBeam\Twinfield\OAuth2\Scope;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live REST API budgets and spreading templates (read-only) of TWINFIELD_COMPANY.
 */
final class BudgetRestTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireScopes(Scope::ReadBudgets, Scope::ReadBudgetScenarios);
    }

    public function testReadsBudgetsTheirLinesAndExport(): void
    {
        $budgets = $this->twinfield->budgets();
        $all = iterator_to_array($budgets->list()->all(), false);

        if ($all === []) {
            self::markTestSkipped('The company has no budgets.');
        }

        $budget = $budgets->getById($all[0]->id);

        self::assertEquals($all[0], $budget);

        $codes = array_map(
            static fn ($summary): string => $summary->code,
            iterator_to_array($budgets->search()->all(), false),
        );

        self::assertContains($budget->code, $codes, 'The Finder finds the same budget by code.');

        foreach ($budgets->annual()->get() as $annual) {
            $lines = $budgets->lines($annual->budget->id, $annual->year)->limit(5)->get();

            self::assertNotEmpty($lines->items);
            self::assertEquals($lines->items[0], $budgets->line($annual->budget->id, $lines->items[0]->key));
            self::assertStringStartsWith(
                'Budget code,',
                $budgets->export($annual->budget->id, $annual->year)->contents,
            );

            break;
        }
    }

    public function testReadsTheImportTemplateAndSpreadingTemplates(): void
    {
        self::assertStringStartsWith(
            'Budget code,General ledger',
            $this->twinfield->budgets()->importTemplate()->contents,
        );
        self::assertNotEmpty($this->twinfield->spreadingTemplates()->defaultDistribution());

        foreach ($this->twinfield->spreadingTemplates()->search()->withDistribution()->all() as $template) {
            self::assertEquals($template, $this->twinfield->spreadingTemplates()->get($template->id));
        }
    }
}

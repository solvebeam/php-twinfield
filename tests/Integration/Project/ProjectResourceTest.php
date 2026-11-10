<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Project;

use SolveBeam\Twinfield\Project\ProjectSummary;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live project searches and reads (read-only) against TWINFIELD_COMPANY.
 */
final class ProjectResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsProjects(): void
    {
        $projects = $this->twinfield->projects();
        $page = $projects->search()->limit(5)->get();

        if ($page->totalRows === 0) {
            self::markTestSkipped('The company has no projects.');
        }

        self::assertSame($page->totalRows, $projects->search()->count());

        $summary = $page->items[0];
        $project = $projects->get($summary->code);

        self::assertSame($summary->code, $project->code);
        self::assertSame($summary->name, $project->name);
        self::assertSame($this->environment->config->company(), $project->company->code);
        self::assertNotSame('', $project->uid);
    }

    public function testSearchesByCode(): void
    {
        $projects = $this->twinfield->projects();
        $first = $projects->search()->limit(1)->get()->items[0] ?? null;

        if ($first === null) {
            self::markTestSkipped('The company has no projects.');
        }

        $codes = array_map(
            static fn (ProjectSummary $project): string => $project->code,
            iterator_to_array($projects->search($first->code)->inCode()->all(), false),
        );

        self::assertSame([$first->code], $codes);
    }

    public function testReadsEveryProject(): void
    {
        $count = 0;

        foreach ($this->twinfield->projects()->search()->limit(50)->all() as $summary) {
            self::assertSame($summary->code, $this->twinfield->projects()->get($summary->code)->code);

            if (++$count === 10) {
                break;
            }
        }

        if ($count === 0) {
            self::markTestSkipped('The company has no projects.');
        }
    }

    public function testUnknownProjectIsNotFound(): void
    {
        $projects = $this->twinfield->projects();
        $code = $this->unusedCode();

        self::assertNull($projects->find($code));

        $this->expectException(DimensionNotFoundException::class);

        $projects->get($code);
    }

    private function unusedCode(): string
    {
        for ($number = 9999; $number > 9900; $number--) {
            $code = sprintf('P%04d', $number);

            // find(), not search(): deleted projects keep their code.
            if ($this->twinfield->projects()->find($code) === null) {
                return $code;
            }
        }

        self::markTestSkipped('No unused project code P9901–P9999.');
    }
}

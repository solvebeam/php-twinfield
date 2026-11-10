<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use InvalidArgumentException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\NotFoundException;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Spreading templates of the company (REST API, Budgeting Spreading Templates): how a yearly budget
 * amount is divided over the periods. Scope `readBudgetScenarios`, `writeBudgetScenarios` to change them.
 *
 *   $templates = $twinfield->company('1000')->spreadingTemplates();
 *
 *   $weights = $templates->defaultDistribution();          // 12 periods, weight 1 each
 *   $template = $templates->create('SEASON', 'Season', [new PeriodWeight(1, 2), new PeriodWeight(2, 1)]);
 *
 * @see https://api.accounting.twinfield.com/Api/swagger/ui/index#!/Budgeting32Spreading32Templates
 */
final readonly class SpreadingTemplateResource
{
    private const string PATH = '/organisations/{organisationId}/companies/{companyId}/budgeting/spreadingtemplates';

    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    public function search(string $pattern = '*'): SpreadingTemplateSearch
    {
        $search = new SpreadingTemplateSearch($this->twinfield, self::PATH);

        return $pattern === '*' ? $search : $search->pattern($pattern);
    }

    /**
     * @throws NotFoundException When the company has no template with this GUID.
     */
    public function get(string $id): SpreadingTemplate
    {
        return self::read($this->twinfield->rest('GET', self::PATH . '/' . Guid::of($id))->json(), $this->company());
    }

    /**
     * @param list<PeriodWeight> $distribution
     */
    public function create(string $code, string $name, array $distribution): SpreadingTemplate
    {
        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('Spreading template code and name must not be empty.');
        }

        $response = $this->twinfield->rest('POST', self::PATH, body: [
            'code' => $code,
            'name' => $name,
            'distribution' => self::weights($distribution),
        ]);

        $location = $response->header('Location');

        if (preg_match('~/([0-9a-fA-F-]{36})/?$~', $location, $matches) !== 1) {
            throw new UnexpectedResponseException(sprintf(
                'Twinfield created the spreading template (HTTP %d) without its GUID in Location: "%s".',
                $response->status(),
                $location,
            ));
        }

        return $this->get($matches[1]);
    }

    /**
     * Changes the name and weights; the code can't change.
     *
     * @param list<PeriodWeight> $distribution
     */
    public function update(string $id, string $name, array $distribution): SpreadingTemplate
    {
        $this->twinfield->rest('PUT', self::PATH . '/' . Guid::of($id), body: [
            'name' => $name,
            'distribution' => self::weights($distribution),
        ]);

        return $this->get($id);
    }

    public function delete(string $id): void
    {
        $this->twinfield->rest('DELETE', self::PATH . '/' . Guid::of($id));
    }

    /**
     * The weights a new template starts with: weight 1 for every period of the company.
     *
     * @return list<PeriodWeight>
     */
    public function defaultDistribution(): array
    {
        $json = $this->twinfield->rest('GET', self::PATH . '/defaultdistribution')->json();

        return array_map(self::weight(...), $json->list('items'));
    }

    /**
     * @internal Also used by SpreadingTemplateSearch.
     */
    public static function read(JsonValue $json, CompanyReference $company): SpreadingTemplate
    {
        return new SpreadingTemplate(
            company: $company,
            id: $json->uuid('id'),
            code: $json->string('code'),
            name: $json->string('name'),
            distribution: array_map(self::weight(...), $json->list('distribution')),
            lastModifiedAt: $json->timestamp('lastModifiedAt'),
        );
    }

    private static function weight(JsonValue $json): PeriodWeight
    {
        return new PeriodWeight($json->int('period'), $json->int('weight'));
    }

    /**
     * @param list<PeriodWeight> $distribution
     *
     * @return list<array{period: int, weight: int}>
     */
    private static function weights(array $distribution): array
    {
        if ($distribution === []) {
            throw new InvalidArgumentException('A spreading template needs the weights of the periods.');
        }

        return array_map(
            static fn (PeriodWeight $weight): array => ['period' => $weight->period, 'weight' => $weight->weight],
            $distribution,
        );
    }

    private function company(): CompanyReference
    {
        return $this->twinfield->companyReference();
    }
}

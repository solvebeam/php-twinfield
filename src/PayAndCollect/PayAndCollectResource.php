<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\PayAndCollect\Request\Authorize;
use SolveBeam\Twinfield\PayAndCollect\Request\CreateFiles;
use SolveBeam\Twinfield\PayAndCollect\Request\CreateRun2;
use SolveBeam\Twinfield\PayAndCollect\Request\CreateRunAsync;
use SolveBeam\Twinfield\PayAndCollect\Request\Finish;
use SolveBeam\Twinfield\PayAndCollect\Request\GetRunInfo;
use SolveBeam\Twinfield\PayAndCollect\Request\GetRuns;
use SolveBeam\Twinfield\PayAndCollect\Request\Modify;
use SolveBeam\Twinfield\PayAndCollect\Request\Post;
use SolveBeam\Twinfield\PayAndCollect\Request\PostAsync;
use SolveBeam\Twinfield\Soap\ClassicServiceFaultException;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Payment runs (paying suppliers) and direct debit runs (collecting from customers), and their
 * SEPA files.
 *
 *   $payAndCollect = $twinfield->company('1000')->payAndCollect();
 *
 *   $run = $payAndCollect->create(
 *       PayRunInput::create('SEPANLCT', new DateTimeImmutable('2026-11-12'), '2026/11'),
 *   )->run;
 *
 *   $version = $payAndCollect->modify(PayRunModification::of($run)->excludeSummary(3));
 *   $version = $payAndCollect->authorise($run, $version);
 *   $version = $payAndCollect->post($run, $version);
 *   $files = $payAndCollect->createFiles($run, $version);
 *   $payAndCollect->finish($run, $files->version);
 *
 * Every step that changes a run needs its version (`touched` and `modified`) and returns the new
 * one; without a version, the version the run was read with is used. Posting and creating files
 * can't be undone: finish() deletes a run only before it is posted.
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/
 */
final readonly class PayAndCollectResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * The pay runs of the company, without their summaries (GetRuns).
     *
     * @return list<PayRunOverview>
     */
    public function list(): array
    {
        $response = $this->send(new GetRuns($this->office()));
        $company = $this->twinfield->companyReference();

        // Left out or empty when the company has no runs.
        if ($response->nullableChild('GetRunsResult') === null) {
            return [];
        }

        return array_map(
            static fn (ResultElement $run): PayRunOverview => PayRunOverview::fromResult($run, $company),
            $response->list('GetRunsResult', 'PayOverview'),
        );
    }

    /**
     * A pay run with its summaries and invoices (GetRunInfo).
     *
     * @param string $payType E.g. `SEPANLCT`.
     */
    public function get(string $payType, int $id): PayRun
    {
        if ($payType === '') {
            throw new InvalidArgumentException('Pay type must not be empty.');
        }

        if ($id < 1) {
            throw new InvalidArgumentException(sprintf('Pay run ID must be 1 or more, got %d.', $id));
        }

        $response = $this->send(new GetRunInfo($this->office(), $payType, $id));

        return PayRun::fromResult($response->child('GetRunInfoResult'), $this->twinfield->companyReference());
    }

    /**
     * Creates a pay run and selects the open invoices (CreateRun2).
     *
     * @throws PayAndCollectException E.g. when no invoices were selected, or a setting differs
     *                                from the pay type.
     */
    public function create(PayRunInput $input): CreatedPayRun
    {
        $response = $this->send(new CreateRun2($input->toRequest($this->office())));
        $messages = $this->messages($response, 'CreateRun2Result', 'MessageOfCreateRunResult');

        return new CreatedPayRun(
            PayRun::fromResult($response->child('payRun'), $this->twinfield->companyReference()),
            $messages,
        );
    }

    /**
     * Starts creating a large pay run (CreateRunAsync). The run is ready when list() or get()
     * shows status Done in phase Select.
     *
     * @return list<PayAndCollectMessage> Warnings and informational messages.
     */
    public function createAsync(PayRunInput $input): array
    {
        $response = $this->send(new CreateRunAsync($input->toRequest($this->office())));

        return $this->messages($response, 'CreateRunAsyncResult', 'MessageOfCreateRunResult');
    }

    /**
     * Excludes summaries or invoices, or changes amounts (Modify). Only in phase Select (status
     * Done, with selected transactions) or Manipulate.
     *
     * @param PayRunVersion|null $version Default: the version of the run of the modification.
     */
    public function modify(PayRunModification $modification, ?PayRunVersion $version = null): PayRunVersion
    {
        $run = $modification->run;
        $version ??= $run->version;
        $response = $this->send(new Modify(
            $this->office(),
            $run->payType,
            $run->id,
            $version->touched,
            $version->modifiedValue,
            $modification->toSummaries(),
        ));

        return $this->version($response, 'ModifyResult', 'MessageOfModifyResult');
    }

    /**
     * Authorises the run (Authorize): after this, its data can't be changed. Only up to phase
     * Manipulate, with status Done.
     */
    public function authorise(PayRun|PayRunOverview $run, ?PayRunVersion $version = null): PayRunVersion
    {
        $version ??= $run->version;
        $response = $this->send(new Authorize(
            $this->office(),
            $run->payType,
            $run->id,
            $version->touched,
            $version->modifiedValue,
        ));

        return $this->version($response, 'AuthorizeResult', 'MessageOfAuthoriseResult');
    }

    /**
     * Posts the summaries to the in-transfer ledger and matches them with the invoices (Post).
     * Can't be undone. Only after authorise(), or again after a post with errors.
     */
    public function post(PayRun|PayRunOverview $run, ?PayRunVersion $version = null): PayRunVersion
    {
        $version ??= $run->version;
        $response = $this->send(new Post(
            $this->office(),
            $run->payType,
            $run->id,
            $version->touched,
            $version->modifiedValue,
        ));

        return $this->version($response, 'PostResult', 'MessageOfPostResult');
    }

    /**
     * Starts posting a large run (PostAsync). Read the run with get() for the result: the returned
     * version may already be outdated.
     */
    public function postAsync(PayRun|PayRunOverview $run, ?PayRunVersion $version = null): PayRunVersion
    {
        $version ??= $run->version;
        $response = $this->send(new PostAsync(
            $this->office(),
            $run->payType,
            $run->id,
            $version->touched,
            $version->modifiedValue,
        ));

        return $this->version($response, 'PostAsyncResult', 'MessageOfPostResult');
    }

    /**
     * Creates the payment file(s) of a posted run (CreateFiles). Twinfield creates more files when
     * the bank limits the number of transactions per file.
     *
     * @param array<string, string> $exportSettings Optional settings of the file format, by name.
     */
    public function createFiles(
        PayRun|PayRunOverview $run,
        ?PayRunVersion $version = null,
        array $exportSettings = [],
    ): PayRunFiles {
        $version ??= $run->version;
        $response = $this->send(new CreateFiles(
            $this->office(),
            $run->payType,
            $run->id,
            $version->touched,
            $version->modifiedValue,
            PayRunInput::settings($exportSettings),
        ));
        $version = $this->version($response, 'CreateFilesResult', 'MessageOfCreateFilesResult');

        // minOccurs="0": left out when no file was created.
        $files = $response->nullableChild('payFiles') === null
            ? []
            : array_map(self::file(...), $response->list('payFiles', 'base64Binary'));

        return new PayRunFiles($files, $version);
    }

    /**
     * Deletes a run that is not posted yet, or ends a run after its files were created (Finish).
     * All run data is removed from Twinfield.
     *
     * @return list<PayAndCollectMessage> Warnings and informational messages.
     */
    public function finish(PayRun|PayRunOverview $run, ?PayRunVersion $version = null): array
    {
        $version ??= $run->version;
        $response = $this->send(new Finish(
            $this->office(),
            $run->payType,
            $run->id,
            $version->touched,
            $version->modifiedValue,
        ));

        return $this->messages($response, 'FinishResult', 'MessageOfFinishResult');
    }

    /**
     * Sends the request and returns the `<Operation>Response` element. The operation is the short
     * class name of the request.
     *
     * @throws ClassicServiceFaultException
     */
    private function send(object $request): ResultElement
    {
        $operation = substr($request::class, (int) strrpos($request::class, '\\') + 1);
        $expected = $operation . 'Response';
        $payload = $this->twinfield->call('payandcollect', $request)->payloadElement();

        if ($payload === null || $payload->localName !== $expected) {
            throw new UnexpectedResponseException(sprintf(
                'Expected <%s>, got %s.',
                $expected,
                $payload === null ? 'an empty SOAP body' : '<' . $payload->localName . '>',
            ));
        }

        return new ResultElement($payload, $expected);
    }

    /**
     * The messages of the result, after throwing the errors.
     *
     * @return list<PayAndCollectMessage> Warnings and informational messages.
     *
     * @throws PayAndCollectException
     */
    private function messages(ResultElement $response, string $result, string $itemName): array
    {
        $messages = PayAndCollectXml::messages($response, $result, $itemName);
        $errors = array_values(array_filter(
            $messages,
            static fn (PayAndCollectMessage $message): bool => $message->type === PayAndCollectMessageType::Error,
        ));

        if ($errors !== []) {
            throw new PayAndCollectException(substr($result, 0, -strlen('Result')), $errors);
        }

        return $messages;
    }

    /**
     * The new `touched` and `modified` of a step, on the response element (not in the messages,
     * as the documentation shows).
     */
    private function version(ResultElement $response, string $result, string $itemName): PayRunVersion
    {
        $messages = $this->messages($response, $result, $itemName);

        return PayAndCollectXml::version($response, 'touched', 'modified', $messages);
    }

    private static function file(ResultElement $file): string
    {
        $contents = base64_decode(trim($file->element->textContent), true);

        if ($contents === false || $contents === '') {
            throw new UnexpectedResponseException(sprintf('%s is not a base64 encoded file.', $file->path));
        }

        return $contents;
    }

    private function office(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Pay & Collect needs a company in the request header: use company() first.');
    }
}

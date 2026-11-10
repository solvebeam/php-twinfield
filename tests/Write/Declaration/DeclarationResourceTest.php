<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\Declaration;

use SolveBeam\Twinfield\Declaration\DeclarationException;
use SolveBeam\Twinfield\Declaration\DeclarationStatusMessage;
use SolveBeam\Twinfield\Declaration\DeclarationStatusStep;
use SolveBeam\Twinfield\Declaration\DeclarationSummary;
use SolveBeam\Twinfield\Soap\ClassicServiceFaultException;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Status commands (SetToSent, SetToRejected) on the returns of the write company only, never of
 * the whole organisation. A return is only marked in Twinfield: nothing is sent to the tax
 * authorities. The response shapes are not documented; record them (`TWINFIELD_RECORD=1`) for
 * TODO.md.
 */
final class DeclarationResourceTest extends WriteTestCase
{
    public function testRefusesAnUnknownDocument(): void
    {
        try {
            $this->twinfield->declarations()->setToSent(999999999);
            self::fail('Expected an error for an unknown document.');
        } catch (DeclarationException $exception) {
            self::assertSame(DeclarationStatusMessage::DocumentDoesNotExist, $exception->messages[0]->code);
        } catch (ClassicServiceFaultException $exception) {
            // Like the queries, which give SOAP fault 100 for an unknown document.
            self::assertSame('100', $exception->errorCode);
        }
    }

    /**
     * Marks a return that is still "Created" as sent, then as rejected. Skipped when the
     * company has no such return: create one in Twinfield first (Btw-aangifte, without sending).
     */
    public function testMarksAReturnAsSentAndRejected(): void
    {
        $return = $this->created();
        $declarations = $this->twinfield->declarations();

        $declarations->setToSent($return->id);
        self::assertSame(DeclarationStatusStep::Sent, $this->reread($return)->status->step);

        $declarations->setToRejected($return->id, 'PHP SDK write test');
        self::assertSame(DeclarationStatusStep::Rejected, $this->reread($return)->status->step);
    }

    public function testRefusesToApproveAReturnThatIsNotSent(): void
    {
        $return = $this->created();

        $this->expectException(DeclarationException::class);

        $this->twinfield->declarations()->setToApproved($return->id);
    }

    private function created(): DeclarationSummary
    {
        foreach ($this->twinfield->declarations()->all() as $return) {
            if ($return->status->step === DeclarationStatusStep::Created) {
                return $return;
            }
        }

        self::markTestSkipped(sprintf('Company %s has no return with status Created.', $this->company()));
    }

    private function reread(DeclarationSummary $return): DeclarationSummary
    {
        foreach ($this->twinfield->declarations()->all() as $read) {
            if ($read->id === $return->id) {
                return $read;
            }
        }

        self::fail(sprintf('Return %d is gone.', $return->id));
    }
}

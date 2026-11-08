<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\User;

/**
 * Why a user is deleted (`user/deletereason/code`), e.g.
 * `$twinfield->users()->delete('JDOE', UserDeleteReason::NoLongerOperative, 'Left the company')`.
 *
 * The documentation lists the codes without explaining them; the descriptions below follow
 * from the codes.
 */
enum UserDeleteReason: string
{
    /** Because of the product. */
    case Product = 'product';

    /** Because of the (new) service. */
    case ServiceNew = 'servicenew';

    /** Because of the (new) pricing. */
    case PricingNew = 'pricingnew';

    /** Because the Twinfield subscription changes. */
    case SubscriptionChange = 'changetwfsubscription';

    /** Because of non-payment. */
    case Defaulting = 'defaulting';

    /** Because the user no longer works with Twinfield, e.g. left the organisation. */
    case NoLongerOperative = 'nolongeroperative';
}

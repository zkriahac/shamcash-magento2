<?php
/**
 * The linked Sham Cash account has no usable subscription.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway\Exception;

/**
 * Raised for SUBSCRIPTION_UNAVAILABLE — the link is inactive, the plan ended,
 * or no subscription is associated. Reconciliation cannot continue until the
 * merchant renews on shamcash-api.com.
 */
class SubscriptionUnavailableException extends ApiException
{
}

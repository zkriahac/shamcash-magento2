<?php
/**
 * Authentication failure against the Sham Cash API.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway\Exception;

/**
 * Raised for AUTH_MISSING / AUTH_INVALID / FORBIDDEN — the token is absent,
 * unknown, expired, revoked, or not permitted for the action.
 */
class AuthenticationException extends ApiException
{
}

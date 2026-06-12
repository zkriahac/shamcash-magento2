<?php
/**
 * Sham Cash payment gateway for Magento 2.
 *
 * @see https://api.shamcash-api.com/v1
 */
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'ShamCash_Payment',
    __DIR__
);

<?php
/**
 * Source model for the reconciliation matching mode.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use ShamCash\Payment\Model\Config;

class MatchingMode implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::MATCH_MODE_BOTH, 'label' => __('Reference (note) first, then amount')],
            ['value' => Config::MATCH_MODE_NOTE, 'label' => __('Reference (note) only')],
            ['value' => Config::MATCH_MODE_AMOUNT, 'label' => __('Amount + currency only')],
        ];
    }
}

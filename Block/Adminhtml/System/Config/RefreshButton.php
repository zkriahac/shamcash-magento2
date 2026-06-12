<?php
/**
 * "Refresh account info" button for the Sham Cash config screen.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders a button that calls /shamcash/account/refresh to pull the linked
 * account's wallet address, QR payload and subscription status from /accounts
 * and store them in config (so checkout does not hit the API per page view).
 */
class RefreshButton extends Field
{
    protected $_template = 'ShamCash_Payment::system/config/refresh_button.phtml';

    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Remove the scope label so the button spans the row.
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    /**
     * HTML for the clickable button, rendered by the template.
     */
    public function getButtonHtml(): string
    {
        return $this->getLayout()->createBlock(Button::class)
            ->setData([
                'id' => 'shamcash_refresh_account',
                'label' => __('Refresh account info'),
            ])
            ->toHtml();
    }

    public function getAjaxUrl(): string
    {
        return $this->getUrl('shamcash/account/refresh');
    }
}

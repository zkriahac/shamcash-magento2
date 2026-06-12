<?php
/**
 * Admin: refresh cached Sham Cash account info from /accounts.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Controller\Adminhtml\Account;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use ShamCash\Payment\Gateway\ApiClient;
use ShamCash\Payment\Gateway\Dto\Account;
use ShamCash\Payment\Gateway\Exception\ApiException;
use ShamCash\Payment\Model\Config;

/**
 * Pulls the linked account from /accounts and caches its wallet address, QR
 * payload and status into config so checkout never has to call the API.
 */
class Refresh extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'ShamCash_Payment::config';

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ApiClient $apiClient,
        private readonly Config $config,
        private readonly WriterInterface $configWriter
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        try {
            $accounts = $this->apiClient->accounts();
        } catch (ApiException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Could not reach the Sham Cash API: %1', $e->getMessage())->render(),
            ]);
        }

        if ($accounts === []) {
            return $result->setData([
                'success' => false,
                'message' => __('No linked Sham Cash accounts were returned for this token.')->render(),
            ]);
        }

        $account = $this->resolveAccount($accounts);
        if ($account === null) {
            return $result->setData([
                'success' => false,
                'message' => __('Multiple accounts are linked. Set the Account ID first, then refresh.')->render(),
            ]);
        }

        $this->configWriter->save('payment/shamcash/account_id', $account->getId());
        $this->configWriter->save('payment/shamcash/account_address', (string)$account->getAddress());
        $this->configWriter->save('payment/shamcash/account_qr_payload', (string)$account->getQrPayload());

        $message = $account->isActive()
            ? __('Account "%1" loaded. Wallet and QR updated.', $account->getId())
            : __('Account "%1" loaded but is INACTIVE — reconciliation will not work until reactivated.', $account->getId());

        return $result->setData(['success' => true, 'message' => $message->render()]);
    }

    /**
     * Use the configured account when set; otherwise the only one available.
     *
     * @param Account[] $accounts
     */
    private function resolveAccount(array $accounts): ?Account
    {
        $configured = $this->config->getAccountId();
        if ($configured !== '') {
            foreach ($accounts as $account) {
                if ($account->getId() === $configured) {
                    return $account;
                }
            }
        }
        return count($accounts) === 1 ? $accounts[0] : null;
    }
}

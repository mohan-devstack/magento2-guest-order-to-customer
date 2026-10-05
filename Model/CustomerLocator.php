<?php
declare(strict_types=1);

namespace Mohan\GuestOrderToCustomer\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Finds the customer account that a guest order can be mapped to.
 */
class CustomerLocator
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * Return the customer whose email matches the guest order on the order's website, or null.
     *
     * @param OrderInterface $order
     * @return CustomerInterface|null
     */
    public function getCustomerForOrder(OrderInterface $order): ?CustomerInterface
    {
        if (!$order->getCustomerIsGuest() || $order->getCustomerId() || !$order->getCustomerEmail()) {
            return null;
        }

        try {
            $websiteId = (int)$this->storeManager->getStore($order->getStoreId())->getWebsiteId();
            return $this->customerRepository->get($order->getCustomerEmail(), $websiteId);
        } catch (LocalizedException $e) {
            // Covers NoSuchEntityException (no matching customer or store).
            return null;
        }
    }
}

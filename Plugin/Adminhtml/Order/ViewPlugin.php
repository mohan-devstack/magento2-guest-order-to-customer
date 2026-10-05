<?php
declare(strict_types=1);

namespace Mohan\GuestOrderToCustomer\Plugin\Adminhtml\Order;

use Mohan\GuestOrderToCustomer\Model\Config;
use Mohan\GuestOrderToCustomer\Model\CustomerLocator;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Block\Adminhtml\Order\View;
use Psr\Log\LoggerInterface;

/**
 * Adds the "Map to Customer" button to guest orders that have a matching customer account.
 */
class ViewPlugin
{
    /**
     * Hex-encode every character that HTML treats specially. The button renders attributes with
     * escapeHtmlAttr($value, false), which keeps existing entities, so a customer name or email
     * containing "&quot;" would otherwise become a real quote inside the mage-init JSON.
     */
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * @var CustomerLocator
     */
    private $customerLocator;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CustomerLocator $customerLocator
     * @param Config $config
     * @param AuthorizationInterface $authorization
     * @param LoggerInterface $logger
     */
    public function __construct(
        CustomerLocator $customerLocator,
        Config $config,
        AuthorizationInterface $authorization,
        LoggerInterface $logger
    ) {
        $this->customerLocator = $customerLocator;
        $this->config = $config;
        $this->authorization = $authorization;
        $this->logger = $logger;
    }

    /**
     * Add the button before the layout renders the toolbar.
     *
     * @param View $subject
     * @param LayoutInterface $layout
     * @return null
     */
    public function beforeSetLayout(View $subject, LayoutInterface $layout)
    {
        try {
            if (!$this->authorization->isAllowed('Mohan_GuestOrderToCustomer::assign')) {
                return null;
            }

            $order = $subject->getOrder();
            if (!$order || !$this->config->isEnabled($order->getStoreId())) {
                return null;
            }

            $customer = $this->customerLocator->getCustomerForOrder($order);
            if (!$customer) {
                return null;
            }

            $message = __(
                'Map order #%1 to customer account "%2 %3" (%4, ID %5)? '
                . 'The order will appear in this customer\'s account.',
                $order->getIncrementId(),
                $customer->getFirstname(),
                $customer->getLastname(),
                $customer->getEmail(),
                $customer->getId()
            );

            $subject->addButton(
                'map_to_customer',
                [
                    'label' => __('Map to Customer'),
                    'class' => 'map-to-customer',
                    'data_attribute' => [
                        'mage-init' => json_encode([
                            'Mohan_GuestOrderToCustomer/js/map-to-customer' => [
                                'url' => $subject->getUrl(
                                    'guestordertocustomer/order/assign',
                                    ['order_id' => $order->getEntityId()]
                                ),
                                // Plain text; escaped client-side when shown in the dialog.
                                'message' => (string)$message,
                            ],
                        ], self::JSON_FLAGS),
                    ],
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error('Map to Customer button: ' . $e->getMessage(), ['exception' => $e]);
        }

        return null;
    }
}

<?php
declare(strict_types=1);

namespace Mohan\GuestOrderToCustomer\Controller\Adminhtml\Order;

use Mohan\GuestOrderToCustomer\Model\Config;
use Mohan\GuestOrderToCustomer\Model\CustomerLocator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\CustomerAssignment;
use Psr\Log\LoggerInterface;

/**
 * Maps a guest order to the customer account with the same email on the order's website.
 */
class Assign extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Mohan_GuestOrderToCustomer::assign';

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CustomerLocator
     */
    private $customerLocator;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var CustomerAssignment
     */
    private $customerAssignment;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param CustomerLocator $customerLocator
     * @param Config $config
     * @param CustomerAssignment $customerAssignment
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        CustomerLocator $customerLocator,
        Config $config,
        CustomerAssignment $customerAssignment,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->orderRepository = $orderRepository;
        $this->customerLocator = $customerLocator;
        $this->config = $config;
        $this->customerAssignment = $customerAssignment;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            // get() throws InputException (not NoSuchEntityException) for a missing/zero ID.
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException | InputException $e) {
            $this->messageManager->addErrorMessage(__('This order no longer exists.'));
            return $resultRedirect->setPath('sales/order/');
        }

        $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);

        if (!$this->config->isEnabled($order->getStoreId())) {
            $this->messageManager->addErrorMessage(__('Mapping guest orders to customers is disabled.'));
            return $resultRedirect;
        }

        // Re-check on the server: the order may have changed since the page was loaded.
        $customer = $this->customerLocator->getCustomerForOrder($order);
        if (!$customer) {
            $this->messageManager->addErrorMessage(
                __('This order is not a guest order, or no customer account matches its email on this website.')
            );
            return $resultRedirect;
        }

        try {
            $adminUser = $this->_auth->getUser();
            $order->addCommentToStatusHistory(
                __(
                    'Guest order mapped to customer account %1 (ID %2) by %3.',
                    $customer->getEmail(),
                    $customer->getId(),
                    $adminUser ? $adminUser->getUserName() : __('admin')
                )
            );
            $this->customerAssignment->execute($order, $customer);

            $this->messageManager->addSuccessMessage(
                __('The order has been mapped to customer %1.', $customer->getEmail())
            );
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__('The order could not be mapped to the customer.'));
        }

        return $resultRedirect;
    }
}

<?php
declare(strict_types=1);

namespace Mohan\GuestOrderToCustomer\Test\Unit\Model;

use Mohan\GuestOrderToCustomer\Model\CustomerLocator;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CustomerLocatorTest extends TestCase
{
    /**
     * @var CustomerRepositoryInterface|MockObject
     */
    private $customerRepository;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var CustomerLocator
     */
    private $locator;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->locator = new CustomerLocator($this->customerRepository, $this->storeManager);
    }

    public function testReturnsCustomerForGuestOrderWithMatchingEmail(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $this->mockStoreWebsite(3, 2);
        $this->customerRepository->expects($this->once())
            ->method('get')
            ->with('guest@example.com', 2)
            ->willReturn($customer);

        $this->assertSame($customer, $this->locator->getCustomerForOrder($this->createOrder(true, null)));
    }

    public function testReturnsNullWhenNoCustomerMatches(): void
    {
        $this->mockStoreWebsite(3, 2);
        $this->customerRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('No such entity.')));

        $this->assertNull($this->locator->getCustomerForOrder($this->createOrder(true, null)));
    }

    /**
     * @dataProvider notMappableOrderProvider
     */
    public function testReturnsNullForOrdersThatAreNotMappable(bool $isGuest, ?int $customerId, ?string $email): void
    {
        $this->customerRepository->expects($this->never())->method('get');

        $this->assertNull($this->locator->getCustomerForOrder($this->createOrder($isGuest, $customerId, $email)));
    }

    public static function notMappableOrderProvider(): array
    {
        return [
            'registered customer order' => [false, 5, 'guest@example.com'],
            'guest flag but customer id set' => [true, 5, 'guest@example.com'],
            'guest order without email' => [true, null, null],
        ];
    }

    private function createOrder(bool $isGuest, ?int $customerId, ?string $email = 'guest@example.com'): OrderInterface
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomerIsGuest')->willReturn($isGuest ? 1 : 0);
        $order->method('getCustomerId')->willReturn($customerId);
        $order->method('getCustomerEmail')->willReturn($email);
        $order->method('getStoreId')->willReturn(3);

        return $order;
    }

    private function mockStoreWebsite(int $storeId, int $websiteId): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn($websiteId);
        $this->storeManager->method('getStore')->with($storeId)->willReturn($store);
    }
}

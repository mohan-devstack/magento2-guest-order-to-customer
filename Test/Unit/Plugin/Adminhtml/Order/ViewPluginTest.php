<?php
declare(strict_types=1);

namespace Mohan\GuestOrderToCustomer\Test\Unit\Plugin\Adminhtml\Order;

use Mohan\GuestOrderToCustomer\Model\Config;
use Mohan\GuestOrderToCustomer\Model\CustomerLocator;
use Mohan\GuestOrderToCustomer\Plugin\Adminhtml\Order\ViewPlugin;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Block\Adminhtml\Order\View;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ViewPluginTest extends TestCase
{
    private const COMPONENT = 'Mohan_GuestOrderToCustomer/js/map-to-customer';

    /**
     * @var CustomerLocator|MockObject
     */
    private $customerLocator;

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var AuthorizationInterface|MockObject
     */
    private $authorization;

    /**
     * @var View|MockObject
     */
    private $view;

    /**
     * @var ViewPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->customerLocator = $this->createMock(CustomerLocator::class);
        $this->config = $this->createMock(Config::class);
        $this->authorization = $this->createMock(AuthorizationInterface::class);
        $this->view = $this->createMock(View::class);
        $this->plugin = new ViewPlugin(
            $this->customerLocator,
            $this->config,
            $this->authorization,
            $this->createMock(LoggerInterface::class)
        );

        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(10);
        $order->method('getIncrementId')->willReturn('000000010');
        $this->view->method('getOrder')->willReturn($order);
        $this->view->method('getUrl')
            ->willReturn('https://example.com/admin/guestordertocustomer/order/assign/order_id/10/');
    }

    public function testButtonConfigSurvivesEntitiesInCustomerData(): void
    {
        $this->authorization->method('isAllowed')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(true);
        $firstname = 'Tom &quot;}, &lt;b&gt;';
        $this->customerLocator->method('getCustomerForOrder')
            ->willReturn($this->createCustomer($firstname, "o'neil&co@example.com"));

        $buttonData = null;
        $this->view->expects($this->once())
            ->method('addButton')
            ->with('map_to_customer', $this->callback(function (array $data) use (&$buttonData) {
                $buttonData = $data;
                return true;
            }));

        $this->plugin->beforeSetLayout($this->view, $this->createMock(LayoutInterface::class));

        $json = $buttonData['data_attribute']['mage-init'];
        // Same escaping as Magento\Backend\Block\Widget\Button, then what the browser decodes.
        $attribute = htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        $decoded = json_decode(html_entity_decode($attribute, ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([self::COMPONENT], array_keys($decoded));
        $this->assertStringContainsString($firstname, $decoded[self::COMPONENT]['message']);
        $this->assertStringContainsString("o'neil&co@example.com", $decoded[self::COMPONENT]['message']);
    }

    public function testNoButtonWithoutPermission(): void
    {
        $this->authorization->method('isAllowed')->willReturn(false);
        $this->customerLocator->expects($this->never())->method('getCustomerForOrder');
        $this->view->expects($this->never())->method('addButton');

        $this->plugin->beforeSetLayout($this->view, $this->createMock(LayoutInterface::class));
    }

    public function testNoButtonWhenDisabled(): void
    {
        $this->authorization->method('isAllowed')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(false);
        $this->view->expects($this->never())->method('addButton');

        $this->plugin->beforeSetLayout($this->view, $this->createMock(LayoutInterface::class));
    }

    public function testNoButtonWithoutMatchingCustomer(): void
    {
        $this->authorization->method('isAllowed')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(true);
        $this->customerLocator->method('getCustomerForOrder')->willReturn(null);
        $this->view->expects($this->never())->method('addButton');

        $this->plugin->beforeSetLayout($this->view, $this->createMock(LayoutInterface::class));
    }

    private function createCustomer(string $firstname, string $email): CustomerInterface
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('getFirstname')->willReturn($firstname);
        $customer->method('getLastname')->willReturn('Smith');
        $customer->method('getEmail')->willReturn($email);

        return $customer;
    }
}

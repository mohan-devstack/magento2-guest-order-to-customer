# Guest Order to Customer for Magento 2

Map a guest order to an existing customer account from the Magento admin. When a guest checks out with an email that already belongs to a registered customer on the same website, a **Map to Customer** button appears on the order view page; one click assigns the order to that customer so it shows up in their account.

## Features

- **Map to Customer** button in the admin order view header, shown only for guest orders with a matching customer
- Matches by order email, scoped to the order's website
- Confirmation dialog showing the target customer's name, email and ID before mapping
- Uses Magento's native `Magento\Sales\Model\Order\CustomerAssignment`
- Adds an order history comment recording who mapped the order
- Dedicated ACL resource (`Mohan_GuestOrderToCustomer::assign`) so you control which admin roles can map orders
- Enable/disable per store view

## Requirements

- Magento 2.4.4 or later
- PHP 8.1 or later

## Installation

### Via Composer (recommended)

```bash
composer require mohan-devstack/magento2-guest-order-to-customer
php bin/magento module:enable Mohan_GuestOrderToCustomer
php bin/magento setup:upgrade
php bin/magento cache:clean
```

### Manual

1. Copy the module to `app/code/Mohan/GuestOrderToCustomer/`
2. Run:
   ```bash
   php bin/magento module:enable Mohan_GuestOrderToCustomer
   php bin/magento setup:upgrade
   php bin/magento cache:clean
   ```

## Configuration

**Stores > Configuration > Mohan > Guest Order to Customer**

| Field | Description |
|-------|-------------|
| Enable | Show the **Map to Customer** button and allow mapping (default: Yes) |

## Usage

1. Go to **Sales > Orders** and open a guest order
2. If a customer account exists with the same email on the order's website, click **Map to Customer** in the page header
3. Confirm the dialog — the order is assigned to the customer and a comment is added to the order history

## Permissions

Grant **Sales > Operations > Orders > Actions > Map Guest Order to Customer** to admin roles that should be able to map orders.

## Support

- Email: mohandevstack@gmail.com

## License

[OSL-3.0](LICENSE.txt)

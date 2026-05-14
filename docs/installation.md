# SnappyCommerce CartLink — Installation Guide

This module adds a cart loading endpoint to your Magento 2 store. When a customer follows a generated link, their pre-built cart is loaded automatically and they are redirected to the checkout page.

**Endpoint:** `https://your-store.com/cart/load?id=<masked_cart_id>`

---

## Requirements

- Magento 2.4.x
- PHP 8.1 or higher
- SSH access to the server

---

## Installation

### Step 1 — Get the module

**Option A — Clone (recommended)**

From your Magento root directory, run:

```bash
git clone https://github.com/tukpot/snappy-commerce-magento-checkout.git app/code/SnappyCommerce/CartLink
```

To update the module in the future:

```bash
cd app/code/SnappyCommerce/CartLink && git pull
```

**Option B — Download zip**

**[Download SnappyCommerce_CartLink.zip](https://raw.githubusercontent.com/tukpot/snappy-commerce-magento-checkout/main/SnappyCommerce_CartLink.zip)**

Upload the zip to your Magento root directory and extract it:

```bash
unzip SnappyCommerce_CartLink.zip
```

---

Either way, the module will be placed at:

```
<magento-root>/
└── app/
    └── code/
        └── SnappyCommerce/
            └── CartLink/
                ├── Controller/
                │   └── Load/
                │       └── Index.php
                ├── etc/
                │   ├── frontend/
                │   │   └── routes.xml
                │   └── module.xml
                ├── composer.json
                └── registration.php
```

### Step 2 — Enable the module

From the Magento root directory, run:

```bash
bin/magento module:enable SnappyCommerce_CartLink
bin/magento setup:upgrade
bin/magento cache:clean
```

If your store runs in production mode, also run:

```bash
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
```

### Step 3 — Verify the installation

Open the following URL in a browser (replace `your-store.com` with your domain):

```
https://your-store.com/cart/load
```

You should be redirected to the store homepage where the message **"Invalid cart link."** will appear in the notification area at the top of the page — this confirms the module is active and responding correctly.

---

## How It Works

When Snappy Commerce generates a checkout link for a customer, it uses the cart ID returned by the Magento REST API to build a URL in the format:

```
https://your-store.com/cart/load?id=<masked_cart_id>
```

> **Note:** The `<masked_cart_id>` is the alphanumeric Masked Quote ID used by Magento's REST API (e.g. `PGS6bgwqRApnt3umlHj9xB0eEC4KATBl`), not the numeric internal entity ID.

When the customer opens the link:

1. The module loads the pre-built cart into their session.
2. The customer is redirected directly to the checkout page with the cart ready.
3. If the customer is already logged in, their existing cart is merged with the incoming one.

---

## Uninstallation

To remove the module:

```bash
bin/magento module:disable SnappyCommerce_CartLink
bin/magento setup:upgrade
bin/magento cache:clean
rm -rf <magento-root>/app/code/SnappyCommerce/CartLink
```

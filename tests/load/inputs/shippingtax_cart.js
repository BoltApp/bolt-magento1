import { address } from "./address.js";
import { create_test_item } from "./test_item.js";

export function create_shippingtax_cart ( order_reference ) {
    return {
        "order_reference": order_reference,
        "display_id": "100044865|" +   order_reference,
        "currency": "USD",
        "total_amount": 90000,
        "tax_amount": 0,
        "billing_address": address,
        "billing_address_id": null,
        "items": [
            create_test_item( false ),
        ],
        "shipments": null,
        "discounts": 0,
        "discount_code": "",
        "order_description": null,
        "transaction_reference": null,
        "cart_url": null,
        "is_shopify_hosted_checkout": false
    };
}
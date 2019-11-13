import { create_amount } from "./amount.js";
import { address } from "./address.js";
import { create_test_item } from "./test_item.js";
import { shipment } from "./shipment.js";

export function create_preauth_cart ( order_reference ) {
    return {
        "order_reference": order_reference,
        "display_id": "100044865|" + order_reference,
        "currency": {
            "currency": "USD",
            "currency_symbol": "$"
        },
        "subtotal_amount": create_amount(90000),
        "total_amount": create_amount(97925),
        "tax_amount": create_amount(7425),
        "shipping_amount": create_amount(500),
        "discount_amount": create_amount(0),
        "billing_address": address,
        "items": [ create_test_item(true) ],
        "shipments": [ shipment ],
        "discounts": []
    };
}
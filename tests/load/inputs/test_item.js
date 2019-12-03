import { create_amount } from "./amount.js";
import { URL } from "../config.js";

export function create_test_item ( is_pre_auth ) {
    return {
        "reference": "111667",
        "name": "Test Product 01",
        "description": "Test Product 01",
        "options": null,
        "total_amount": is_pre_auth ? create_amount(90000) : 90000,
        "unit_price": is_pre_auth ? create_amount(90000) : 90000,
        "tax_amount": is_pre_auth ? create_amount(0) : 0,
        "quantity": 1,
        "uom": null,
        "upc": null,
        "sku": "Test-Product-01",
        "isbn": null,
        "brand": null,
        "manufacturer": null,
        "category": null,
        "tags": null,
        "properties": [],
        "color": null,
        "size": null,
        "weight": null,
        "weight_unit": null,
        "image_url": URL + "/media/catalog/product/cache/1/thumbnail/9df78eab33525d08d6e5fb8d27136e95/images/catalog/product/placeholder/thumbnail.jpg",
        "details_url": null,
        "taxable": true,
        "tax_code": null,
        "type": "unknown"
    };

}
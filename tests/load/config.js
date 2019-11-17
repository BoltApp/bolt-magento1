import crypto from "k6/crypto";

// The constants below should be replaced with the
// values from the store you want to load test

// URL to the store
export const URL = "http://18.205.119.113";

// Path from URL to the Cart Data Load Test Webhook
export const CART_PATH = "/boltpay/cartDataLoadTest";

// Path from URL to the Shipping and Tax Webhook
export const SHIPPING_PATH = "/boltpay/shipping";

// Path from URL to the PreAuth Webhook
export const PREAUTH_PATH = "/boltpay/api/create_order";

// Store's Signing Secret
const SIGNING_SECRET = "f527a8972d13c1ecdc31fd45a47054a249fe058b5bede0fc652dec986c95bfea";

export function create_header( data ) {
    const hmac = crypto.hmac( 'sha256', SIGNING_SECRET, JSON.stringify( data ), 'base64' );
    return {
        "X-Bolt-Hmac-Sha256": hmac,
        "Content-Type": "application/json"
    };
}

import crypto from "k6/crypto";

export const URL = "http://107.22.129.12";
export const CART_PATH = "/boltpay/cartDataLoadTest";
export const SHIPPING_PATH = "/boltpay/shipping";
export const PREAUTH_PATH = "/boltpay/api/create_order";
const SIGNING_SECRET = "f527a8972d13c1ecdc31fd45a47054a249fe058b5bede0fc652dec986c95bfea";

export function create_header( data ) {
    const hmac = crypto.hmac( 'sha256', SIGNING_SECRET, JSON.stringify( data ), 'base64' );
    return {
        "X-Bolt-Hmac-Sha256": hmac,
        "Content-Type": "application/json"
    };
}

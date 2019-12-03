import http from "k6/http";
import { URL, CART_PATH, create_header } from "../config.js";

export function cart_data() {
    const cart_url = URL + CART_PATH;
    const cart = { "cart": [ { "id": 1 } ] };
    const header = create_header( cart );
    return http.post( cart_url, JSON.stringify( cart ), { headers: header } );
}

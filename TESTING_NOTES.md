# Testing Notes

Final testing scenarios for **Sistem Pengurusan Komisyen Affiliate TikTok**.

| Test case | Steps | Expected result | Actual result | Status |
|---|---|---|---|---|
| Admin login | Open `/login`, login with `admin@example.com` and password `password`. | Admin is redirected to `/admin/dashboard`. | Admin login successful and dashboard displayed. | Pass |
| Affiliate management | Login as Admin, open `Admin > Affiliate Management`, create Abu, then create Ali with Abu as upline. | Abu and Ali are created. Ali is linked to Abu as direct upline/downline hierarchy. Affiliate login users are created with default password `password`. | Abu and Ali created successfully. Ali appears under Abu as direct downline. | Pass |
| TikTok account management | Open Ali detail page, add TikTok accounts such as `ali_shop1` and `ali_shop2`. | TikTok accounts are saved under Ali. Username is normalized without `@`. Duplicate username is rejected. | Ali can have multiple TikTok accounts. Duplicate normalized username is not allowed. | Pass |
| CSV upload and order import | Upload sample TikTok CSV containing orders for Ali TikTok accounts. | Matching orders are imported into `tiktok_orders`. Unmatched creator usernames are skipped. Duplicate `Order ID` is skipped. | Orders for registered Ali TikTok accounts imported successfully. Unmatched and duplicate rows skipped. | Pass |
| Commission calculation using sample Ali CSV | Run commission calculation for the sample month/year after importing Ali sales RM5,000. | Ali personal commission = RM500. Abu overriding L1 = RM50. Total commission = RM550. | Ali sales RM5,000, Ali personal commission RM500, Abu overriding L1 RM50, total commission RM550. | Pass |
| Affiliate dashboard for Ali | Login as Ali affiliate, open `/affiliate/dashboard`. | Ali can view personal sales, TikTok accounts, commission breakdown, and recent orders. | Ali can view TikTok accounts and recent orders. Ali sales RM5,000 and personal commission RM500 are displayed. | Pass |
| Affiliate dashboard for Abu | Login as Abu affiliate, open `/affiliate/dashboard`. | Abu can view direct downline Ali and overriding commission from Ali sales. | Abu can view direct downline Ali and overriding commission RM50. | Pass |

## Summary

- Ali sales: **RM5,000**
- Ali personal commission: **RM500**
- Abu overriding L1: **RM50**
- Total commission: **RM550**
- CSV import only stores orders matched to registered TikTok accounts.
- Affiliate dashboards show sales, commission, TikTok accounts, downline, and recent orders based on available data.

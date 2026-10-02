# Customer orders progress

Plan: [11-customer-orders-plan.md](11-customer-orders-plan.md). Design: [12-customer-orders-design.md](12-customer-orders-design.md).

| Step | Work | Status | Notes |
|---|---|---|---|
| 0 | Commit prior work | Done 2026-10-02 | `2262a58` |
| 1 | Schema `016`, design | Done 2026-10-02 | Applied to `cidery_dev`; `5fab7df` |
| 2 | Orders screens | Done 2026-10-02 | See below |
| 3 | Packaging runs and shipping from orders | Next | |
| 4 | Spreadsheet import | | |
| 5 | Standing orders and forecasts | | |
| 6 | Projections | | |
| 7 | Order history report, dashboard tiles | | |
| 8 | Assistant tools and actions | | Manifest, `prompts.py` roles (add `sales`), records tools |
| 9 | Full test pass | | |

## Step 2: orders screens

- **Screens:** `/orders/` (open orders by default, "All statuses" shows every order; value column for owner and sales), `/orders/new` (`?customer=` prefills the customer and their destination), `/orders/{id}`, `/orders/{id}/edit` (draft and confirmed only).
- **Actions:** save (create/update with lines), confirm, cancel (needs a reason; refused once anything is shipped or in a packaging run), close (open lines become closed short). Events: `order_created`, `order_updated`, `order_confirmed`, `order_cancelled`, `order_closed`.
- **Lines** are saved in place by id, so lines that packaging runs or shipments point at keep their identity. Such a line cannot be removed, change format, or drop below what is shipped or in runs.
- **Line availability:** choosing a format sets its list price and shows released units on hand, units promised to other open orders, and the product's bulk volume.
- **History:** a new or draft order can be saved as "Already fulfilled outside the system": closed, counted as shipped, no stock or tax effect.
- **Prices:** `List price per unit` on the packaging configuration form (owner only, since that form needs the production role and only owner and sales see prices). Prices and values are hidden from other roles on every orders screen.
- **Role:** `sales` added to user roles; the Sales navigation group holds Customer orders.
- **Tabs:** Orders on the customer view (with New order) and the product view (`?tab=orders`).
- **Checked:** every action through HTTP as a sales user and as a viewer (403 on save and new), validation 422s, duplicate customer reference, conformance (`orders`, `packaging-configs`, `users`), 375px sweep of 10 screens with no problems.

**Test data left in the dev database:** user 10 "Sales Test" (role sales, no password, cannot sign in; used with action tokens), orders SO-00001 (closed), SO-00002 (history), SO-00003 (cancelled), and list prices $1.85 per can and $165 per half barrel on the two Hill Dry formats.

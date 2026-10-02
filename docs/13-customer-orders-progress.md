# Customer orders progress

Plan: [11-customer-orders-plan.md](11-customer-orders-plan.md). Design: [12-customer-orders-design.md](12-customer-orders-design.md).

| Step | Work | Status | Notes |
|---|---|---|---|
| 0 | Commit prior work | Done 2026-10-02 | `2262a58` |
| 1 | Schema `016`, design | Done 2026-10-02 | Applied to `cidery_dev`; `5fab7df` |
| 2 | Orders screens | Done 2026-10-02 | See below |
| 3 | Packaging runs and shipping from orders | Done 2026-10-02 | See below |
| 4 | Spreadsheet import | Done 2026-10-02 | See below |
| 5 | Standing orders and forecasts | Next | |
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

## Step 3: packaging and shipping from orders

- **Package** (`/orders/{id}/package`, and `/orders/package?format=` for every open line of a format): one card per format with each line's open units, units in draft runs, units covered by stock and units to package. Stock on hand is promised in due-date order, then order number, across all open orders. Each card proposes units (the need), a batch and vessel (oldest released batch with a vessel holding enough; else the oldest that holds enough; else the oldest), the output location and the run date. **Create Packaging Runs** makes one draft run per format, linked to the order lines in due order. Units above the need go to stock. Backflushed materials are set from the bill of materials; ABV, CO2 and explicit material lots are entered on the run before posting, as before. When no batch is ready, the card says so and offers **Plan production** (a production order prefilled with the product and volume).
- **Packaging queue** (`/orders/to-package`, Sales menu): every format with open order lines: open, on hand, to package, and the lines in due order, with **Package** when something is needed.
- **Ship** (order view, sales or compliance): drafts one removal from the bonded location holding the most of the order's units, FEFO by packaging date. Keg lots ship only in kegs recorded as filled with the lot. It ships what is on hand; the rest stays open. Destination and reference come from the order (taproom transfers go to the premises' tax-paid taproom; in-bond needs the customer's permit). One draft shipment per order at a time. Compliance reviews and posts it on the removal screen, unchanged.
- **Links:** removal lines map to order lines by format (oldest open line first) every time the removal is saved; reversals copy the order and line links, so shipped units net out. The removal view shows its customer order.
- **Status roll-up** (`order_status_changed`, logged): shipped when every open line has shipped in full; in fulfillment once anything is shipped, in a packaging run or drafted for shipping; confirmed otherwise. Refreshed on package, ship, and on removal save, post, reverse and delete, and packaging run delete and reverse.
- **Checked:** draft runs created and linked (48 cans for a 46 need, 1 keg), vessel capacity refused with 422, queue and per-format page, ship (54 cans; refused while a draft exists), removal edit keeps links, deleting drafts returns the order to confirmed. Posting and reversing a shipment, and a full shipment, were tested inside a rolled-back transaction: 54/100 shipped after posting, 0 after reversal, `shipped` when complete. Conformance (`orders`, `removals`, `packaging-runs`) and the 375px sweep pass.
- **Noticed:** finished keg lots L-261001-017 and L-261001-018 have units on hand but no keg recorded as filled with them, so Ship cannot pick kegs for them.

## Step 4: spreadsheet import

- **Library:** `phpoffice/phpspreadsheet` 3.10.8 (Composer reported no advisories). CSV, XLSX and XLS, first sheet, up to 5 MB and 5,000 rows; Excel date cells are read as dates.
- **Screens:** `/orders/import` (upload, CSV template download, past imports; also Import on the orders list and Import orders in the Sales menu for owner and sales) and `/orders/import/{n}` (preview, columns, result).
- **Mapping:** columns are matched by header name (many common names: "Order #", "Ship Date", "Qty", "SKU", ...) or by the last import's mapping; the Columns card changes any of them and re-runs the preview.
- **Rows:** grouped into orders by customer and order reference (or, without a reference, customer and dates). Formats match by configuration name or finished item code, narrowed by a Product column. Dates in Y-m-d, m/d/Y, d-Mon-Y and "May 1, 2026" forms. A blank status means history when due before today, otherwise the chosen upcoming status; a Status column can say history/closed/shipped, confirmed/open, draft, or cancelled (skipped). An order whose customer reference already exists is skipped, so re-uploading a file imports nothing twice.
- **Commit:** one transaction and one `orders_imported` event; new customers are created (kind "other") when allowed; rows with errors block the import unless the user chooses to skip them, and are kept on the import with their reasons. **Undo** deletes the import's orders while none has packaging runs, shipments or a production plan; customers it created stay. **Discard** sets a preview aside.
- **Checked:** a CSV with history, upcoming, draft, new-customer, cancelled, duplicate and invalid rows (preview, refusal without skip, commit with skip, re-upload skips everything); an XLSX with date cells and unrecognised headers fixed through the Columns card; undo; viewer gets 403; conformance and the 375px sweep pass. Test imports were undone or discarded and the test customer deleted.

**Test data left in the dev database:** user 10 "Sales Test" (role sales, no password, cannot sign in; used with action tokens), orders SO-00001 (closed), SO-00002 (history), SO-00003 and SO-00004 (cancelled), and list prices $1.85 per can and $165 per half barrel on the two Hill Dry formats.

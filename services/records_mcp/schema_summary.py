"""Schema summary for records_search, generated at startup from information_schema as the
read-only role, so it lists exactly what the role can read (auth tables and the auth columns
of app.users never appear)."""
from __future__ import annotations

import psycopg
from psycopg.rows import dict_row

from common import config

from . import queries as Q

KEY_VIEWS = {
    "v_lot_balances": "on hand / allocated / available per item, lot, location with quality status and expiry",
    "v_item_stock": "item totals with qty_on_order and below_reorder_point",
    "v_inventory_valuation": "value by item_class, tax_state, premises",
    "v_vessel_board": "current occupant of every vessel (tank board)",
    "v_batch_costs": "material, packaging, overhead, total and variance per batch",
    "v_batch_material_costs": "consumptions of a batch at lot cost",
    "v_batch_stage_yields": "per-stage actual vs expected loss",
    "v_press_run_yields": "press yields by variety, gal_per_ton, gal_per_bushel",
    "v_finished_stock": "finished lots with units on hand by product and package",
    "v_keg_fleet": "kegs with holder and days since moved",
    "v_open_po_lines": "open PO lines with expected_on and overdue",
    "v_supplier_performance": "receipts, late receipts, short and damaged lines per supplier",
}
NOTES = ("Functions: app.trace_forward(lot_id) and app.trace_backward(batch_id) return (level, kind, id, label, detail jsonb); "
         "app.derive_tax_class(beverage, abv, co2_g_100ml, fruit_share_pct, other_fruit, flavoring). "
         "Polymorphic targets: readings, loss_events, sensory_records, release_decisions use (target_kind, target_id); "
         "vessel_occupancies use (occupant_kind, occupant_id); lots.source_kind/source_id; inventory_transactions.reference_kind/reference_id. "
         "finished_lots.lot_id = lots.id. users: only id, display_name, role, status are readable.")


def build() -> str:
    with psycopg.connect(config.dsn("records"), row_factory=dict_row) as conn:
        rows = conn.execute(Q.SCHEMA_COLUMNS).fetchall()
    tables = [r for r in rows if r["table_type"] == "BASE TABLE"]
    views = [r for r in rows if r["table_type"] == "VIEW"]
    lines = ["Tables:"] + [f"{r['table_name']}({r['columns']})" for r in tables]
    lines += ["Views:"] + [f"{r['table_name']}({r['columns']})" + (f" -- {KEY_VIEWS[r['table_name']]}" if r["table_name"] in KEY_VIEWS else "") for r in views]
    lines.append(NOTES)
    return "\n".join(lines)


if __name__ == "__main__":
    text = build()
    print(text)
    print(f"\n{len(text)} characters")

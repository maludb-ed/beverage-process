from common.sql_guard import validate_select

ok = ["select 1", "SELECT * FROM app.lots", "with x as (select 1) select * from x", "select 'drop table' as t"]
bad = ["delete from app.lots", "select 1; select 2", "select pg_sleep(10)", "select * into t from app.lots", "-- hi\nselect 1", "update x set y=1", "select set_config('a','b',false)"]
for q in ok:
    validate_select(q)
for q in bad:
    try:
        validate_select(q)
    except ValueError:
        continue
    raise SystemExit(f"should have failed: {q}")
print("sql_guard ok")

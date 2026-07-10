import pyarrow as pa
import pyarrow.parquet as pq
import os

table = pa.table({
    'name': pa.array(['alice', 'bob', 'charlie'], type=pa.string()),
    'age': pa.array([25, 30, 35], type=pa.int32()),
    'score': pa.array([0.8, 0.9, 0.7], type=pa.float64()),
})

fixture_dir = os.path.join(os.path.dirname(__file__), '..', 'packages', 'dataframe', 'tests', 'Unit', 'IO', 'fixtures')
os.makedirs(fixture_dir, exist_ok=True)

path = os.path.join(fixture_dir, 'simple.parquet')
pq.write_table(table, path, compression='NONE')

print(f'Written: {path}')
print(f'Size: {os.path.getsize(path)} bytes')
print(f'Rows: {table.num_rows}')
print(f'Columns: {table.column_names}')

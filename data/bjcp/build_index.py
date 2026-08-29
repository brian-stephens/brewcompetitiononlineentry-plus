"""Merge beer, mead, and cider indexes into one style_index.json."""
import json

all_index = []
for fname in ["style_index_beer.json", "style_index_mead.json", "style_index_cider.json"]:
    with open(fname, encoding="utf-8") as f:
        all_index.extend(json.load(f))

# Sort: beer (numeric codes), then mead (M*), then cider (C*)
def sort_key(entry):
    code = entry["code"]
    if code[0] == 'M':
        return (1, code)
    elif code[0] == 'C':
        return (2, code)
    else:
        # Numeric sort for beer
        m = __import__('re').match(r'^(\d+)([A-Z]\d*)$', code)
        if m:
            return (0, f"{int(m.group(1)):03d}{m.group(2)}")
        return (0, code)

all_index.sort(key=sort_key)

with open("style_index.json", "w", encoding="utf-8") as f:
    json.dump(all_index, f, ensure_ascii=False, indent=2)

print(f"Combined index: {len(all_index)} styles")
print(f"  Beer:  {sum(1 for e in all_index if e['type']=='beer')}")
print(f"  Mead:  {sum(1 for e in all_index if e['type']=='mead')}")
print(f"  Cider: {sum(1 for e in all_index if e['type']=='cider')}")

"""Quick probe to understand PDF text structure."""
import fitz, sys

path = sys.argv[1]
doc = fitz.open(path)
for page_num, page in enumerate(doc):
    text = page.get_text()
    lines = text.split('\n')
    for line in lines:
        line = line.strip()
        if line:
            print(f"P{page_num+1:3d} | {line[:120]}")

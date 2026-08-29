"""Quick probe to understand DOCX heading/paragraph structure."""
import docx, sys

path = sys.argv[1]
doc = docx.Document(path)
for i, p in enumerate(doc.paragraphs[:200]):
    if p.style.name.startswith('Heading') or p.text.strip():
        print(f"{i:4d} | {p.style.name:30s} | {p.text[:100]}")

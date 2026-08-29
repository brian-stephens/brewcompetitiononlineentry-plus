"""
Extract BJCP 2025 Cider Style Guidelines from PDF into structured JSON.
"""
import json, re, sys
import fitz  # PyMuPDF

STYLE_CODE_RE = re.compile(r'^(C\d[A-Z](?:\d+)?)\.\s+(.+)$')
CATEGORY_CODE_RE = re.compile(r'^(C\d)\.\s+(?!Common Cider|Heirloom|English|French|Spanish|New England|Apple|Ice|Fire|Fruit|Spiced|Experimental|Perry|Strong)(.+)$')
# Simpler: match category headings that are ALL CAPS or match known category names
CATEGORY_HEADING_RE = re.compile(r'^(C\d)\.\s+([A-Z][A-Z ]+)$')

SECTION_LABELS = [
    "Overall Impression",
    "Aroma and Flavor",
    "Aroma",
    "Flavor",
    "Appearance",
    "Mouthfeel",
    "Comments",
    "History",
    "Characteristic Ingredients",
    "Ingredients",
    "Style Comparison",
    "Entry Instructions",
    "Vital Statistics",
    "Commercial Examples",
    "Varieties",
    "Tags",
]
SECTION_KEYS = {
    "Overall Impression": "overall_impression",
    "Aroma and Flavor": "aroma_and_flavor",
    "Aroma": "aroma",
    "Flavor": "flavor",
    "Appearance": "appearance",
    "Mouthfeel": "mouthfeel",
    "Comments": "comments",
    "History": "history",
    "Characteristic Ingredients": "characteristic_ingredients",
    "Ingredients": "characteristic_ingredients",
    "Style Comparison": "style_comparison",
    "Entry Instructions": "entry_instructions",
    "Vital Statistics": "vital_statistics",
    "Commercial Examples": "commercial_examples",
    "Varieties": "varieties",
    "Tags": "tags",
}

_label_pattern = "|".join(re.escape(l) for l in sorted(SECTION_LABELS, key=len, reverse=True))
LABEL_START_RE = re.compile(r'^(' + _label_pattern + r'):\s*(.*)$')

# Junk lines to skip
JUNK_RE = re.compile(r'^(BJCP Cider Style Guidelines|BEER JUDGE CERTIFICATION PROGRAM|Contents$|\d+$)', re.IGNORECASE)


def normalize(text):
    return (text
            .replace('\u2013', '-').replace('\u2014', '-')
            .replace('\u2019', "'").replace('\u2018', "'")
            .replace('\u201c', '"').replace('\u201d', '"')
            .replace('\ufffd', "'")
            .replace('\u2022', '-')
            )


def parse_style_block(raw_lines):
    """
    Given the raw lines for a style body (everything after the heading line),
    merge soft-wrapped lines and split into labeled sections.
    First chunk of text before any label becomes 'description'.
    """
    sections = {}
    current_label = "description"
    current_parts = []

    def flush():
        if current_parts:
            text = " ".join(p.strip() for p in current_parts if p.strip())
            text = re.sub(r'\s+', ' ', text).strip()
            if text:
                sections[current_label] = text

    for raw in raw_lines:
        line = normalize(raw).strip()
        if not line:
            continue
        m = LABEL_START_RE.match(line)
        if m:
            flush()
            current_parts = []
            current_label = SECTION_KEYS[m.group(1)]
            rest = m.group(2).strip()
            if rest:
                current_parts.append(rest)
        else:
            current_parts.append(line)

    flush()
    return sections


def extract_pdf(path):
    doc = fitz.open(path)

    # Collect all raw lines, page by page, skipping junk
    all_lines = []
    for page in doc:
        for line in page.get_text().split('\n'):
            norm = normalize(line.strip())
            if not norm:
                continue
            # Skip running headers/footers
            if JUNK_RE.match(norm):
                continue
            # Skip table of contents dots
            if re.match(r'^[A-Z].{5,}\.{5,}', norm):
                continue
            all_lines.append(norm)

    styles_out = {}
    index = []

    current_category_name = None
    current_category_desc_lines = []
    current_style_code = None
    current_style_name = None
    current_style_lines = []

    def flush_style():
        nonlocal current_style_code, current_style_name, current_style_lines
        if not current_style_code:
            return
        sections = parse_style_block(current_style_lines)
        obj = {
            "code": current_style_code,
            "name": current_style_name,
            "category": current_category_name or "",
            "category_description": " ".join(current_category_desc_lines).strip(),
            "guide": "BJCP2025",
            "type": "cider",
            "sections": sections,
        }
        styles_out[current_style_code] = obj
        index.append({
            "code": current_style_code,
            "name": current_style_name,
            "category": current_category_name or "",
            "guide": "BJCP2025",
            "type": "cider",
        })
        current_style_code = None
        current_style_name = None
        current_style_lines = []

    # State machine
    # Scan for category headings (ALL-CAPS lines like "C1. TRADITIONAL CIDER")
    # and style headings (like "C1A. Common Cider")
    i = 0
    while i < len(all_lines):
        line = all_lines[i]

        # Check for category heading (uppercase)
        cat_m = re.match(r'^(C\d)\.\s+([A-Z][A-Z\s]+)$', line)
        style_m = STYLE_CODE_RE.match(line)

        if cat_m and not style_m:
            flush_style()
            current_category_name = cat_m.group(2).strip().title()
            current_category_desc_lines = []
            i += 1
            # Collect category description lines until a style heading
            while i < len(all_lines):
                nl = all_lines[i]
                if STYLE_CODE_RE.match(nl) or re.match(r'^(C\d)\.\s+([A-Z][A-Z\s]+)$', nl):
                    break
                current_category_desc_lines.append(nl)
                i += 1
            continue

        if style_m:
            flush_style()
            current_style_code = style_m.group(1)
            current_style_name = style_m.group(2).strip()
            current_style_lines = []
            i += 1
            continue

        if current_style_code:
            current_style_lines.append(line)
        elif current_category_name:
            current_category_desc_lines.append(line)

        i += 1

    flush_style()
    return styles_out, index


if __name__ == "__main__":
    src = sys.argv[1] if len(sys.argv) > 1 else r"source/2025_Guidelines_Cider.pdf"
    out_styles = sys.argv[2] if len(sys.argv) > 2 else r"bjcp_2025_cider.json"
    out_index = sys.argv[3] if len(sys.argv) > 3 else r"style_index_cider.json"

    styles, index = extract_pdf(src)
    with open(out_styles, "w", encoding="utf-8") as f:
        json.dump({"styles": styles}, f, ensure_ascii=False, indent=2)
    with open(out_index, "w", encoding="utf-8") as f:
        json.dump(index, f, ensure_ascii=False, indent=2)
    print(f"Extracted {len(styles)} cider styles.")
    for code in styles:
        print(f"  {code}: {styles[code]['name']}")

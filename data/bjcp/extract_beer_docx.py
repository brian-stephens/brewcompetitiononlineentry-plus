"""
Extract BJCP 2021 Beer Style Guidelines from DOCX into structured JSON.
Styles are stored in paragraphs using Word style names:
  Heading 1         = Category (e.g. "1. Standard American Beer")
  Style Intro Last  = Category description
  Heading 2 first   = Style heading (e.g. "1A. American Light Lager")
  Heading 2         = Category or Style heading
  Style Body        = Section content, prefixed with label (e.g. "Overall Impression: ...")
  Specs / Specs Last= Vital statistics rows
"""
import json, re, sys
import docx

SECTION_LABELS = [
    "Overall Impression", "Aroma", "Appearance", "Flavor", "Mouthfeel",
    "Comments", "History", "Characteristic Ingredients", "Style Comparison",
    "Entry Instructions", "Commercial Examples", "Tags",
]
SECTION_KEYS = {
    "Overall Impression": "overall_impression",
    "Aroma": "aroma",
    "Appearance": "appearance",
    "Flavor": "flavor",
    "Mouthfeel": "mouthfeel",
    "Comments": "comments",
    "History": "history",
    "Characteristic Ingredients": "characteristic_ingredients",
    "Style Comparison": "style_comparison",
    "Entry Instructions": "entry_instructions",
    "Commercial Examples": "commercial_examples",
    "Tags": "tags",
}

STYLE_HEADING_RE = re.compile(r'^(\d{1,2}[A-Z]\d*)\.\s+(.+)$', re.IGNORECASE)
CATEGORY_HEADING_RE = re.compile(r'^(\d{1,2})\.\s+(.+)$')
VITALS_ROW_RE = re.compile(r'(OG|FG|IBUs?|SRM|ABV)[\s:]+')


def parse_section(text):
    """Split a 'Style Body' paragraph into (key, content) or None."""
    for label in SECTION_LABELS:
        if text.startswith(label + ":"):
            content = text[len(label) + 1:].strip()
            return SECTION_KEYS[label], content
    return None


def parse_docx(path):
    doc = docx.Document(path)
    styles_out = {}
    index = []
    categories = {}

    current_category_num = None
    current_category_name = None
    current_category_desc_parts = []
    current_style_code = None
    current_style_name = None
    current_sections = {}
    current_vitals_parts = []

    def flush_style():
        nonlocal current_style_code, current_style_name, current_sections, current_vitals_parts
        if current_style_code:
            vitals_text = " ".join(current_vitals_parts).strip()
            vitals_text = re.sub(r'\s+', ' ', vitals_text)
            if vitals_text:
                current_sections["vital_statistics"] = vitals_text
            # Determine type from category prefix
            g = current_style_code
            if g[0] == 'M':
                t = "mead"
            elif g[0] == 'C':
                t = "cider"
            else:
                t = "beer"
            cat_desc = " ".join(current_category_desc_parts).strip()
            obj = {
                "code": current_style_code,
                "name": current_style_name,
                "category": current_category_name or "",
                "category_description": cat_desc,
                "guide": "BJCP2021",
                "type": t,
                "sections": current_sections,
            }
            styles_out[current_style_code] = obj
            index.append({
                "code": current_style_code,
                "name": current_style_name,
                "category": current_category_name or "",
                "guide": "BJCP2021",
                "type": t,
            })
        current_style_code = None
        current_style_name = None
        current_sections = {}
        current_vitals_parts = []

    for para in doc.paragraphs:
        sname = para.style.name
        text = para.text.strip()
        if not text:
            continue

        # Category heading
        if sname == "Heading 1":
            flush_style()
            m = CATEGORY_HEADING_RE.match(text)
            if m:
                current_category_num = m.group(1)
                current_category_name = m.group(2).strip()
                current_category_desc_parts = []
            continue

        # Category intro (description)
        if sname in ("Style Intro Last", "Style Intro"):
            current_category_desc_parts.append(text)
            continue

        # Style heading
        if sname in ("Heading 2 first", "Heading 2"):
            m = STYLE_HEADING_RE.match(text)
            if m:
                flush_style()
                current_style_code = m.group(1)
                current_style_name = m.group(2).strip()
            continue

        # Style body sections
        if sname == "Style Body":
            result = parse_section(text)
            if result:
                key, content = result
                if key in current_sections:
                    current_sections[key] = current_sections[key] + " " + content
                else:
                    current_sections[key] = content
            continue

        # Vital statistics rows
        if sname in ("Specs", "Specs Last"):
            # Strip redundant "Vital Statistics:" prefix sometimes included
            cleaned = re.sub(r'^Vital Statistics:\s*', '', text)
            current_vitals_parts.append(cleaned)
            continue

    flush_style()
    return styles_out, index


if __name__ == "__main__":
    src = sys.argv[1] if len(sys.argv) > 1 else r"source/2021_Guidelines_Beer.docx"
    out_styles = sys.argv[2] if len(sys.argv) > 2 else r"bjcp_2021_beer.json"
    out_index = sys.argv[3] if len(sys.argv) > 3 else r"style_index_beer.json"

    styles, index = parse_docx(src)
    with open(out_styles, "w", encoding="utf-8") as f:
        json.dump({"styles": styles}, f, ensure_ascii=False, indent=2)
    with open(out_index, "w", encoding="utf-8") as f:
        json.dump(index, f, ensure_ascii=False, indent=2)
    print(f"Extracted {len(styles)} beer styles.")

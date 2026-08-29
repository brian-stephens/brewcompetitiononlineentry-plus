"""
Extract BJCP 2015 Mead Style Guidelines from DOCX into structured JSON.
Mead DOCX structure:
  Heading 1    = Category (e.g. "M1. Traditional Mead")
  Style Intro  = Category description
  Heading 2    = Style heading (e.g. "M1A. Dry Mead")
  Style Body   = Section content prefixed with label
  Normal       = Vital statistics rows (appear after "Vital Statistics:" Style Body line)
"""
import json, re, sys
import docx

SECTION_LABELS = [
    "Overall Impression", "Aroma", "Appearance", "Flavor", "Mouthfeel",
    "Comments", "History", "Ingredients", "Characteristic Ingredients",
    "Style Comparison", "Entry Instructions", "Commercial Examples", "Tags",
    "Vital Statistics",
]
SECTION_KEYS = {
    "Overall Impression": "overall_impression",
    "Aroma": "aroma",
    "Appearance": "appearance",
    "Flavor": "flavor",
    "Mouthfeel": "mouthfeel",
    "Comments": "comments",
    "History": "history",
    "Ingredients": "characteristic_ingredients",
    "Characteristic Ingredients": "characteristic_ingredients",
    "Style Comparison": "style_comparison",
    "Entry Instructions": "entry_instructions",
    "Commercial Examples": "commercial_examples",
    "Tags": "tags",
    "Vital Statistics": "vital_statistics",
}

STYLE_HEADING_RE = re.compile(r'^(M\d+[A-Z](?:\d+)?)\.\s+(.+)$')
CATEGORY_HEADING_RE = re.compile(r'^(M\d+)\.\s+(.+)$')


def parse_section(text):
    for label in SECTION_LABELS:
        if text.startswith(label + ":"):
            content = text[len(label) + 1:].strip()
            return SECTION_KEYS[label], content
    return None


def parse_docx(path):
    doc = docx.Document(path)
    styles_out = {}
    index = []

    current_category_name = None
    current_category_desc_parts = []
    current_style_code = None
    current_style_name = None
    current_sections = {}
    in_vitals = False

    def flush_style():
        nonlocal current_style_code, current_style_name, current_sections, in_vitals
        if current_style_code:
            obj = {
                "code": current_style_code,
                "name": current_style_name,
                "category": current_category_name or "",
                "category_description": " ".join(current_category_desc_parts).strip(),
                "guide": "BJCP2015",
                "type": "mead",
                "sections": current_sections,
            }
            styles_out[current_style_code] = obj
            index.append({
                "code": current_style_code,
                "name": current_style_name,
                "category": current_category_name or "",
                "guide": "BJCP2015",
                "type": "mead",
            })
        current_style_code = None
        current_style_name = None
        current_sections = {}
        in_vitals = False

    for para in doc.paragraphs:
        sname = para.style.name
        text = para.text.strip()
        if not text:
            continue

        if sname == "Heading 1":
            flush_style()
            m = CATEGORY_HEADING_RE.match(text)
            if m:
                current_category_name = m.group(2).strip()
                current_category_desc_parts = []
            else:
                current_category_name = text
                current_category_desc_parts = []
            in_vitals = False
            continue

        if sname in ("Style Intro", "Style Intro Last"):
            current_category_desc_parts.append(text)
            in_vitals = False
            continue

        if sname == "Heading 2":
            m = STYLE_HEADING_RE.match(text)
            if m:
                flush_style()
                current_style_code = m.group(1)
                current_style_name = m.group(2).strip()
            in_vitals = False
            continue

        if sname == "Style Body":
            result = parse_section(text)
            if result:
                key, content = result
                if key == "vital_statistics":
                    # Content may be empty or partial; accumulate
                    in_vitals = True
                    if content:
                        current_sections["vital_statistics"] = content
                    else:
                        current_sections["vital_statistics"] = ""
                else:
                    in_vitals = False
                    if key in current_sections:
                        current_sections[key] = current_sections[key] + " " + content
                    else:
                        current_sections[key] = content
            else:
                # Continuation of previous section — append to last key if possible
                in_vitals = False
            continue

        # Normal paragraphs after "Vital Statistics:" belong to vitals
        if sname == "Normal" and in_vitals and current_style_code:
            existing = current_sections.get("vital_statistics", "")
            current_sections["vital_statistics"] = (existing + " " + text).strip()
            continue

    flush_style()
    return styles_out, index


if __name__ == "__main__":
    src = sys.argv[1] if len(sys.argv) > 1 else r"source/2015_Guidelines_Mead.docx"
    out_styles = sys.argv[2] if len(sys.argv) > 2 else r"bjcp_2015_mead.json"
    out_index = sys.argv[3] if len(sys.argv) > 3 else r"style_index_mead.json"

    styles, index = parse_docx(src)
    with open(out_styles, "w", encoding="utf-8") as f:
        json.dump({"styles": styles}, f, ensure_ascii=False, indent=2)
    with open(out_index, "w", encoding="utf-8") as f:
        json.dump(index, f, ensure_ascii=False, indent=2)
    print(f"Extracted {len(styles)} mead styles.")

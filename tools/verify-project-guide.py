"""Verify the generated guide and add PDF navigation bookmarks. Requires PyMuPDF."""

import json
import pathlib
import re

import pymupdf


root = pathlib.Path(__file__).resolve().parent.parent
path = root / "docs/Ledger-Project-Guide.pdf"
document = pymupdf.open(path)
texts = [page.get_text() for page in document]
html = (root / "docs/project-guide.html").read_text(encoding="utf-8")
tables = re.findall(r'<div class="schema-block"><h2>(.*?)</h2>', html)
full_text = "\n".join(texts)
assert all(table in full_text for table in tables), "A schema table is missing from the PDF."
assert all(len(text.strip()) >= 180 for text in texts), "An empty or orphaned page needs review."
for page in document:
    for block in page.get_text("dict")["blocks"]:
        for line in block.get("lines", []):
            for span in line.get("spans", []):
                x0, y0, x1, y1 = span["bbox"]
                assert x0 >= 0 and y0 >= 0 and x1 <= page.rect.width + 1 and y1 <= page.rect.height + 1, "Text is clipped."

headings = [
    "Contents and key terms", "From a button to a database row", "Where each action stores data",
    "Company creation and isolation", "Users, roles and permissions", "Customers and suppliers",
    "Sales invoices and receipts", "Where a purchase is stored", "Expenses and categories",
    "Credit notes and refunds", "Accounts, journals and reversals", "Bank statements and reconciliation",
    "Dashboard and financial reports", "Attachments, exports, queues and audit",
    "Taxes, currencies and period locks", "Subscriptions and platform plans",
    "Follow a purchase through the tables", "Inspecting and running the project",
    "What exists and what remains", "Complete database reference",
]
normalized = [" ".join(text.split()) for text in texts]
bookmarks = []
for heading in headings:
    pages = [index + 1 for index, text in enumerate(normalized) if heading in text]
    assert pages, f"Missing workflow heading: {heading}"
    bookmarks.append([1, heading, pages[0]])
document.set_toc(sorted(bookmarks, key=lambda item: item[2]))
temporary = path.with_suffix(".verified.pdf")
document.save(temporary, garbage=4, deflate=True)
page_count = len(document)
document.close()
temporary.replace(path)
print(json.dumps({"pages": page_count, "documented_tables": len(tables), "bookmarks": len(bookmarks), "bytes": path.stat().st_size, "verification": "passed"}))

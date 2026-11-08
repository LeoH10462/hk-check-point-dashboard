"""Render the editable technical report on Windows with ReportLab."""
from pathlib import Path
import os
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer

root = Path(__file__).resolve().parents[1]
font = Path(os.environ.get("BORDERFLOW_PDF_FONT", r"C:\Windows\Fonts\msjh.ttc"))
pdfmetrics.registerFont(TTFont("ReportCJK", str(font), subfontIndex=0))
blue = colors.HexColor("#10364e")
title = ParagraphStyle("Title", fontName="ReportCJK", fontSize=24, leading=34, textColor=blue, wordWrap="CJK")
heading = ParagraphStyle("Heading", parent=title, fontSize=15, leading=20, spaceBefore=10, spaceAfter=4, keepWithNext=True)
body = ParagraphStyle("Body", parent=title, fontSize=10.5, leading=16, textColor=colors.HexColor("#243948"), spaceAfter=4)

items = []
for line in (root / "docs/report.md").read_text(encoding="utf-8").splitlines():
    if line.startswith("# "):
        items.extend([Paragraph(escape(line[2:]), title), Spacer(1, 8)])
    elif line.startswith("## "):
        items.append(Paragraph(escape(line[3:]), heading))
    elif line.startswith("|"):
        cells = [cell.strip() for cell in line.strip("|").split("|")]
        if cells[0] not in ("測試", "---"):
            items.append(Paragraph(escape(cells[0] + "　｜　" + cells[1]), body))
    elif line:
        items.append(Paragraph(escape(line), body))


def footer(canvas, doc):
    canvas.setFillColor(blue)
    canvas.setFont("Helvetica", 9)
    canvas.drawString(46, 28, "BORDERFLOW  /  DATABASE APPLICATION")
    canvas.drawRightString(doc.pagesize[0] - 46, 28, str(doc.page))


SimpleDocTemplate(str(root / "docs/BorderFlow-report.pdf"), title="BorderFlow Technical Report",
                  leftMargin=46, rightMargin=46, topMargin=40, bottomMargin=45).build(
                      items, onFirstPage=footer, onLaterPages=footer
                  )

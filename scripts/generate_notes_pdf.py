#!/usr/bin/env python3
"""
📄 CCPA — Générateur de fiche de notes PDF officielle
Collège Catholique Père Aupiais — Cotonou, Bénin
Usage : python3 generate_notes_pdf.py <chemin_json>
"""
import sys, json, os, datetime
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import cm
from reportlab.platypus import (
    SimpleDocTemplate, Table, TableStyle,
    Paragraph, Spacer, HRFlowable, Image as RLImage
)
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT

# ── PALETTE CCPA ──────────────────────────────────────────────────────
NAVY  = colors.HexColor('#003366')
GOLD  = colors.HexColor('#C8A951')
LIGHT = colors.HexColor('#EEF3FA')
GRAY  = colors.HexColor('#F7F7F7')
WHITE = colors.white


def mention(note):
    if note is None:  return "Absent",    "#888888"
    n = float(note)
    if n >= 18: return "Excellent",    "#005500"
    if n >= 16: return "Très Bien",    "#1a7a1a"
    if n >= 14: return "Bien",         "#2e6e2e"
    if n >= 12: return "Assez Bien",   "#7a5000"
    if n >= 10: return "Passable",     "#b85c00"
    return             "Insuffisant",  "#cc0000"


def build(data: dict, logo_path: str | None, output: str):
    doc = SimpleDocTemplate(
        output, pagesize=A4,
        leftMargin=1.5*cm, rightMargin=1.5*cm,
        topMargin=1.5*cm, bottomMargin=2*cm,
        title=f"Fiche de Notes — {data.get('classe','')} — {data.get('matiere','')}",
        author="Collège Catholique Père Aupiais",
    )
    base  = getSampleStyleSheet()
    story = []

    def S(name, **kw):
        return ParagraphStyle(name, parent=base['Normal'], **kw)

    # ── EN-TÊTE ──────────────────────────────────────────────────────
    logo_cell = Paragraph(
        "<font size='20' color='#003366'>✝</font>",
        S('x', alignment=TA_CENTER)
    )
    if logo_path and os.path.exists(logo_path):
        try:
            logo_cell = RLImage(logo_path, width=2*cm, height=2*cm)
        except Exception:
            pass

    hdr = Table([[
        Paragraph(
            "<b>République du Bénin</b><br/>"
            "Ministère des Enseignements<br/>"
            "Secondaire, Technique et de la<br/>"
            "Formation Professionnelle",
            S('hl', fontSize=8, leading=12, alignment=TA_LEFT, textColor=NAVY)
        ),
        Table(
            [[logo_cell],
             [Paragraph("<b><font size='13'>COLLÈGE CATHOLIQUE<br/>PÈRE AUPIAIS</font></b>",
                        S('hc', fontSize=13, leading=17, alignment=TA_CENTER,
                          textColor=NAVY, fontName='Helvetica-Bold'))],
             [Paragraph("<font size='9' color='#C8A951'>━━━━━━━━━━━━━━━━━━━━</font>",
                        S('sep', alignment=TA_CENTER))],
             [Paragraph("<i><font size='8' color='#555'>Excellence · Intégrité · Service</font></i>",
                        S('dev', alignment=TA_CENTER))]],
            colWidths=[7*cm],
            style=TableStyle([
                ('ALIGN',         (0,0),(-1,-1),'CENTER'),
                ('TOPPADDING',    (0,0),(-1,-1), 2),
                ('BOTTOMPADDING', (0,0),(-1,-1), 2),
            ])
        ),
        Paragraph(
            "Cotonou — Bénin<br/>"
            "Tél : +229 21 30 XX XX<br/>"
            "Email : ccpa@ccpa.bj<br/>"
            f"Date : {datetime.date.today().strftime('%d/%m/%Y')}",
            S('hr', fontSize=8, leading=12, alignment=TA_RIGHT, textColor=NAVY)
        ),
    ]], colWidths=[5.5*cm, 7.5*cm, 5.5*cm])

    hdr.setStyle(TableStyle([
        ('VALIGN',        (0,0),(-1,-1),'MIDDLE'),
        ('BACKGROUND',    (0,0),(-1,-1), LIGHT),
        ('TOPPADDING',    (0,0),(-1,-1), 10),
        ('BOTTOMPADDING', (0,0),(-1,-1), 10),
        ('LEFTPADDING',   (0,0),(-1,-1), 8),
        ('RIGHTPADDING',  (0,0),(-1,-1), 8),
        ('LINEABOVE',     (0,0),(-1,-1), 2,   NAVY),
        ('LINEBELOW',     (0,0),(-1,-1), 3,   GOLD),
        ('LINEBEFORE',    (0,0),(0,-1),  2,   NAVY),
        ('LINEAFTER',     (-1,0),(-1,-1), 2,  NAVY),
    ]))
    story += [hdr, Spacer(1, 0.4*cm)]

    # ── TITRE ────────────────────────────────────────────────────────
    titre = Table([[
        Paragraph("<b><font color='white' size='15'>  FICHE DE NOTES  </font></b>",
                  S('tt', alignment=TA_CENTER, fontName='Helvetica-Bold'))
    ]], colWidths=[18.5*cm])
    titre.setStyle(TableStyle([
        ('BACKGROUND',    (0,0),(-1,-1), NAVY),
        ('TOPPADDING',    (0,0),(-1,-1), 9),
        ('BOTTOMPADDING', (0,0),(-1,-1), 9),
        ('ALIGN',         (0,0),(-1,-1),'CENTER'),
    ]))
    story += [titre, Spacer(1, 0.4*cm)]

    # ── BLOC INFO ────────────────────────────────────────────────────
    def ikey(t): return Paragraph(f"<b>{t}</b>", S('ik', fontSize=9, leading=14, fontName='Helvetica-Bold'))
    def ival(t): return Paragraph(str(t), S('iv', fontSize=9, leading=14, textColor=NAVY, fontName='Helvetica-Bold'))
    def itxt(t): return Paragraph(str(t), S('it', fontSize=9, leading=14))

    info = Table([
        [ikey("Classe :"),          ival(data.get('classe','')),
         ikey("Matière :"),         ival(data.get('matiere',''))],
        [itxt("Année scolaire :"),  itxt(data.get('annee_scolaire','')),
         itxt("Période :"),         itxt(data.get('periode',''))],
        [itxt("Type d'évaluation :"), itxt(data.get('type_note','')),
         itxt("Enseignant(e) :"),   itxt(data.get('enseignant',''))],
    ], colWidths=[4*cm, 5*cm, 4*cm, 5.5*cm])
    info.setStyle(TableStyle([
        ('BACKGROUND',    (0,0),(-1,-1), LIGHT),
        ('GRID',          (0,0),(-1,-1), 0.5, colors.HexColor('#CCCCCC')),
        ('TOPPADDING',    (0,0),(-1,-1), 6),
        ('BOTTOMPADDING', (0,0),(-1,-1), 6),
        ('LEFTPADDING',   (0,0),(-1,-1), 10),
    ]))
    story += [info, Spacer(1, 0.4*cm)]

    # ── TABLEAU NOTES ────────────────────────────────────────────────
    def th(t): return Paragraph(f"<b>{t}</b>", S('th', fontSize=9, alignment=TA_CENTER,
                                 textColor=WHITE, fontName='Helvetica-Bold'))
    def td(t, al=TA_LEFT, bold=False, sz=9):
        return Paragraph(str(t), S('td', fontSize=sz, alignment=al,
                         fontName='Helvetica-Bold' if bold else 'Helvetica'))

    rows = [[th("#"), th("Matricule"), th("Nom"), th("Prénom"),
             th("Note /20"), th("Commentaire"), th("Mention")]]

    notes     = data.get('notes', [])
    n_valid   = [n for n in notes if n.get('note') not in (None, '', 'None')]

    for i, e in enumerate(notes):
        m_txt, m_col = mention(e.get('note'))
        note_str     = str(e['note']) if e.get('note') not in (None,'','None') else "—"
        rows.append([
            td(str(e.get('num', i+1)), TA_CENTER),
            td(e.get('matricule',''), TA_CENTER, sz=8),
            td(e.get('nom',''),  bold=True),
            td(e.get('prenom','')),
            Paragraph(f"<b>{note_str}</b>",
                      S('n', fontSize=12, alignment=TA_CENTER, fontName='Helvetica-Bold',
                        textColor=colors.HexColor(m_col) if e.get('note') is not None else colors.gray)),
            td(e.get('commentaire',''), sz=8),
            Paragraph(f"<font color='{m_col}'><b>{m_txt}</b></font>",
                      S('mn', fontSize=8, alignment=TA_CENTER, fontName='Helvetica-Bold')),
        ])

    row_styles = []
    for i in range(1, len(rows)):
        row_styles.append(('BACKGROUND', (0,i), (-1,i), WHITE if i%2==1 else GRAY))

    nt = Table(rows, colWidths=[0.8*cm, 2.5*cm, 3.8*cm, 3.5*cm, 2*cm, 3.5*cm, 2.4*cm], repeatRows=1)
    nt.setStyle(TableStyle([
        ('BACKGROUND',    (0,0),(-1,0),  NAVY),
        ('LINEBELOW',     (0,0),(-1,0),  3,   GOLD),
        ('GRID',          (0,0),(-1,-1), 0.5, colors.HexColor('#CCCCCC')),
        ('LINEBELOW',     (0,-1),(-1,-1),1.5, NAVY),
        ('TOPPADDING',    (0,0),(-1,-1), 7),
        ('BOTTOMPADDING', (0,0),(-1,-1), 7),
        ('LEFTPADDING',   (0,0),(-1,-1), 5),
        ('VALIGN',        (0,0),(-1,-1),'MIDDLE'),
    ] + row_styles))
    story += [nt, Spacer(1, 0.5*cm)]

    # ── STATISTIQUES ─────────────────────────────────────────────────
    if n_valid:
        vals  = [float(n['note']) for n in n_valid]
        avg   = sum(vals)/len(vals)
        mx    = max(vals); mn_v = min(vals)
        admis = len([v for v in vals if v >= 10])
        taux  = round(admis/len(n_valid)*100, 1)
    else:
        avg = mx = mn_v = admis = taux = 0

    def stat(t): return Paragraph(t, S('st', fontSize=9, alignment=TA_CENTER, leading=16))

    stats = Table([[
        stat(f"Effectif évalué<br/><b>{len(n_valid)}/{len(notes)}</b>"),
        stat(f"Moyenne de classe<br/><b><font color='#003366'>{avg:.2f} / 20</font></b>"),
        stat(f"Note maximale<br/><b><font color='#005500'>{mx} / 20</font></b>"),
        stat(f"Note minimale<br/><b><font color='#cc0000'>{mn_v} / 20</font></b>"),
        stat(f"Taux de réussite<br/><b><font color='#003366'>{taux} %</font></b>"),
    ]], colWidths=[3.5*cm, 4*cm, 3.5*cm, 3.5*cm, 4*cm])
    stats.setStyle(TableStyle([
        ('BACKGROUND',    (0,0),(-1,-1), colors.HexColor('#FFF8E1')),
        ('GRID',          (0,0),(-1,-1), 1.5, GOLD),
        ('TOPPADDING',    (0,0),(-1,-1), 8),
        ('BOTTOMPADDING', (0,0),(-1,-1), 8),
        ('ALIGN',         (0,0),(-1,-1),'CENTER'),
        ('VALIGN',        (0,0),(-1,-1),'MIDDLE'),
    ]))
    story += [stats, Spacer(1, 0.9*cm)]

    # ── SIGNATURES ───────────────────────────────────────────────────
    def sig(title, name=""):
        t = f"<b>{title}</b><br/><br/><br/><br/>{'_'*30}"
        if name: t += f"<br/><i>{name}</i>"
        return Paragraph(t, S('sg', fontSize=9, alignment=TA_CENTER, leading=16))

    sigs = Table([[
        sig("Le Chef d'Établissement"),
        sig("L'Enseignant(e)", data.get('enseignant','')),
        sig("Cachet et Date", datetime.date.today().strftime("%d / %m / %Y")),
    ]], colWidths=[6*cm, 6.5*cm, 6*cm])
    sigs.setStyle(TableStyle([
        ('TOPPADDING',    (0,0),(-1,-1), 8),
        ('BOTTOMPADDING', (0,0),(-1,-1), 8),
        ('LINEBELOW',     (0,0),(-1,-1), 0.5, GOLD),
    ]))
    story += [sigs, Spacer(1, 0.3*cm)]

    # ── PIED DE PAGE ─────────────────────────────────────────────────
    story.append(HRFlowable(width='100%', thickness=2, color=NAVY))
    story.append(Spacer(1, 0.1*cm))
    story.append(Paragraph(
        f"Collège Catholique Père Aupiais — Cotonou, Bénin &nbsp;|&nbsp; "
        f"Document officiel &nbsp;|&nbsp; "
        f"Généré le {datetime.datetime.now().strftime('%d/%m/%Y à %H:%M')}",
        S('ft', fontSize=7, alignment=TA_CENTER, textColor=colors.HexColor('#777777'), leading=10)
    ))
    story.append(HRFlowable(width='100%', thickness=1, color=GOLD))

    doc.build(story)
    print(f"OK:{output}:{os.path.getsize(output)}")


# ── POINT D'ENTRÉE ────────────────────────────────────────────────────
if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Usage: python3 generate_notes_pdf.py <json_file>", file=sys.stderr)
        sys.exit(1)

    jf = sys.argv[1]
    if not os.path.exists(jf):
        print(f"JSON introuvable : {jf}", file=sys.stderr)
        sys.exit(1)

    with open(jf, 'r', encoding='utf-8') as f:
        payload = json.load(f)

    build(
        data      = payload['data'],
        logo_path = payload.get('logo_path'),
        output    = payload['output'],
    )
---
paths:
  - 'app/Pdf/**'
---

# Pdf

## Fpdf::Output — immer 'S', und auf die Argumentreihenfolge achten
`Fpdf::Output($dest, $name)` **vertauscht die Argumente**, wenn `strlen($name) == 1 && strlen($dest) != 1`. Deshalb liefern `Output('SEPA-Einzug', 'S')` (SepaPdf) und `Output('', 'S')` (BlsvPdf) beide korrekt einen String zurück — dort greift der Tausch.

`MemberPdf` und `MemberRolesPdf` riefen aus lsverein7 `Output('I', 'Liste.pdf')` auf. Da `$name` neun Zeichen hat, greift der Tausch **nicht**, `$dest` bleibt `'I'`: Fpdf gibt das PDF per `echo` auf die Standardausgabe, setzt eigene `Content-Type`- und `Content-Disposition`-Header und gibt `''` zurück. In lsverein7 fiel das nicht auf, weil die Bytes über den Output-Buffer trotzdem beim Browser landeten. Unter Inertia/Laravel wäre das ein leerer Response-Body plus PDF-Bytes daneben gewesen.

Seit 2026-08-26 beide auf `Output('S', 'Liste.pdf')`. **Neue PDF-Klassen immer mit `'S'` als erstem Argument**, nie `'I'` oder `'D'` — die Auslieferung macht der Controller über `response($content)`, nicht Fpdf.

`tests/Feature/MemberExportTest.php` prüft, dass beide PDFs mit `%PDF-` beginnen; das wird rot, wenn jemand auf `'I'` zurückgeht.

`app/Pdf` ist von phpstan ausgenommen (phpstan.neon), hier fällt so etwas also nicht statisch auf.

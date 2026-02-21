<!doctype html>
<html lang="ru">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <meta charset="UTF-8">
  <title>{{ $title }}</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">
  <style>
    @page { margin: 30mm 20mm 25mm; }
    body { margin: 0; font-family: Nunito, sans-serif; text-align: justify; font-size: 11pt; line-height: 1; color: #111827; }
    h1, h2, h3 { text-align: left; color: #111827; line-height: 1; break-after: avoid-page; page-break-after: avoid; }
    h1 { font-size: 20pt; }
    h2 { font-size: 14pt; margin-top: 20px; }
    h3 { font-size: 13pt; margin-top: 14px; }
    p { margin: 10px 0; widows: 2; orphans: 2; }
    ul { margin: 12px 0 12px 20px; padding: 0; }
    ol { margin: 12px 0 12px 28px; padding: 0; }
    ul ul, ol ol, ul ol, ol ul { margin-top: 0; margin-bottom: 0; }
    li { margin-top: 3px; margin-bottom: 3px; }
    strong { font-weight: 700; }
    em { font-style: italic; }
    u { text-decoration: underline; }
    #header, #footer { position: fixed; left: 0; right: 0; font-size: 10pt; }
    #header { top: -15mm; color: #555; }
    #footer { bottom: -10mm; }
    #header table, #footer table { width: 100%; border-collapse: collapse; border: none; }
    #header td, #footer td { padding: 0; width: 50%; }
    .page-number { text-align: center; }
    .page-number:before { content: counter(page); }
  </style>
</head>
<body>
    <div id="header">
      <table>
        <tr>
          <td>{{ $title }}</td>
          <td style="text-align: right;"><img width="150" src="{{ $logoPath }}" alt="Logo" /></td>
        </tr>
      </table>
    </div>

    <div id="footer">
      <div class="page-number"></div>
    </div>

    {!! $body !!}
</body>
</html>

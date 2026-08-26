---
paths:
  - config/log-viewer.php
---

# Config

## Log Viewer: leere Dateiliste kommt meist aus dem localStorage-Filter, nicht vom Server
Symptom: /log-viewer lädt, „Log files on Local" ist aber leer, obwohl `storage/logs/laravel.log` existiert.

Ursache am 2026-08-26: `localStorage.selectedFileTypes` stand auf `["php_fpm"]`. Der Dateityp-Filter der Oberfläche liegt im Browser, nicht auf dem Server, und `laravel.log` hat den Typ `laravel` — also filtert die Oberfläche alles weg. Der Wert stammte aus der Zeit, als `include_files` noch die System-Logs auflistete (der Cache trug noch einen `php-fpm.log`-Eintrag). Das Einschränken auf `laravel*.log` hat php-fpm.log entfernt, der Filter im Browser zeigte aber weiter genau darauf.

Behebung: im Zahnrad-Menü die Dateitypen zurücksetzen, oder `localStorage.removeItem('selectedFileTypes')`. Nichts am Code.

Deshalb zuerst den Browser prüfen, bevor irgendetwas serverseitig gesucht wird. Was hier alles gesund war und Zeit gekostet hat: Datei vorhanden, `LogViewer::getFiles()` findet sie, `GET /log-viewer/api/folders` liefert 200 mit gültigem JSON, Gate und Stateful-Session greifen. Merkmal dieser Fehlerklasse: **die API antwortet 200 mit Daten, die Liste bleibt trotzdem leer** — dann liegt es im Browserzustand (`defaults.use_local_storage` ist true, die Oberfläche merkt sich Filter, Sortierung, Theme).

Zwei Nebenbefunde, beide unkritisch:
- `public/vendor/log-viewer` ist nicht veröffentlicht. Das Paket bettet seine Assets dann inline ein (`LogViewer::css()`/`js()`), die Seite funktioniert also. `php artisan vendor:publish --tag=log-viewer-assets` wäre optional; lswatter4 läuft ebenfalls ohne.
- `tests/Feature/LogViewerTest.php` trifft nur die HTML-Seite und die Facade, **nicht** `GET /log-viewer/api/folders` — also nicht den Endpunkt, der die Dateiliste füllt. Tests dafür waren bei dieser Suche kurz da und wurden bewusst wieder verworfen: sie hätten diesen Fehler nicht gefunden (er lag im Browser), und der Endpunkt gehört dem Paket, nicht uns. Wer ihn doch absichern will, braucht `actingAs` plus einen `Referer`-Header, sonst greift `EnsureFrontendRequestsAreStateful` nicht.

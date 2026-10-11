# Sani Materialverwaltung

WordPress-Plugin für die Materialverwaltung des **Sanitätsvereins Leuggern**: Artikel, Lagerorte (Bereich › Behälter › Fach), Lieferanten, Inventar mit Kontrolle und PDF-Berichte (Inventarliste, Übersicht, Etiketten, Bestellliste).

Abgelöst aus der früheren Access-Datenbank `Materialverwaltung_Sani.accdb`.

## Installation

1. Unter **Releases** die neueste `sani-materialverwaltung-x.y.z.zip` herunterladen.
2. In WordPress: **Plugins › Installieren › Plugin hochladen** › ZIP wählen › **Jetzt installieren** › **Aktivieren**.
3. **Einstellungen › Materialverwaltung**:
   - Rechte festlegen (wer ansehen, wer bearbeiten darf).
   - Unter **Daten übernehmen** die Sicherung (JSON) aus der bisherigen App hochladen.
4. Auf einer Seite (z. B. «Intern › Material») den Shortcode `[sani_material]` einfügen.

## Update auf eine neue Version

1. **Einstellungen › Materialverwaltung › Sicherung herunterladen** (zur Sicherheit).
2. **Plugins › Installieren › Plugin hochladen** › neue ZIP wählen › «Aktuelle Version durch hochgeladene ersetzen».

Die Daten bleiben erhalten. Neue Datenfelder werden beim ersten Aufruf automatisch ergänzt.

## Aufbau

```
sani-materialverwaltung/
├─ sani-materialverwaltung.php   Plugin-Kopf, Version
├─ includes/
│  ├─ class-access.php           Rechte (Ansehen / Bearbeiten)
│  ├─ class-store.php            Datenbanktabelle wp_sani_mv_docs, Import/Export, Updates
│  ├─ class-rest.php             REST-API /wp-json/sani-mv/v1/
│  ├─ class-app.php              App-Seite (?sani-mv-app=1) und Shortcode [sani_material]
│  └─ class-admin.php            Einstellungsseite
├─ app/app.html                  Die App (gleiche Datei wie die Claude-Version)
├─ assets/wp-adapter.js          Verbindet die App mit der WordPress-Datenbank
└─ vendor/                       jsPDF und jsPDF-AutoTable (PDF-Erzeugung)
```

Die App speichert alles als JSON-Dokumente in vier Sammlungen (`artikel`, `plaetze`, `lieferanten`, `meta`). Mehrere Personen können gleichzeitig arbeiten; Änderungen anderer erscheinen nach spätestens 15 Sekunden.

## Neue Version bauen

```bash
./tools/build-zip.sh        # erzeugt dist/sani-materialverwaltung-<version>.zip
```

Die Version steht an zwei Stellen in `sani-materialverwaltung.php` (Kopfzeile `Version:` und `SANI_MV_VERSION`). Ändert sich die Datenstruktur, zusätzlich `SANI_MV_DB_VERSION` erhöhen und die Umstellung in `Sani_MV_Store::maybe_upgrade()` ergänzen.

## Lizenzen

Plugin: GPL-2.0-or-later. jsPDF und jsPDF-AutoTable: MIT (siehe `vendor/`).

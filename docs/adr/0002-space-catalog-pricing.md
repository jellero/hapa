# ADR 0002 — Space alimenta il catalogo, HAPA governa l’offerta pubblicabile

- Stato: accettata
- Data: 16 luglio 2026

## Contesto

HAPA deve sincronizzare prezzi e disponibilità da Space e distribuirli a più marketplace. Copiare direttamente il dato da Space a ogni canale impedirebbe di applicare scorte di sicurezza e ricarichi coerenti, renderebbe difficile la riconciliazione e introdurrebbe più writer concorrenti.

Prezzo base, disponibilità fisica, prezzo di vendita e quantità pubblicabile hanno ownership differenti. Inoltre SellRapido può essere il percorso tecnico di un canale senza diventare il canale stesso.

## Decisione

- Space è la sorgente autorevole della propria offerta fornitore: costo di acquisto e disponibilità osservata.
- HAPA conserva uno snapshot versionato, sottrae la scorta di sicurezza e calcola l’offerta commerciale.
- Una sola regola di ricarico vince secondo specificità e priorità; le regole non vengono cumulate implicitamente.
- HAPA è la sorgente autorevole di prezzo finale e quantità vendibile.
- Ogni marketplace riceve una proiezione per account-canale e SKU tramite un unico connettore attivo.
- Le pubblicazioni sono idempotenti, versionate, asincrone e riconciliabili tramite outbox.
- Un valore remoto divergente non sovrascrive automaticamente il dato HAPA.
- Articoli, regole e pubblicazioni sono separati dalla disponibilità delle righe ordine.

## Conseguenze

HAPA introduce il modulo `Catalog` e separa `catalog_items`, `supplier_catalog_items`, `pricing_rules` e `marketplace_offers`. Solo l’acquisizione Space è periodica; la pubblicazione marketplace nasce da un comando HAPA quando cambia l’offerta desiderata.

L’architettura può supportare il futuro storefront B2C, ma non rende operative varianti, contenuti, imposte, promozioni, checkout o pagamenti.

Prima dell’attivazione servono specifiche e account sandbox reali, policy sui dati Space scaduti, autorizzazione delle modifiche prezzo e test di riconciliazione dopo esiti ambigui.

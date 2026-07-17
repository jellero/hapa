# ADR 0004 — HAPA come system of record commerciale

Data: 17 luglio 2026.

## Stato

Accettata.

## Contesto

HAPA è separata da Space e compra da Space per rivendere sui marketplace. Il modello precedente mescolava prodotto con dato fornitore, vendita con invio a Space e job tecnici con decisioni commerciali.

## Decisione

- HAPA possiede prodotti, clienti, vendite, acquisti, spedizioni e fiscalità.
- Space è modellato come fornitore.
- L’ordine marketplace è una vendita; l’ordine Space è un acquisto distinto.
- Automation possiede soltanto integrazione tecnica e proiezioni ricostruibili.
- I timer Automation avviano polling e riconciliazioni; le azioni commerciali partono da comandi HAPA.
- Il broker è infrastruttura condivisa, non un database o un proprietario del dominio.

## Conseguenze

Vengono introdotti account marketplace, offerte fornitore, acquisti Space, storico cliente, colli e label. Le strutture legacy vengono migrate in modo additivo prima della rimozione.
